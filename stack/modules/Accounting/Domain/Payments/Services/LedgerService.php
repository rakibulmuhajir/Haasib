<?php

namespace Modules\Accounting\Domain\Payments\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntrySource;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Accounting\Domain\Ledgers\Actions\AutoJournalEntryAction;
use Modules\Accounting\Domain\Payments\Events\AllocationReversed;
use Modules\Accounting\Domain\Payments\Events\PaymentAllocated;
use Modules\Accounting\Domain\Payments\Events\PaymentRecorded;
use Modules\Accounting\Domain\Payments\Events\PaymentReversed;

class LedgerService
{
    /**
     * Chart of accounts constants.
     */
    const ACCOUNTS_RECEIVABLE = '1100'; // AR

    const CASH_ACCOUNT = '1200'; // Bank/Cash

    const UNDEPOSITED_FUNDS = '1250'; // Undeposited Funds

    const PAYMENT_PROCESSING_FEES = '4600'; // Expense - Processing Fees

    const SALES_DISCOUNTS = '4700'; // Revenue - Discounts

    const SALES_RETURNS = '4750'; // Revenue - Returns

    /**
     * Journal entry types.
     */
    const JOURNAL_PAYMENT = 'payment';

    const JOURNAL_REVERSAL = 'reversal';

    const JOURNAL_ALLOCATION = 'allocation';

    const JOURNAL_ALLOCATION_REVERSAL = 'allocation_reversal';

    /**
     * Create journal entries for payment recording.
     */
    public function recordPayment(PaymentRecorded $event): array
    {
        $payment = $event->payment;

        return DB::transaction(function () use ($payment) {
            $autoJournalAction = app(AutoJournalEntryAction::class);

            // Determine accounts based on payment method
            $cashAccountId = $this->getAccountIdForPaymentMethod($payment->method);
            $arAccountId = $this->getAccountsReceivableAccountId($payment->company_id);

            if (! $cashAccountId || ! $arAccountId) {
                throw new \Exception('Required accounts not found for payment processing');
            }

            $journalData = [
                'company_id' => $payment->company_id,
                'description' => "Payment received - {$payment->payment_number}",
                'date' => $payment->payment_date,
                'type' => 'payment',
                'currency' => $payment->currency,
                'reference' => $payment->payment_number,
                'lines' => [
                    [
                        'account_id' => $cashAccountId,
                        'debit_credit' => 'debit',
                        'amount' => $payment->amount,
                        'description' => "Payment via {$payment->method}",
                    ],
                    [
                        'account_id' => $arAccountId,
                        'debit_credit' => 'credit',
                        'amount' => $payment->amount,
                        'description' => "Payment applied - {$payment->payment_number}",
                    ],
                ],
                'source_data' => [
                    'source_type' => 'payment',
                    'source_id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'payment_method' => $payment->method,
                    'entity_id' => $payment->entity_id,
                    'entity_type' => $payment->entity_type ?? 'customer',
                    'auto_allocated' => $payment->auto_allocated ?? false,
                ],
                'idempotency_key' => "payment_recorded_{$payment->id}",
            ];

            $result = $autoJournalAction->execute($journalData);

            // Create source document link
            if (isset($result['journal_entry_id'])) {
                JournalEntrySource::create([
                    'journal_entry_id' => $result['journal_entry_id'],
                    'source_type' => 'payment',
                    'source_id' => $payment->id,
                    'source_data' => $journalData['source_data'],
                ]);
            }

            return [$result];
        });
    }

    /**
     * Create journal entries for payment allocations.
     */
    public function recordAllocation(PaymentAllocated $event): array
    {
        $allocation = $event->allocation;

        return DB::transaction(function () use ($allocation) {
            $autoJournalAction = app(AutoJournalEntryAction::class);

            // Get required accounts
            $undepositedFundsId = $this->getUndepositedFundsAccountId($allocation->company_id);
            $arAccountId = $this->getAccountsReceivableAccountId($allocation->company_id);

            if (! $undepositedFundsId || ! $arAccountId) {
                throw new \Exception('Required accounts not found for allocation processing');
            }

            $journalData = [
                'company_id' => $allocation->company_id,
                'description' => "Payment allocated to invoice #{$allocation->invoice->invoice_number}",
                'date' => $allocation->allocation_date,
                'type' => 'allocation',
                'currency' => $allocation->currency,
                'reference' => $allocation->payment->payment_number,
                'lines' => [
                    [
                        'account_id' => $undepositedFundsId,
                        'debit_credit' => 'debit',
                        'amount' => $allocation->amount,
                        'description' => "Funds allocated to invoice #{$allocation->invoice->invoice_number}",
                    ],
                    [
                        'account_id' => $arAccountId,
                        'debit_credit' => 'credit',
                        'amount' => $allocation->amount,
                        'description' => "Invoice #{$allocation->invoice->invoice_number} payment applied",
                    ],
                ],
                'source_data' => [
                    'source_type' => 'payment_allocation',
                    'source_id' => $allocation->id,
                    'payment_id' => $allocation->payment_id,
                    'invoice_id' => $allocation->invoice_id,
                    'invoice_number' => $allocation->invoice->invoice_number,
                    'payment_number' => $allocation->payment->payment_number,
                    'allocation_amount' => $allocation->amount,
                    'allocation_method' => $allocation->allocation_method,
                    'entity_id' => $allocation->payment->entity_id,
                ],
                'idempotency_key' => "payment_allocation_{$allocation->id}",
            ];

            $result = $autoJournalAction->execute($journalData);

            // Create source document link
            if (isset($result['journal_entry_id'])) {
                JournalEntrySource::create([
                    'journal_entry_id' => $result['journal_entry_id'],
                    'source_type' => 'payment_allocation',
                    'source_id' => $allocation->id,
                    'source_data' => $journalData['source_data'],
                ]);
            }

            return [$result];
        });
    }

    /**
     * Create journal entries for payment reversals.
     */
    public function recordPaymentReversal(PaymentReversed $event): array
    {
        $reversalData = $event->getData();
        $paymentId = $reversalData['payment_id'];
        $reversedAmount = $reversalData['reversed_amount'];
        $reversalMethod = $reversalData['reversal_method'];
        $reason = $reversalData['reason'];
        $companyId = $reversalData['company_id'];

        return DB::transaction(function () use ($paymentId, $reversedAmount, $reversalMethod, $reason, $companyId, $reversalData) {
            $journalEntries = [];

            // Get the original payment method to determine accounts
            $originalPaymentMethod = $reversalData['payment_method'];
            $cashAccount = $this->getCashAccountForPaymentMethod($originalPaymentMethod);
            $arAccount = $this->shouldUseUndepositedFunds($originalPaymentMethod)
                ? self::UNDEPOSITED_FUNDS
                : self::ACCOUNTS_RECEIVABLE;

            // Credit: Cash/Bank Account (decrease assets - money returned/refunded)
            $journalEntries[] = $this->createJournalEntry([
                'payment_id' => $paymentId,
                'company_id' => $companyId,
                'entry_type' => self::JOURNAL_REVERSAL,
                'account_code' => $cashAccount,
                'debit_amount' => 0,
                'credit_amount' => $reversedAmount,
                'description' => "Payment reversal - {$reversalData['payment_number']} ({$reversalMethod})",
                'reference' => $reversalData['payment_number'].'-R',
                'date' => now()->toDateString(),
                'metadata' => [
                    'reversal_method' => $reversalMethod,
                    'reversal_reason' => $reason,
                    'original_payment_method' => $originalPaymentMethod,
                    'entity_id' => $reversalData['entity_id'],
                    'reversal_id' => $reversalData['reversal_id'],
                ],
            ]);

            // Debit: Accounts Receivable or Undeposited Funds (increase - payment is cancelled)
            $journalEntries[] = $this->createJournalEntry([
                'payment_id' => $paymentId,
                'company_id' => $companyId,
                'entry_type' => self::JOURNAL_REVERSAL,
                'account_code' => $arAccount,
                'debit_amount' => $reversedAmount,
                'credit_amount' => 0,
                'description' => "Payment reversal applied - {$reversalData['payment_number']}",
                'reference' => $reversalData['payment_number'].'-R',
                'date' => now()->toDateString(),
                'metadata' => [
                    'reversal_method' => $reversalMethod,
                    'reversal_reason' => $reason,
                    'original_payment_method' => $originalPaymentMethod,
                    'entity_id' => $reversalData['entity_id'],
                    'reversal_id' => $reversalData['reversal_id'],
                ],
            ]);

            // Additional entry for chargebacks (creates liability)
            if ($reversalMethod === 'chargeback') {
                $journalEntries[] = $this->createJournalEntry([
                    'payment_id' => $paymentId,
                    'company_id' => $companyId,
                    'entry_type' => self::JOURNAL_REVERSAL,
                    'account_code' => '2100', // Credit Card Payable
                    'debit_amount' => 0,
                    'credit_amount' => $reversedAmount,
                    'description' => "Chargeback liability - {$reversalData['payment_number']}",
                    'reference' => $reversalData['payment_number'].'-CB',
                    'date' => now()->toDateString(),
                    'metadata' => [
                        'reversal_method' => $reversalMethod,
                        'reversal_reason' => $reason,
                        'original_payment_method' => $originalPaymentMethod,
                        'entity_id' => $reversalData['entity_id'],
                        'reversal_id' => $reversalData['reversal_id'],
                        'is_liability' => true,
                    ],
                ]);
            }

            // Emit ledger events
            foreach ($journalEntries as $entry) {
                Event::dispatch(new LedgerEntryCreated([
                    'journal_entry_id' => $entry['id'],
                    'payment_id' => $paymentId,
                    'company_id' => $companyId,
                    'entry_type' => self::JOURNAL_REVERSAL,
                    'account_code' => $entry['account_code'],
                    'debit_amount' => $entry['debit_amount'],
                    'credit_amount' => $entry['credit_amount'],
                    'description' => $entry['description'],
                    'timestamp' => now()->toISOString(),
                    'metadata' => $entry['metadata'],
                ]));
            }

            return $journalEntries;
        });
    }

    /**
     * Create journal entries for allocation reversals.
     */
    public function recordAllocationReversal(AllocationReversed $event): array
    {
        $reversalData = $event->getData();
        $allocationId = $reversalData['allocation_id'];
        $refundAmount = $reversalData['refund_amount'];
        $originalAmount = $reversalData['original_amount'];
        $reason = $reversalData['reason'];
        $companyId = $reversalData['company_id'];

        return DB::transaction(function () use ($allocationId, $refundAmount, $originalAmount, $reason, $companyId, $reversalData) {
            $journalEntries = [];

            // Credit: Undeposited Funds (increase - funds are now unallocated)
            $journalEntries[] = $this->createJournalEntry([
                'allocation_id' => $allocationId,
                'company_id' => $companyId,
                'entry_type' => self::JOURNAL_ALLOCATION_REVERSAL,
                'account_code' => self::UNDEPOSITED_FUNDS,
                'debit_amount' => 0,
                'credit_amount' => $refundAmount,
                'description' => "Allocation reversal - Invoice #{$reversalData['invoice_number']}",
                'reference' => $reversalData['payment_number'].'-AR',
                'date' => now()->toDateString(),
                'metadata' => [
                    'original_amount' => $originalAmount,
                    'refund_amount' => $refundAmount,
                    'reversal_reason' => $reason,
                    'invoice_id' => $reversalData['invoice_id'],
                    'invoice_number' => $reversalData['invoice_number'],
                    'payment_id' => $reversalData['payment_id'],
                    'entity_id' => $reversalData['entity_id'],
                ],
            ]);

            // Debit: Accounts Receivable (increase - invoice balance restored)
            $journalEntries[] = $this->createJournalEntry([
                'allocation_id' => $allocationId,
                'company_id' => $companyId,
                'entry_type' => self::JOURNAL_ALLOCATION_REVERSAL,
                'account_code' => self::ACCOUNTS_RECEIVABLE,
                'debit_amount' => $refundAmount,
                'credit_amount' => 0,
                'description' => "Invoice balance restored - #{$reversalData['invoice_number']}",
                'reference' => $reversalData['invoice_number'].'-R',
                'date' => now()->toDateString(),
                'metadata' => [
                    'original_amount' => $originalAmount,
                    'refund_amount' => $refundAmount,
                    'reversal_reason' => $reason,
                    'invoice_id' => $reversalData['invoice_id'],
                    'invoice_number' => $reversalData['invoice_number'],
                    'payment_id' => $reversalData['payment_id'],
                    'entity_id' => $reversalData['entity_id'],
                ],
            ]);

            // Emit ledger events
            foreach ($journalEntries as $entry) {
                Event::dispatch(new LedgerEntryCreated([
                    'journal_entry_id' => $entry['id'],
                    'allocation_id' => $allocationId,
                    'company_id' => $companyId,
                    'entry_type' => self::JOURNAL_ALLOCATION_REVERSAL,
                    'account_code' => $entry['account_code'],
                    'debit_amount' => $entry['debit_amount'],
                    'credit_amount' => $entry['credit_amount'],
                    'description' => $entry['description'],
                    'timestamp' => now()->toISOString(),
                    'metadata' => $entry['metadata'],
                ]));
            }

            return $journalEntries;
        });
    }

    /**
     * Get account ID for payment method.
     */
    private function getAccountIdForPaymentMethod(string $paymentMethod, ?string $companyId = null): ?string
    {
        $accountMapping = [
            'cash' => '10000', // Cash
            'bank_transfer' => '11000', // Bank Account
            'card' => '11000', // Bank Account (Card processing)
            'cheque' => '11000', // Bank Account (Cheques)
            'other' => '10000', // Other payment methods
        ];

        $accountCode = $accountMapping[$paymentMethod] ?? '10000';

        if ($companyId) {
            $account = Account::where('company_id', $companyId)
                ->where('code', $accountCode)
                ->first();

            return $account?->id;
        }

        return Account::where('code', $accountCode)->first()?->id;
    }

    /**
     * Get accounts receivable account ID for company.
     */
    private function getAccountsReceivableAccountId(string $companyId): ?string
    {
        return Account::where('company_id', $companyId)
            ->where('code', '12000') // Accounts Receivable
            ->first()?->id;
    }

    /**
     * Get undeposited funds account ID for company.
     */
    private function getUndepositedFundsAccountId(string $companyId): ?string
    {
        return Account::where('company_id', $companyId)
            ->where('code', '12500') // Undeposited Funds
            ->first()?->id;
    }

    /**
     * Get payment method ledger account mapping.
     */
    public function getPaymentMethodAccountMapping(): array
    {
        return [
            'cash' => [
                'debit_account' => '1201', // Cash on Hand
                'credit_account' => self::UNDEPOSITED_FUNDS,
            ],
            'bank_transfer' => [
                'debit_account' => '1210', // Bank Account - Checking
                'credit_account' => self::ACCOUNTS_RECEIVABLE,
            ],
            'card' => [
                'debit_account' => '1220', // Bank Account - Card Processing
                'credit_account' => self::ACCOUNTS_RECEIVABLE,
            ],
            'cheque' => [
                'debit_account' => '1230', // Bank Account - Cheques
                'credit_account' => self::UNDEPOSITED_FUNDS,
            ],
            'other' => [
                'debit_account' => '1240', // Other Payment Methods
                'credit_account' => self::ACCOUNTS_RECEIVABLE,
            ],
        ];
    }

    /**
     * Calculate ledger balance for payment reconciliation.
     */
    public function calculatePaymentLedgerBalance(string $paymentId): array
    {
        // Get journal entries linked to this payment
        $journalEntryIds = JournalEntrySource::where('source_type', 'payment')
            ->where('source_id', $paymentId)
            ->pluck('journal_entry_id');

        if ($journalEntryIds->isEmpty()) {
            return [
                'payment_id' => $paymentId,
                'account_balances' => [],
                'total_debits' => 0,
                'total_credits' => 0,
                'is_balanced' => true,
            ];
        }

        $transactions = DB::table('journal_transactions')
            ->whereIn('journal_entry_id', $journalEntryIds)
            ->join('accounts', 'journal_transactions.account_id', '=', 'accounts.id')
            ->selectRaw('
                accounts.code as account_code,
                accounts.name as account_name,
                SUM(CASE WHEN journal_transactions.debit_credit = \'debit\' THEN journal_transactions.amount ELSE 0 END) as total_debits,
                SUM(CASE WHEN journal_transactions.debit_credit = \'credit\' THEN journal_transactions.amount ELSE 0 END) as total_credits,
                SUM(CASE WHEN journal_transactions.debit_credit = \'debit\' THEN journal_transactions.amount ELSE 0 END) - 
                SUM(CASE WHEN journal_transactions.debit_credit = \'credit\' THEN journal_transactions.amount ELSE 0 END) as net_balance
            ')
            ->groupBy('accounts.code', 'accounts.name')
            ->orderBy('accounts.code')
            ->get();

        return [
            'payment_id' => $paymentId,
            'account_balances' => $transactions->keyBy('account_code')->toArray(),
            'total_debits' => $transactions->sum('total_debits'),
            'total_credits' => $transactions->sum('total_credits'),
            'is_balanced' => abs($transactions->sum('total_debits') - $transactions->sum('total_credits')) < 0.01,
        ];
    }

    /**
     * Get payment reconciliation summary from ledger entries.
     */
    public function getPaymentReconciliationSummary(string $paymentId): array
    {
        $ledgerBalance = $this->calculatePaymentLedgerBalance($paymentId);

        // Get associated journal entries with details
        $journalEntryIds = JournalEntrySource::where('source_type', 'payment')
            ->where('source_id', $paymentId)
            ->pluck('journal_entry_id');

        $entries = JournalEntry::with(['transactions.account', 'sources'])
            ->whereIn('id', $journalEntryIds)
            ->orderBy('date')
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'type' => $entry->type,
                    'status' => $entry->status,
                    'description' => $entry->description,
                    'date' => $entry->date->format('Y-m-d'),
                    'reference' => $entry->reference,
                    'transactions' => $entry->transactions->map(function ($transaction) {
                        return [
                            'account_code' => $transaction->account->code,
                            'account_name' => $transaction->account->name,
                            'debit_credit' => $transaction->debit_credit,
                            'amount' => $transaction->amount,
                        ];
                    })->toArray(),
                    'sources' => $entry->sources->map(function ($source) {
                        return [
                            'source_type' => $source->source_type,
                            'source_id' => $source->source_id,
                            'source_data' => $source->source_data,
                        ];
                    })->toArray(),
                ];
            })
            ->toArray();

        return [
            'payment_id' => $paymentId,
            'ledger_balance' => $ledgerBalance,
            'journal_entries' => $entries,
            'reconciliation_status' => $ledgerBalance['is_balanced'] ? 'balanced' : 'unbalanced',
            'reconciliation_date' => now()->toDateString(),
        ];
    }
}
