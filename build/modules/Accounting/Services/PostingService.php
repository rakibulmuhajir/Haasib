<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\VendorCredit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PostingService
{
    public function __construct(
        private readonly PostingTemplateInstaller $templateInstaller,
        private readonly PostingTemplateValidator $templateValidator,
    ) {}

    public function postInvoice(Invoice $invoice): Transaction
    {
        $invoice->loadMissing(['customer', 'lineItems', 'company']);

        $company = $invoice->company;
        if (! $company instanceof Company) {
            throw new \RuntimeException('Invoice company missing.');
        }

        $this->templateInstaller->ensureDefaults($company);
        $template = $this->resolveTemplate($company->id, 'AR_INVOICE', $invoice->invoice_date ?? now());

        $transactionDate = $invoice->invoice_date ?? now();
        $period = $this->resolveOpenPeriod($company->id, $transactionDate);

        $entries = $this->buildInvoiceEntries($template, $invoice, $company);

        return $this->createTransaction([
            'company_id' => $company->id,
            'transaction_number' => $invoice->invoice_number,
            'transaction_type' => 'invoice',
            'transaction_date' => $transactionDate,
            'posting_date' => $transactionDate,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period_id' => $period->id,
            'currency' => $invoice->currency,
            'base_currency' => $invoice->base_currency ?? $invoice->currency,
            'exchange_rate' => $invoice->exchange_rate,
            'reference_type' => 'acct.invoices',
            'reference_id' => $invoice->id,
            'description' => $invoice->notes ?? "Invoice {$invoice->invoice_number}",
        ], $entries);
    }

    /**
     * Preview + compute invoice posting entries for a specific template (no persistence).
     *
     * @return array<int, array{account_id:string,type:'debit'|'credit',amount:float,description?:string}>
     */
    public function previewInvoice(PostingTemplate $template, Invoice $invoice): array
    {
        $invoice->loadMissing(['customer', 'lineItems', 'company']);
        $company = $invoice->company;
        if (! $company instanceof Company) {
            throw new \RuntimeException('Invoice company missing.');
        }

        return $this->buildInvoiceEntries($template, $invoice, $company);
    }

    public function postPayment(Payment $payment, string $depositAccountId, string $arAccountId): Transaction
    {
        $payment->loadMissing(['paymentAllocations', 'customer', 'company']);

        $company = $payment->company;
        if (! $company instanceof Company) {
            throw new \RuntimeException('Payment company missing.');
        }

        $this->templateInstaller->ensureDefaults($company);
        $this->resolveTemplate($company->id, 'AR_PAYMENT', $payment->payment_date ?? now());

        $transactionDate = $payment->payment_date ?? now();
        $period = $this->resolveOpenPeriod($company->id, $transactionDate);

        $allocated = $payment->paymentAllocations ? (float) $payment->paymentAllocations->sum('amount_allocated') : (float) $payment->amount;
        $amount = round((float) $payment->amount, 2);
        if (abs($amount - round($allocated, 2)) >= 0.01) {
            throw new \RuntimeException('Payment allocations must equal payment amount to post to GL.');
        }

        return $this->createTransaction([
            'company_id' => $company->id,
            'transaction_number' => $payment->payment_number,
            'transaction_type' => 'payment',
            'transaction_date' => $transactionDate,
            'posting_date' => $transactionDate,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period_id' => $period->id,
            'currency' => $payment->currency,
            'base_currency' => $payment->base_currency ?? $payment->currency,
            'exchange_rate' => $payment->exchange_rate,
            'reference_type' => 'acct.payments',
            'reference_id' => $payment->id,
            'description' => $payment->notes ?? "Payment {$payment->payment_number}",
        ], [
            [
                'account_id' => $depositAccountId,
                'type' => 'debit',
                'amount' => $amount,
                'description' => 'Deposit',
            ],
            [
                'account_id' => $arAccountId,
                'type' => 'credit',
                'amount' => $amount,
                'description' => 'Accounts Receivable',
            ],
        ]);
    }

    public function postBill(Bill $bill): Transaction
    {
        $bill->loadMissing(['vendor', 'lineItems', 'company']);

        $company = $bill->company;
        if (! $company instanceof Company) {
            throw new \RuntimeException('Bill company missing.');
        }

        $this->templateInstaller->ensureDefaults($company);
        $template = $this->resolveTemplate($company->id, 'AP_BILL', $bill->bill_date ?? now());

        $transactionDate = $bill->bill_date ?? now();
        $period = $this->resolveOpenPeriod($company->id, $transactionDate);

        $entries = $this->buildBillEntries($template, $bill, $company);

        return $this->createTransaction([
            'company_id' => $company->id,
            'transaction_number' => $bill->bill_number,
            'transaction_type' => 'bill',
            'transaction_date' => $transactionDate,
            'posting_date' => $transactionDate,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period_id' => $period->id,
            'currency' => $bill->currency,
            'base_currency' => $bill->base_currency ?? $bill->currency,
            'exchange_rate' => $bill->exchange_rate,
            'reference_type' => 'acct.bills',
            'reference_id' => $bill->id,
            'description' => $bill->notes ?? "Bill {$bill->bill_number}",
        ], $entries);
    }

    /**
     * Preview + compute bill posting entries for a specific template (no persistence).
     *
     * @return array<int, array{account_id:string,type:'debit'|'credit',amount:float,description?:string}>
     */
    public function previewBill(PostingTemplate $template, Bill $bill): array
    {
        $bill->loadMissing(['vendor', 'lineItems', 'company']);
        $company = $bill->company;
        if (! $company instanceof Company) {
            throw new \RuntimeException('Bill company missing.');
        }

        return $this->buildBillEntries($template, $bill, $company);
    }

    public function postBillPayment(BillPayment $payment, string $paymentAccountId, string $apAccountId): Transaction
    {
        $payment->loadMissing(['allocations', 'company']);

        $company = $payment->company;
        if (! $company instanceof Company) {
            throw new \RuntimeException('Bill payment company missing.');
        }

        $this->templateInstaller->ensureDefaults($company);
        $this->resolveTemplate($company->id, 'AP_PAYMENT', $payment->payment_date ?? now());

        $transactionDate = $payment->payment_date ?? now();
        $period = $this->resolveOpenPeriod($company->id, $transactionDate);

        $allocated = $payment->allocations ? (float) $payment->allocations->sum('amount_allocated') : (float) $payment->amount;
        $amount = round((float) $payment->amount, 2);
        if (abs($amount - round($allocated, 2)) >= 0.01) {
            throw new \RuntimeException('Bill payment allocations must equal payment amount to post to GL.');
        }

        return $this->createTransaction([
            'company_id' => $company->id,
            'transaction_number' => $payment->payment_number,
            'transaction_type' => 'bill_payment',
            'transaction_date' => $transactionDate,
            'posting_date' => $transactionDate,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period_id' => $period->id,
            'currency' => $payment->currency,
            'base_currency' => $payment->base_currency ?? $payment->currency,
            'exchange_rate' => $payment->exchange_rate,
            'reference_type' => 'acct.bill_payments',
            'reference_id' => $payment->id,
            'description' => $payment->notes ?? "Bill payment {$payment->payment_number}",
        ], [
            [
                'account_id' => $apAccountId,
                'type' => 'debit',
                'amount' => $amount,
                'description' => 'Accounts Payable',
            ],
            [
                'account_id' => $paymentAccountId,
                'type' => 'credit',
                'amount' => $amount,
                'description' => 'Cash/Bank',
            ],
        ]);
    }

    public function postCreditNote(CreditNote $creditNote): Transaction
    {
        $creditNote->loadMissing(['customer', 'items', 'company']);

        $company = $creditNote->company;
        if (! $company instanceof Company) {
            throw new \RuntimeException('Credit note company missing.');
        }

        $this->templateInstaller->ensureDefaults($company);
        $template = $this->resolveTemplate($company->id, 'AR_CREDIT_NOTE', $creditNote->credit_date ?? now());
        $roleAccounts = $this->roleAccounts($template);

        $arAccountId = $creditNote->customer?->ar_account_id ?? $roleAccounts['AR'] ?? null;
        if (! $arAccountId) {
            throw new \RuntimeException('AR account is required to post credit note.');
        }

        $revenueAccountId = $roleAccounts['REVENUE'] ?? null;
        if (! $revenueAccountId) {
            throw new \RuntimeException('Revenue account is required to post credit note.');
        }

        $transactionDate = $creditNote->credit_date ?? now();
        $period = $this->resolveOpenPeriod($company->id, $transactionDate);

        $taxAmount = $creditNote->items ? round((float) $creditNote->items->sum('tax_amount'), 2) : 0.0;
        $total = round((float) $creditNote->amount, 2);
        $revenueAmount = round($total - $taxAmount, 2);

        $requiredWhenPresent = [];
        if ($taxAmount > 0) $requiredWhenPresent[] = 'TAX_PAYABLE';
        $this->templateValidator->validateForPosting($template, $roleAccounts, $requiredWhenPresent);

        $entries = [
            [
                'account_id' => $revenueAccountId,
                'type' => 'debit',
                'amount' => $revenueAmount,
                'description' => 'Credit note (revenue reversal)',
            ],
            [
                'account_id' => $arAccountId,
                'type' => 'credit',
                'amount' => $total,
                'description' => 'Accounts Receivable',
            ],
        ];

        if ($taxAmount > 0) {
            $entries[] = [
                'account_id' => $roleAccounts['TAX_PAYABLE'],
                'type' => 'debit',
                'amount' => $taxAmount,
                'description' => 'Tax payable (reversal)',
            ];
        }

        return $this->createTransaction([
            'company_id' => $company->id,
            'transaction_number' => $creditNote->credit_note_number,
            'transaction_type' => 'credit_note',
            'transaction_date' => $transactionDate,
            'posting_date' => $transactionDate,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period_id' => $period->id,
            'currency' => $company->base_currency,
            'base_currency' => $company->base_currency,
            'exchange_rate' => null,
            'reference_type' => 'acct.credit_notes',
            'reference_id' => $creditNote->id,
            'description' => $creditNote->notes ?? "Credit note {$creditNote->credit_note_number}",
        ], $entries);
    }

    public function postVendorCredit(VendorCredit $credit): Transaction
    {
        $credit->loadMissing(['vendor', 'items', 'company']);

        $company = $credit->company;
        if (! $company instanceof Company) {
            throw new \RuntimeException('Vendor credit company missing.');
        }

        $this->templateInstaller->ensureDefaults($company);
        $template = $this->resolveTemplate($company->id, 'AP_VENDOR_CREDIT', $credit->credit_date ?? now());
        $roleAccounts = $this->roleAccounts($template);

        $apAccountId = $credit->vendor?->ap_account_id ?? $roleAccounts['AP'] ?? null;
        if (! $apAccountId) {
            throw new \RuntimeException('AP account is required to post vendor credit.');
        }

        $expenseAccountId = $roleAccounts['EXPENSE'] ?? null;
        if (! $expenseAccountId) {
            throw new \RuntimeException('Expense account is required to post vendor credit.');
        }

        $transactionDate = $credit->credit_date ?? now();
        $period = $this->resolveOpenPeriod($company->id, $transactionDate);

        $taxAmount = $credit->items ? round((float) $credit->items->sum('tax_amount'), 2) : 0.0;
        $total = round((float) $credit->amount, 2);
        $expenseAmount = round($total - $taxAmount, 2);

        $requiredWhenPresent = [];
        if ($taxAmount > 0) $requiredWhenPresent[] = 'TAX_RECEIVABLE';
        $this->templateValidator->validateForPosting($template, $roleAccounts, $requiredWhenPresent);

        $entries = [
            [
                'account_id' => $apAccountId,
                'type' => 'debit',
                'amount' => $total,
                'description' => 'Accounts Payable',
            ],
            [
                'account_id' => $expenseAccountId,
                'type' => 'credit',
                'amount' => $expenseAmount,
                'description' => 'Vendor credit (expense reversal)',
            ],
        ];

        if ($taxAmount > 0) {
            $entries[] = [
                'account_id' => $roleAccounts['TAX_RECEIVABLE'],
                'type' => 'credit',
                'amount' => $taxAmount,
                'description' => 'Tax receivable (reversal)',
            ];
        }

        return $this->createTransaction([
            'company_id' => $company->id,
            'transaction_number' => $credit->credit_number,
            'transaction_type' => 'vendor_credit',
            'transaction_date' => $transactionDate,
            'posting_date' => $transactionDate,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period_id' => $period->id,
            'currency' => $credit->currency,
            'base_currency' => $credit->base_currency ?? $credit->currency,
            'exchange_rate' => $credit->exchange_rate,
            'reference_type' => 'acct.vendor_credits',
            'reference_id' => $credit->id,
            'description' => $credit->reason ?? "Vendor credit {$credit->credit_number}",
        ], $entries);
    }

    /**
     * Create a reversing transaction that nets the original transaction to zero.
     * Idempotent: if already reversed, returns the existing reversal.
     */
    public function reverseTransaction(Transaction $original, ?string $reason = null, Carbon|string|null $date = null): Transaction
    {
        $original->loadMissing(['journalEntries']);

        return DB::transaction(function () use ($original, $reason, $date) {
            if ($original->reversed_by_id) {
                $existing = Transaction::where('company_id', $original->company_id)
                    ->where('id', $original->reversed_by_id)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $existing = Transaction::where('company_id', $original->company_id)
                ->where('reversal_of_id', $original->id)
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->first();

            if ($existing) {
                if (! $original->reversed_by_id) {
                    $original->reversed_by_id = $existing->id;
                    $original->save();
                }
                return $existing;
            }

            $journalEntries = $original->journalEntries;
            if (! $journalEntries || $journalEntries->isEmpty()) {
                throw new \RuntimeException('Cannot reverse a transaction without journal entries.');
            }

            $hasCurrencyAmounts = $journalEntries->contains(function ($journalEntry) {
                return $journalEntry->currency_debit !== null || $journalEntry->currency_credit !== null;
            });

            $transactionDate = $date ? ($date instanceof Carbon ? $date : Carbon::parse($date)) : now();
            $period = $this->resolveOpenPeriod($original->company_id, $transactionDate);

            $reversalNumber = $this->generateReversalNumber($original->company_id, $original->transaction_number);
            $reversalDescription = trim('Reversal of ' . $original->transaction_number . ($reason ? " — {$reason}" : ''));

            $entries = [];
            foreach ($journalEntries->sortBy('line_number')->values() as $journalEntry) {
                $debit = round((float) $journalEntry->debit_amount, 2);
                $credit = round((float) $journalEntry->credit_amount, 2);

                if ($debit > 0 && $credit > 0) {
                    throw new \RuntimeException('Invalid journal entry: both debit and credit are populated.');
                }
                if ($debit <= 0 && $credit <= 0) {
                    continue;
                }

                $currencyDebit = $journalEntry->currency_debit !== null ? (float) $journalEntry->currency_debit : null;
                $currencyCredit = $journalEntry->currency_credit !== null ? (float) $journalEntry->currency_credit : null;

                $amount = $debit > 0
                    ? ($hasCurrencyAmounts ? ($currencyDebit ?? (float) $debit) : (float) $debit)
                    : ($hasCurrencyAmounts ? ($currencyCredit ?? (float) $credit) : (float) $credit);

                $entries[] = [
                    'account_id' => $journalEntry->account_id,
                    'type' => $debit > 0 ? 'credit' : 'debit',
                    'amount' => $amount,
                    'description' => $journalEntry->description,
                ];
            }

            if (empty($entries)) {
                throw new \RuntimeException('Cannot reverse a transaction with zero-value journal entries.');
            }

            $reversal = $this->createTransaction([
                'company_id' => $original->company_id,
                'transaction_number' => $reversalNumber,
                'transaction_type' => $original->transaction_type,
                'transaction_date' => $transactionDate,
                'posting_date' => $transactionDate,
                'fiscal_year_id' => $period->fiscal_year_id,
                'period_id' => $period->id,
                'currency' => $original->currency,
                'base_currency' => $original->base_currency,
                'exchange_rate' => $original->exchange_rate,
                'reference_type' => $original->reference_type,
                'reference_id' => $original->reference_id,
                'description' => $reversalDescription,
                'reversal_of_id' => $original->id,
            ], $entries);

            $original->reversed_by_id = $reversal->id;
            $original->voided_at = $original->voided_at ?? now();
            $original->voided_by_user_id = $original->voided_by_user_id ?? Auth::id();
            if ($reason) {
                $original->void_reason = $original->void_reason ?: Str::limit($reason, 255);
            }
            $original->save();

            return $reversal;
        });
    }

    private function resolveTemplate(string $companyId, string $docType, Carbon|string $date): PostingTemplate
    {
        $dateObj = $date instanceof Carbon ? $date : Carbon::parse($date);

        $template = PostingTemplate::where('company_id', $companyId)
            ->where('doc_type', $docType)
            ->where('is_active', true)
            ->where('is_default', true)
            ->whereDate('effective_from', '<=', $dateObj->toDateString())
            ->where(function ($q) use ($dateObj) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $dateObj->toDateString());
            })
            ->whereNull('deleted_at')
            ->with(['lines'])
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first();

        if (! $template) {
            throw new \RuntimeException("No active default posting template for {$docType}. Configure posting templates for this company.");
        }

        return $template;
    }

    /**
     * @return array<int, array{account_id:string,type:'debit'|'credit',amount:float,description?:string}>
     */
    private function buildInvoiceEntries(PostingTemplate $template, Invoice $invoice, Company $company): array
    {
        $invoice->loadMissing(['customer', 'lineItems']);

        $roleAccounts = $this->roleAccounts($template);
        $arAccountId = $invoice->customer?->ar_account_id ?? $roleAccounts['AR'] ?? null;
        if (! $arAccountId) {
            throw new \RuntimeException('AR account is required to post invoice.');
        }

        if ($invoice->lineItems->isEmpty()) {
            throw new \RuntimeException('Cannot post invoice without line items.');
        }

        $taxAmount = round((float) $invoice->tax_amount, 2);
        $discountAmount = round((float) $invoice->discount_amount, 2);

        $requiredWhenPresent = [];
        if ($taxAmount > 0) $requiredWhenPresent[] = 'TAX_PAYABLE';
        if ($discountAmount > 0) $requiredWhenPresent[] = 'DISCOUNT_GIVEN';
        $this->templateValidator->validateForPosting($template, $roleAccounts, $requiredWhenPresent);

        // Revenue credits grouped by account_id
        $revenueCredits = [];
        foreach ($invoice->lineItems as $line) {
            $accountId = $line->income_account_id ?: ($roleAccounts['REVENUE'] ?? null);
            if (! $accountId) {
                $lineNumber = $line->line_number ?? null;
                throw new \RuntimeException('Revenue account is required' . ($lineNumber ? " on invoice line {$lineNumber}." : '.'));
            }

            $lineTotal = round((float) $line->line_total, 2);
            $revenueCredits[$accountId] = round(($revenueCredits[$accountId] ?? 0) + $lineTotal, 2);
        }

        $entries = [];
        foreach ($revenueCredits as $accountId => $amount) {
            $entries[] = [
                'account_id' => $accountId,
                'type' => 'credit',
                'amount' => $amount,
                'description' => 'Revenue',
            ];
        }

        if ($taxAmount > 0) {
            $entries[] = [
                'account_id' => $roleAccounts['TAX_PAYABLE'],
                'type' => 'credit',
                'amount' => $taxAmount,
                'description' => 'Tax payable',
            ];
        }

        if ($discountAmount > 0) {
            $entries[] = [
                'account_id' => $roleAccounts['DISCOUNT_GIVEN'],
                'type' => 'debit',
                'amount' => $discountAmount,
                'description' => 'Discount given',
            ];
        }

        $arDebit = round((float) $invoice->total_amount, 2);
        $entries[] = [
            'account_id' => $arAccountId,
            'type' => 'debit',
            'amount' => $arDebit,
            'description' => 'Accounts Receivable',
        ];

        $this->assertBalanced($entries);
        return $entries;
    }

    /**
     * @return array<int, array{account_id:string,type:'debit'|'credit',amount:float,description?:string}>
     */
    private function buildBillEntries(PostingTemplate $template, Bill $bill, Company $company): array
    {
        $bill->loadMissing(['vendor', 'lineItems']);

        $roleAccounts = $this->roleAccounts($template);
        $apAccountId = $bill->vendor?->ap_account_id ?? $roleAccounts['AP'] ?? null;
        if (! $apAccountId) {
            throw new \RuntimeException('AP account is required to post bill.');
        }

        if ($bill->lineItems->isEmpty()) {
            throw new \RuntimeException('Cannot post bill without line items.');
        }

        $taxAmount = round((float) $bill->tax_amount, 2);
        $discountAmount = round((float) $bill->discount_amount, 2);

        $requiredWhenPresent = [];
        if ($taxAmount > 0) $requiredWhenPresent[] = 'TAX_RECEIVABLE';
        if ($discountAmount > 0) $requiredWhenPresent[] = 'DISCOUNT_RECEIVED';
        $this->templateValidator->validateForPosting($template, $roleAccounts, $requiredWhenPresent);

        $expenseDebits = [];
        foreach ($bill->lineItems as $line) {
            $accountId = $line->expense_account_id ?: ($roleAccounts['EXPENSE'] ?? null);
            if (! $accountId) {
                $lineNumber = $line->line_number ?? null;
                throw new \RuntimeException('Expense account is required' . ($lineNumber ? " on bill line {$lineNumber}." : '.'));
            }

            $lineTotal = round((float) $line->line_total, 2);
            $expenseDebits[$accountId] = round(($expenseDebits[$accountId] ?? 0) + $lineTotal, 2);
        }

        $entries = [];
        foreach ($expenseDebits as $accountId => $amount) {
            $entries[] = [
                'account_id' => $accountId,
                'type' => 'debit',
                'amount' => $amount,
                'description' => 'Expense',
            ];
        }

        if ($taxAmount > 0) {
            $entries[] = [
                'account_id' => $roleAccounts['TAX_RECEIVABLE'],
                'type' => 'debit',
                'amount' => $taxAmount,
                'description' => 'Tax receivable',
            ];
        }

        if ($discountAmount > 0) {
            $entries[] = [
                'account_id' => $roleAccounts['DISCOUNT_RECEIVED'],
                'type' => 'credit',
                'amount' => $discountAmount,
                'description' => 'Discount received',
            ];
        }

        $apCredit = round((float) $bill->total_amount, 2);
        $entries[] = [
            'account_id' => $apAccountId,
            'type' => 'credit',
            'amount' => $apCredit,
            'description' => 'Accounts Payable',
        ];

        $this->assertBalanced($entries);
        return $entries;
    }

    /**
     * @param array<int, array{type:'debit'|'credit',amount:float}> $entries
     */
    private function assertBalanced(array $entries): void
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($entries as $entry) {
            if ($entry['type'] === 'debit') $debit += (float) $entry['amount'];
            if ($entry['type'] === 'credit') $credit += (float) $entry['amount'];
        }
        if (abs(round($debit, 2) - round($credit, 2)) >= 0.01) {
            throw new \RuntimeException('Posting preview out of balance; check template mappings and document totals.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function roleAccounts(PostingTemplate $template): array
    {
        $map = [];
        foreach ($template->lines as $line) {
            $map[$line->role] = $line->account_id;
        }
        return $map;
    }

    /**
     * @param array<int, array{account_id:string,type:'debit'|'credit',amount:float,description?:string}> $entries
     */
    private function createTransaction(array $data, array $entries): Transaction
    {
        $currency = $data['currency'];
        $baseCurrency = $data['base_currency'] ?? $currency;
        $exchangeRate = $data['exchange_rate'] ?? null;

        if ($currency !== $baseCurrency && $exchangeRate === null) {
            throw new \RuntimeException('exchange_rate is required when currency differs from base_currency.');
        }

        $isForeign = $currency !== $baseCurrency;

        $computed = [];
        $debitTotal = 0.0;
        $creditTotal = 0.0;

        foreach (array_values($entries) as $index => $entry) {
            $currencyAmount = (float) $entry['amount'];
            $baseAmount = round($currencyAmount * ($exchangeRate ?? 1), 2);

            $computed[$index] = [
                ...$entry,
                'currency_amount' => $currencyAmount,
                'base_amount' => $baseAmount,
            ];

            if ($entry['type'] === 'debit') {
                $debitTotal += $baseAmount;
            } else {
                $creditTotal += $baseAmount;
            }
        }

        $diff = round($debitTotal - $creditTotal, 2);
        if ($diff !== 0.0) {
            if (abs($diff) > 0.01) {
                throw new \RuntimeException('Transaction not balanced in base currency; debits must equal credits.');
            }

            // FX rounding adjustment: bump the last line on the smaller side to keep the journal balanced in base.
            if ($diff > 0) {
                $this->applyBaseAdjustment($computed, 'credit', $diff);
                $creditTotal += $diff;
            } else {
                $amount = abs($diff);
                $this->applyBaseAdjustment($computed, 'debit', $amount);
                $debitTotal += $amount;
            }
        }

        return DB::transaction(function () use ($data, $entries, $debitTotal, $creditTotal) {
            $transaction = Transaction::create([
                'company_id' => $data['company_id'],
                'transaction_number' => $data['transaction_number'],
                'transaction_type' => $data['transaction_type'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'posting_date' => $data['posting_date'] ?? $data['transaction_date'],
                'fiscal_year_id' => $data['fiscal_year_id'],
                'period_id' => $data['period_id'],
                'description' => $data['description'] ?? null,
                'currency' => $data['currency'],
                'base_currency' => $data['base_currency'] ?? $data['currency'],
                'exchange_rate' => $data['exchange_rate'] ?? null,
                'total_debit' => $debitTotal,
                'total_credit' => $creditTotal,
                'status' => 'posted',
                'reversal_of_id' => $data['reversal_of_id'] ?? null,
                'posted_at' => now(),
                'posted_by_user_id' => Auth::id(),
                'created_by_user_id' => Auth::id(),
            ]);

            $currency = $data['currency'];
            $baseCurrency = $data['base_currency'] ?? $currency;
            $exchangeRate = $data['exchange_rate'] ?? null;
            $isForeign = $currency !== $baseCurrency;

            $computed = [];
            foreach (array_values($entries) as $idx => $entry) {
                $currencyAmount = (float) $entry['amount'];
                $computed[$idx] = [
                    ...$entry,
                    'currency_amount' => $currencyAmount,
                    'base_amount' => round($currencyAmount * ($exchangeRate ?? 1), 2),
                ];
            }
            $diff = round(array_sum(array_map(fn ($e) => $e['type'] === 'debit' ? $e['base_amount'] : 0.0, $computed))
                - array_sum(array_map(fn ($e) => $e['type'] === 'credit' ? $e['base_amount'] : 0.0, $computed)), 2);
            if ($diff !== 0.0 && abs($diff) <= 0.01) {
                if ($diff > 0) {
                    $this->applyBaseAdjustment($computed, 'credit', $diff);
                } else {
                    $this->applyBaseAdjustment($computed, 'debit', abs($diff));
                }
            }

            foreach ($computed as $index => $entry) {
                JournalEntry::create([
                    'company_id' => $data['company_id'],
                    'transaction_id' => $transaction->id,
                    'account_id' => $entry['account_id'],
                    'line_number' => $index + 1,
                    'description' => $entry['description'] ?? null,
                    'debit_amount' => $entry['type'] === 'debit' ? $entry['base_amount'] : 0,
                    'credit_amount' => $entry['type'] === 'credit' ? $entry['base_amount'] : 0,
                    'currency_debit' => ($isForeign && $entry['type'] === 'debit') ? $entry['currency_amount'] : null,
                    'currency_credit' => ($isForeign && $entry['type'] === 'credit') ? $entry['currency_amount'] : null,
                    'exchange_rate' => $data['exchange_rate'] ?? null,
                    'reference_type' => $data['reference_type'] ?? null,
                    'reference_id' => $data['reference_id'] ?? null,
                    'dimension_1' => null,
                    'dimension_2' => null,
                    'dimension_3' => null,
                ]);
            }

            return $transaction;
        });
    }

    /**
     * @param array<int, array{type:'debit'|'credit',base_amount:float}> $entries
     */
    private function applyBaseAdjustment(array &$entries, string $side, float $amount): void
    {
        for ($i = count($entries) - 1; $i >= 0; $i--) {
            if ($entries[$i]['type'] !== $side) {
                continue;
            }

            $new = round(((float) $entries[$i]['base_amount']) + $amount, 2);
            if ($new <= 0.0) {
                continue;
            }

            $entries[$i]['base_amount'] = $new;
            return;
        }

        throw new \RuntimeException('Unable to apply FX rounding adjustment; no suitable journal line found.');
    }

    private function generateReversalNumber(string $companyId, string $originalNumber): string
    {
        $baseSuffix = '-REV';

        for ($i = 0; $i < 25; $i++) {
            $suffix = $i === 0 ? $baseSuffix : ($baseSuffix . ($i + 1));
            $maxBaseLen = 50 - strlen($suffix);
            $base = (string) Str::of($originalNumber)->substr(0, max(0, $maxBaseLen));
            $base = rtrim($base, '-');
            if ($base === '') {
                $base = 'TXN';
            }

            $candidate = $base . $suffix;

            $exists = Transaction::where('company_id', $companyId)
                ->where('transaction_number', $candidate)
                ->whereNull('deleted_at')
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        return (string) Str::uuid();
    }

    private function resolveOpenPeriod(string $companyId, Carbon|string $date): AccountingPeriod
    {
        $dateObj = $date instanceof Carbon ? $date : Carbon::parse($date);

        $period = AccountingPeriod::join('acct.fiscal_years', 'acct.accounting_periods.fiscal_year_id', '=', 'acct.fiscal_years.id')
            ->where('acct.accounting_periods.company_id', $companyId)
            ->where('acct.accounting_periods.start_date', '<=', $dateObj->toDateString())
            ->where('acct.accounting_periods.end_date', '>=', $dateObj->toDateString())
            ->where('acct.accounting_periods.is_closed', false)
            ->where('acct.fiscal_years.is_closed', false)
            ->select('acct.accounting_periods.*')
            ->first();

            if (! $period) {
                $fiscalYearService = app(FiscalYearService::class);
                $company = Company::find($companyId);

            if ($company && $company->getAutoCreateFiscalYear()) {
                $fiscalYearService->ensureCurrentFiscalYearExists($companyId, $dateObj);
                $period = AccountingPeriod::join('acct.fiscal_years', 'acct.accounting_periods.fiscal_year_id', '=', 'acct.fiscal_years.id')
                    ->where('acct.accounting_periods.company_id', $companyId)
                    ->where('acct.accounting_periods.start_date', '<=', $dateObj->toDateString())
                    ->where('acct.accounting_periods.end_date', '>=', $dateObj->toDateString())
                    ->where('acct.accounting_periods.is_closed', false)
                    ->where('acct.fiscal_years.is_closed', false)
                    ->select('acct.accounting_periods.*')
                    ->first();
            }

            if (! $period) {
                $message = "No open accounting period for {$dateObj->toDateString()}. Please ensure a fiscal year and accounting periods are set up.";
                Log::warning($message, [
                    'company_id' => $companyId,
                    'date' => $dateObj->toDateString(),
                    'auto_create_fiscal_year' => $company?->getAutoCreateFiscalYear(),
                ]);
                throw new \RuntimeException($message);
            }
        }

        return $period;
    }
}
