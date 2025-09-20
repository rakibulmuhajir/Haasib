<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LedgerIntegrationService
{
    private function logAudit(string $action, array $params, ?User $user = null, ?string $companyId = null, ?string $idempotencyKey = null, ?array $result = null): void
    {
        try {
            DB::transaction(function () use ($action, $params, $user, $companyId, $idempotencyKey, $result) {
                DB::table('audit_logs')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user?->id,
                    'company_id' => $companyId,
                    'action' => $action,
                    'params' => json_encode($params),
                    'result' => $result ? json_encode($result) : null,
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to write audit log', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function postInvoiceToLedger(Invoice $invoice, bool $forceRepost = false): JournalEntry
    {
        if (! $invoice->canBePosted()) {
            throw new \InvalidArgumentException('Invoice cannot be posted to ledger');
        }

        $existingEntry = $this->findExistingInvoiceEntry($invoice);
        if ($existingEntry && ! $forceRepost) {
            throw new \InvalidArgumentException('Invoice already posted to ledger');
        }

        $result = DB::transaction(function () use ($invoice, $existingEntry) {
            if ($existingEntry && $forceRepost) {
                $this->voidExistingEntry($existingEntry, 'Force repost of invoice');
            }

            $journalEntry = $this->createInvoiceJournalEntry($invoice);
            $this->postJournalEntry($journalEntry);

            $invoice->posted_at = now();
            $invoice->posted_by_user_id = auth()->id();
            $invoice->save();

            return $journalEntry;
        });

        $this->logAudit('ledger.invoice.post', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'total_amount' => $invoice->total_amount,
            'force_repost' => $forceRepost,
        ], auth()->user(), $invoice->company_id, result: ['journal_entry_id' => $result->id]);

        return $result;
    }

    public function postPaymentToLedger(Payment $payment): JournalEntry
    {
        if (! $payment->isCompleted()) {
            throw new \InvalidArgumentException('Payment must be completed before posting to ledger');
        }

        $existingEntry = $this->findExistingPaymentEntry($payment);
        if ($existingEntry) {
            throw new \InvalidArgumentException('Payment already posted to ledger');
        }

        $result = DB::transaction(function () use ($payment) {
            $journalEntry = $this->createPaymentJournalEntry($payment);
            $this->postJournalEntry($journalEntry);

            $payment->metadata = array_merge($payment->metadata ?? [], [
                'posted_to_ledger_at' => now()->toISOString(),
                'posted_by_user_id' => auth()->id(),
            ]);
            $payment->save();

            return $journalEntry;
        });

        $this->logAudit('ledger.payment.post', [
            'payment_id' => $payment->id,
            'payment_reference' => $payment->payment_reference,
            'amount' => $payment->amount,
        ], auth()->user(), $payment->company_id, result: ['journal_entry_id' => $result->id]);

        return $result;
    }

    public function postPaymentAllocationToLedger(PaymentAllocation $allocation): JournalEntry
    {
        if (! $allocation->isActive()) {
            throw new \InvalidArgumentException('Only active payment allocations can be posted to ledger');
        }

        $existingEntry = $this->findExistingAllocationEntry($allocation);
        if ($existingEntry) {
            throw new \InvalidArgumentException('Payment allocation already posted to ledger');
        }

        $result = DB::transaction(function () use ($allocation) {
            $journalEntry = $this->createPaymentAllocationJournalEntry($allocation);
            $this->postJournalEntry($journalEntry);

            return $journalEntry;
        });

        $this->logAudit('ledger.allocation.post', [
            'allocation_id' => $allocation->id,
            'payment_id' => $allocation->payment_id,
            'invoice_id' => $allocation->invoice_id,
            'amount' => $allocation->amount,
        ], auth()->user(), $allocation->payment->company_id, result: ['journal_entry_id' => $result->id]);

        return $result;
    }

    public function voidInvoiceLedgerEntry(Invoice $invoice, string $reason): JournalEntry
    {
        $existingEntry = $this->findExistingInvoiceEntry($invoice);
        if (! $existingEntry) {
            throw new \InvalidArgumentException('No ledger entry found for this invoice');
        }

        $result = DB::transaction(function () use ($existingEntry, $reason) {
            $voidedEntry = $this->voidExistingEntry($existingEntry, $reason);

            $invoice->posted_at = null;
            $invoice->posted_by_user_id = null;
            $invoice->save();

            return $voidedEntry;
        });

        $this->logAudit('ledger.invoice.void', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'reason' => $reason,
        ], auth()->user(), $invoice->company_id, result: ['voided_entry_id' => $result->id]);

        return $result;
    }

    public function getInvoiceLedgerEntries(Invoice $invoice): array
    {
        $entries = JournalEntry::where('company_id', $invoice->company_id)
            ->where(function ($query) use ($invoice) {
                $query->where('source_type', 'invoice')
                    ->where('source_id', $invoice->id)
                    ->orWhere('description', 'like', "%{$invoice->invoice_number}%");
            })
            ->with(['journalLines.ledgerAccount'])
            ->orderBy('date', 'desc')
            ->get();

        return $entries->map(fn ($entry) => [
            'entry_id' => $entry->id,
            'date' => $entry->date,
            'description' => $entry->description,
            'reference' => $entry->reference,
            'status' => $entry->status,
            'total_debit' => $entry->journalLines->sum('debit_amount'),
            'total_credit' => $entry->journalLines->sum('credit_amount'),
            'lines' => $entry->journalLines->map(fn ($line) => [
                'account_name' => $line->ledgerAccount->name,
                'account_code' => $line->ledgerAccount->code,
                'debit_amount' => $line->debit_amount,
                'credit_amount' => $line->credit_amount,
                'description' => $line->description,
            ]),
        ])->toArray();
    }

    public function getCustomerAccountBalance(Company $company, string $customerId, ?string $asOfDate = null): array
    {
        $receivablesAccount = $this->getAccountsReceivableAccount($company);
        if (! $receivablesAccount) {
            throw new \InvalidArgumentException('Accounts Receivable account not found');
        }

        $query = $receivablesAccount->journalLines()
            ->whereHas('journalEntry', function ($q) use ($company, $customerId) {
                $q->where('company_id', $company->id)
                    ->where('status', 'posted')
                    ->where('metadata->customer_id', $customerId);
            });

        if ($asOfDate) {
            $query->whereHas('journalEntry', fn ($q) => $q->where('date', '<=', $asOfDate));
        }

        $totalDebit = $query->sum('debit_amount');
        $totalCredit = $query->sum('credit_amount');

        $balance = $totalDebit - $totalCredit;

        return [
            'customer_id' => $customerId,
            'account_id' => $receivablesAccount->id,
            'account_name' => $receivablesAccount->name,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => $balance,
            'balance_type' => $balance >= 0 ? 'debit' : 'credit',
            'as_of_date' => $asOfDate ?? now()->toDateString(),
        ];
    }

    public function reconcileCustomerAccount(Company $company, string $customerId): array
    {
        $ledgerBalance = $this->getCustomerAccountBalance($company, $customerId);

        $customerOutstanding = Invoice::where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['sent', 'posted', 'partial'])
            ->sum('balance_due');

        $customerPaid = Payment::where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->where('status', 'completed')
            ->sum('amount');

        $discrepancy = abs($ledgerBalance['balance'] - ($customerOutstanding - $customerPaid));

        return [
            'customer_id' => $customerId,
            'ledger_balance' => $ledgerBalance['balance'],
            'calculated_outstanding' => $customerOutstanding,
            'total_payments' => $customerPaid,
            'expected_balance' => $customerOutstanding - $customerPaid,
            'discrepancy' => $discrepancy,
            'is_balanced' => $discrepancy < 0.01,
            'reconciliation_date' => now()->toDateString(),
        ];
    }

    private function createInvoiceJournalEntry(Invoice $invoice): JournalEntry
    {
        $accounts = $this->getInvoiceAccounts($invoice->company, $invoice);

        $journalLines = [
            [
                'account_id' => $accounts['receivable']->id,
                'debit_amount' => $invoice->total_amount,
                'credit_amount' => 0,
                'description' => "Invoice #{$invoice->invoice_number} - {$invoice->customer->name}",
            ],
            [
                'account_id' => $accounts['revenue']->id,
                'debit_amount' => 0,
                'credit_amount' => $invoice->subtotal,
                'description' => "Revenue from invoice #{$invoice->invoice_number}",
            ],
        ];

        if ($invoice->tax_amount > 0) {
            $journalLines[] = [
                'account_id' => $accounts['tax_payable']->id,
                'debit_amount' => 0,
                'credit_amount' => $invoice->tax_amount,
                'description' => "Tax payable from invoice #{$invoice->invoice_number}",
            ];
        }

        return $this->createJournalEntry(
            $invoice->company,
            "Invoice Posting - {$invoice->invoice_number}",
            $journalLines,
            $invoice->invoice_number,
            $invoice->invoice_date,
            'invoice',
            $invoice->getKey(),
            ['customer_id' => $invoice->customer_id]
        );
    }

    private function createPaymentJournalEntry(Payment $payment): JournalEntry
    {
        $accounts = $this->getPaymentAccounts($payment->company, $payment);

        $amountInCompanyCurrency = $payment->getAmountInCompanyCurrency();

        $journalLines = [
            [
                'account_id' => $accounts['cash']->id,
                'debit_amount' => $amountInCompanyCurrency->getAmount()->toFloat(),
                'credit_amount' => 0,
                'description' => "Payment received - {$payment->payment_reference}",
            ],
            [
                'account_id' => $accounts['receivable']->id,
                'debit_amount' => 0,
                'credit_amount' => $amountInCompanyCurrency->getAmount()->toFloat(),
                'description' => "Payment applied to account - {$payment->payment_reference}",
            ],
        ];

        return $this->createJournalEntry(
            $payment->company,
            "Payment Received - {$payment->payment_reference}",
            $journalLines,
            $payment->payment_reference,
            $payment->payment_date,
            'payment',
            $payment->id,
            ['customer_id' => $payment->customer_id]
        );
    }

    private function createPaymentAllocationJournalEntry(PaymentAllocation $allocation): JournalEntry
    {
        $invoice = $allocation->invoice;
        $payment = $allocation->payment;
        $company = $allocation->payment->company;

        $amountInCompanyCurrency = Money::of($allocation->allocated_amount, $payment->currency->code)
            ->multipliedBy($payment->exchange_rate);

        $journalLines = [
            [
                'account_id' => $this->getAccountsReceivableAccount($company)->id,
                'debit_amount' => 0,
                'credit_amount' => $amountInCompanyCurrency->getAmount()->toFloat(),
                'description' => "Payment allocation - Invoice #{$invoice->invoice_number}",
            ],
            [
                'account_id' => $this->getAccountsReceivableAccount($company)->id,
                'debit_amount' => $amountInCompanyCurrency->getAmount()->toFloat(),
                'credit_amount' => 0,
                'description' => "Payment received - {$payment->payment_reference}",
            ],
        ];

        return $this->createJournalEntry(
            $company,
            "Payment Allocation - {$payment->payment_reference} to Invoice #{$invoice->invoice_number}",
            $journalLines,
            $payment->payment_reference,
            $allocation->allocation_date,
            'payment_allocation',
            $allocation->getKey(),
            [
                'customer_id' => $payment->customer_id,
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
            ]
        );
    }

    private function createJournalEntry(
        Company $company,
        string $description,
        array $lines,
        ?string $reference = null,
        ?string $date = null,
        ?string $sourceType = null,
        ?string $sourceId = null,
        ?array $metadata = null
    ): JournalEntry {
        $entry = new JournalEntry([
            'company_id' => $company->id,
            'reference' => $reference,
            'date' => $date ?? now()->toDateString(),
            'description' => $description,
            'status' => 'draft',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'metadata' => $metadata,
        ]);

        $entry->save();

        $lineNumber = 1;
        foreach ($lines as $line) {
            JournalLine::create([
                'company_id' => $company->id,
                'journal_entry_id' => $entry->id,
                'ledger_account_id' => $line['account_id'],
                'description' => $line['description'],
                'debit_amount' => $line['debit_amount'],
                'credit_amount' => $line['credit_amount'],
                'line_number' => $lineNumber++,
            ]);
        }

        return $entry->fresh(['journalLines.ledgerAccount']);
    }

    private function postJournalEntry(JournalEntry $entry): void
    {
        if (! $entry->isBalanced()) {
            throw new \InvalidArgumentException('Journal entry is not balanced');
        }

        $entry->status = 'posted';
        $entry->posted_at = now();
        $entry->posted_by_user_id = auth()->id();
        $entry->save();

        Log::info('Journal entry posted to ledger', [
            'entry_id' => $entry->id,
            'company_id' => $entry->company_id,
            'description' => $entry->description,
        ]);
    }

    private function findExistingInvoiceEntry(Invoice $invoice): ?JournalEntry
    {
        return JournalEntry::where('company_id', $invoice->company_id)
            ->where('source_type', 'invoice')
            ->where('source_id', $invoice->id)
            ->where('status', 'posted')
            ->first();
    }

    private function findExistingPaymentEntry(Payment $payment): ?JournalEntry
    {
        return JournalEntry::where('company_id', $payment->company_id)
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->where('status', 'posted')
            ->first();
    }

    private function findExistingAllocationEntry(PaymentAllocation $allocation): ?JournalEntry
    {
        return JournalEntry::where('company_id', $allocation->payment->company_id)
            ->where('source_type', 'payment_allocation')
            ->where('source_id', $allocation->id)
            ->where('status', 'posted')
            ->first();
    }

    private function voidExistingEntry(JournalEntry $entry, string $reason): JournalEntry
    {
        $voidEntry = new JournalEntry([
            'company_id' => $entry->company_id,
            'reference' => "VOID-{$entry->reference}",
            'date' => now()->toDateString(),
            'description' => "Voiding: {$entry->description}",
            'status' => 'draft',
            'source_type' => 'void',
            'metadata' => [
                'voided_entry_id' => $entry->id,
                'void_reason' => $reason,
                'voided_at' => now()->toISOString(),
                'voided_by_user_id' => auth()->id(),
            ],
        ]);

        $voidEntry->save();

        foreach ($entry->journalLines as $line) {
            JournalLine::create([
                'company_id' => $entry->company_id,
                'journal_entry_id' => $voidEntry->id,
                'ledger_account_id' => $line->ledger_account_id,
                'description' => "Void: {$line->description}",
                'debit_amount' => $line->credit_amount,
                'credit_amount' => $line->debit_amount,
                'line_number' => $line->line_number,
            ]);
        }

        $this->postJournalEntry($voidEntry);

        $entry->status = 'void';
        $entry->metadata = array_merge($entry->metadata ?? [], [
            'voided_at' => now()->toISOString(),
            'void_reason' => $reason,
            'voided_by_user_id' => auth()->id(),
        ]);
        $entry->save();

        return $voidEntry;
    }

    private function getInvoiceAccounts(Company $company, Invoice $invoice): array
    {
        return [
            'receivable' => $this->getAccountsReceivableAccount($company),
            'revenue' => $this->getRevenueAccount($company),
            'tax_payable' => $this->getTaxPayableAccount($company),
        ];
    }

    private function getPaymentAccounts(Company $company, Payment $payment): array
    {
        return [
            'cash' => $this->getCashAccount($company, $payment->payment_method),
            'receivable' => $this->getAccountsReceivableAccount($company),
        ];
    }

    private function getAccountsReceivableAccount(Company $company): ?LedgerAccount
    {
        return LedgerAccount::where('company_id', $company->id)
            ->where('type', 'asset')
            ->where('code', 'like', '1200%')
            ->where('active', true)
            ->first();
    }

    private function getRevenueAccount(Company $company): ?LedgerAccount
    {
        return LedgerAccount::where('company_id', $company->id)
            ->where('type', 'revenue')
            ->where('code', 'like', '4000%')
            ->where('active', true)
            ->first();
    }

    private function getTaxPayableAccount(Company $company): ?LedgerAccount
    {
        return LedgerAccount::where('company_id', $company->id)
            ->where('type', 'liability')
            ->where('code', 'like', '2200%')
            ->where('active', true)
            ->first();
    }

    private function getCashAccount(Company $company, string $paymentMethod): ?LedgerAccount
    {
        $accountMap = [
            'cash' => '1010',
            'bank_transfer' => '1020',
            'check' => '1030',
            'credit_card' => '1040',
            'paypal' => '1050',
            'stripe' => '1060',
        ];

        $code = $accountMap[$paymentMethod] ?? '1010';

        return LedgerAccount::where('company_id', $company->id)
            ->where('type', 'asset')
            ->where('code', 'like', $code.'%')
            ->where('active', true)
            ->first();
    }
}
