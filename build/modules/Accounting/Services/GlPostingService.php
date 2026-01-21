<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Accounting\Models\CreditNote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class GlPostingService
{
    /**
     * Post a generic balanced transaction to the GL (used by non-accounting modules).
     *
     * @param array{
     *   company_id:string,
     *   transaction_number?:string,
     *   transaction_type:string,
     *   date:\Illuminate\Support\Carbon|string,
     *   currency:string,
     *   base_currency?:string,
     *   exchange_rate?:float|null,
     *   description?:string|null,
     *   reference_type?:string|null,
     *   reference_id?:string|null,
     *   metadata?:array<string,mixed>|null,
     * } $headerData
     * @param array<int, array{account_id:string,type:'debit'|'credit',amount:float,description?:string}> $entries
     */
    public function postBalancedTransaction(array $headerData, array $entries): Transaction
    {
        $companyId = $headerData['company_id'];
        $transactionDate = $headerData['date'] ?? now();
        $period = $this->resolveOpenPeriod($companyId, $transactionDate);

        $currency = strtoupper((string) $headerData['currency']);
        $baseCurrency = strtoupper((string) ($headerData['base_currency'] ?? $currency));

        $transactionNumber = $headerData['transaction_number'] ?? Transaction::generateJournalNumber($companyId);

        // Defensive: ensure all accounts belong to the same company and are active.
        $accountIds = array_values(array_unique(array_map(fn ($e) => (string) $e['account_id'], $entries)));
        $validCount = Account::where('company_id', $companyId)
            ->whereIn('id', $accountIds)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->count();
        if ($validCount !== count($accountIds)) {
            throw new \RuntimeException('One or more GL accounts are invalid or inactive for this company.');
        }

        return $this->createTransaction([
            'company_id' => $companyId,
            'transaction_number' => $transactionNumber,
            'transaction_type' => $headerData['transaction_type'],
            'transaction_date' => $transactionDate,
            'posting_date' => $transactionDate,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period_id' => $period->id,
            'currency' => $currency,
            'base_currency' => $baseCurrency,
            'exchange_rate' => $headerData['exchange_rate'] ?? null,
            'description' => $headerData['description'] ?? null,
            'reference_type' => $headerData['reference_type'] ?? null,
            'reference_id' => $headerData['reference_id'] ?? null,
            'metadata' => $headerData['metadata'] ?? null,
            'reversal_of_id' => $headerData['reversal_of_id'] ?? null,
        ], $entries);
    }

    /**
     * Post an invoice to the general ledger.
     * Creates: DR AR, CR revenue per line (line total includes tax/discount for now).
     */
    public function postInvoice(Invoice $invoice): Transaction
    {
        return app(PostingService::class)->postInvoice($invoice);
    }

    /**
     * Post a payment to the general ledger.
     * Creates: DR Bank/Cash, CR AR.
     */
    public function postPayment(Payment $payment, string $depositAccountId, string $arAccountId): Transaction
    {
        return app(PostingService::class)->postPayment($payment, $depositAccountId, $arAccountId);
    }

    /**
     * Post a bill to the general ledger: DR expense lines, CR AP.
     */
    public function postBill(Bill $bill): Transaction
    {
        return app(PostingService::class)->postBill($bill);
    }

    /**
     * Post a bill payment: DR AP, CR payment account.
     */
    public function postBillPayment(BillPayment $payment, string $paymentAccountId, string $apAccountId): Transaction
    {
        return app(PostingService::class)->postBillPayment($payment, $paymentAccountId, $apAccountId);
    }

    /**
     * Post a vendor credit: DR AP, CR expense reversal.
     */
    public function postVendorCredit(VendorCredit $credit): Transaction
    {
        return app(PostingService::class)->postVendorCredit($credit);
    }

    /**
     * Post a credit note: DR revenue (reverse), CR AR.
     */
    public function postCreditNote(CreditNote $creditNote): Transaction
    {
        return app(PostingService::class)->postCreditNote($creditNote);
    }

    /**
     * Post a direct bank transaction (Spend Money / Receive Money) from the bank feed.
     * 
     * @param array $headerData ['company_id', 'date', 'currency', 'amount', 'description', 'bank_account_id']
     * @param array $lines [['account_id', 'amount', 'description', 'type']]
     */
    public function postBankTransaction(array $headerData, array $lines): Transaction
    {
        $companyId = $headerData['company_id'];
        $transactionDate = $headerData['date'] ?? now();
        $period = $this->resolveOpenPeriod($companyId, $transactionDate);

        // Validate Bank Account
        $bankAccount = Account::where('id', $headerData['bank_account_id'])
            ->where('company_id', $companyId)
            ->whereIn('subtype', ['bank', 'cash', 'credit_card'])
            ->firstOrFail();

        // Determine main bank entry type
        // If amount is negative (Spend Money) -> Bank Credit
        // If amount is positive (Receive Money) -> Bank Debit
        $amount = (float) $headerData['amount'];
        $isSpend = $amount < 0;
        $absAmount = abs($amount);

        $bankEntryType = $isSpend ? 'credit' : 'debit';
        $offsetEntryType = $isSpend ? 'debit' : 'credit';

        // Prepare GL Entries
        $glEntries = [];
        
        // 1. Bank Line
        $glEntries[] = [
            'account_id' => $bankAccount->id,
            'type' => $bankEntryType,
            'amount' => $absAmount,
            'description' => $headerData['description'] ?? 'Bank Transaction',
        ];

        // 2. Offset Lines (Expenses/Revenues)
        $allocatedTotal = 0.0;
        foreach ($lines as $line) {
            $lineAmount = (float) $line['amount'];
            $allocatedTotal += $lineAmount;
            
            $glEntries[] = [
                'account_id' => $line['account_id'],
                'type' => $offsetEntryType, // Opposite of bank entry
                'amount' => $lineAmount,
                'description' => $line['description'] ?? null,
            ];
        }

        // Validate Balance
        if (abs($absAmount - $allocatedTotal) > 0.01) {
            throw new \RuntimeException("Transaction out of balance: Bank Amount {$absAmount} vs Allocated {$allocatedTotal}");
        }

        // Create Transaction
        return $this->createTransaction([
            'company_id' => $companyId,
            'transaction_number' => Transaction::generateJournalNumber($companyId), // Or use bank ref
            'transaction_type' => $isSpend ? 'payment' : 'receipt', // Using generic types for now
            'transaction_date' => $transactionDate,
            'posting_date' => $transactionDate,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period_id' => $period->id,
            'currency' => $headerData['currency'],
            'base_currency' => $headerData['base_currency'] ?? $headerData['currency'], // Simplified for now
            'exchange_rate' => $headerData['exchange_rate'] ?? 1.0,
            'description' => $headerData['description'],
            'reference_type' => 'acct.bank_transactions', // Optional link back
            'reference_id' => $headerData['reference_id'] ?? null,
        ], $glEntries);
    }

    /**
     * Post a bank transfer between two accounts.
     */
    public function postBankTransfer(array $data): Transaction
    {
        $companyId = $data['company_id'];
        $transactionDate = $data['date'] ?? now();
        $period = $this->resolveOpenPeriod($companyId, $transactionDate);
        $amount = (float) $data['amount']; // Always positive

        $fromAccount = Account::findOrFail($data['from_account_id']);
        $toAccount = Account::findOrFail($data['to_account_id']);

        return $this->createTransaction([
            'company_id' => $companyId,
            'transaction_number' => Transaction::generateJournalNumber($companyId),
            'transaction_type' => 'transfer',
            'transaction_date' => $transactionDate,
            'posting_date' => $transactionDate,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period_id' => $period->id,
            'currency' => $data['currency'],
            'base_currency' => $data['currency'], // Simplified: assume same currency for transfer V1
            'description' => $data['description'] ?? "Transfer from {$fromAccount->name} to {$toAccount->name}",
            'reference_type' => 'acct.bank_transactions',
            'reference_id' => $data['reference_id'] ?? null,
        ], [
            [
                'account_id' => $fromAccount->id,
                'type' => 'credit',
                'amount' => $amount,
                'description' => "Transfer Out",
            ],
            [
                'account_id' => $toAccount->id,
                'type' => 'debit',
                'amount' => $amount,
                'description' => "Transfer In",
            ],
        ]);
    }

    /**
     * @param array<int, array{account_id:string,type:'debit'|'credit',amount:float,description?:string}> $entries
     */
    protected function createTransaction(array $data, array $entries): Transaction
    {
        $currency = $data['currency'];
        $baseCurrency = $data['base_currency'] ?? $currency;
        $exchangeRate = $data['exchange_rate'] ?? null;

        if ($currency !== $baseCurrency && $exchangeRate === null) {
            throw new \RuntimeException('exchange_rate is required when currency differs from base_currency.');
        }

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
                'metadata' => $data['metadata'] ?? null,
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
    protected function applyBaseAdjustment(array &$entries, string $side, float $amount): void
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

    protected function resolveOpenPeriod(string $companyId, Carbon|string $date): AccountingPeriod
    {
        $dateObj = $date instanceof Carbon ? $date : Carbon::parse($date);

        // First try to find an existing open period
        $period = AccountingPeriod::join('acct.fiscal_years', 'acct.accounting_periods.fiscal_year_id', '=', 'acct.fiscal_years.id')
            ->where('acct.accounting_periods.company_id', $companyId)
            ->where('acct.accounting_periods.start_date', '<=', $dateObj->toDateString())
            ->where('acct.accounting_periods.end_date', '>=', $dateObj->toDateString())
            ->where('acct.accounting_periods.is_closed', false)
            ->where('acct.fiscal_years.is_closed', false)
            ->select('acct.accounting_periods.*')
            ->first();

        if (!$period) {
            // Try to ensure a current fiscal year exists
            $fiscalYearService = app(FiscalYearService::class);
            $company = \App\Models\Company::find($companyId);

            if ($company && $company->getAutoCreateFiscalYear()) {
                $fiscalYear = $fiscalYearService->ensureCurrentFiscalYearExists($companyId, $dateObj);

                // Try again to find the period
                $period = AccountingPeriod::join('acct.fiscal_years', 'acct.accounting_periods.fiscal_year_id', '=', 'acct.fiscal_years.id')
                    ->where('acct.accounting_periods.company_id', $companyId)
                    ->where('acct.accounting_periods.start_date', '<=', $dateObj->toDateString())
                    ->where('acct.accounting_periods.end_date', '>=', $dateObj->toDateString())
                    ->where('acct.accounting_periods.is_closed', false)
                    ->where('acct.fiscal_years.is_closed', false)
                    ->select('acct.accounting_periods.*')
                    ->first();
            }

            if (!$period) {
                $message = "No open accounting period for {$dateObj->toDateString()}. Please ensure a fiscal year and accounting periods are set up.";
                Log::warning($message, [
                    'company_id' => $companyId,
                    'date' => $dateObj->toDateString(),
                    'auto_create_fiscal_year' => $company?->getAutoCreateFiscalYear()
                ]);
                throw new \RuntimeException($message);
            }
        }

        return $period;
    }

    protected function defaultRevenueAccountId(string $companyId): ?string
    {
        // First check company default
        $company = \App\Models\Company::find($companyId);
        if ($company && $company->income_account_id) {
            return $company->income_account_id;
        }

        // Fallback to first revenue account
        return Account::where('company_id', $companyId)
            ->where('type', 'revenue')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->value('id');
    }

    protected function defaultExpenseAccountId(string $companyId): ?string
    {
        // First check company default
        $company = \App\Models\Company::find($companyId);
        if ($company && $company->expense_account_id) {
            return $company->expense_account_id;
        }

        // Fallback to first expense account
        return Account::where('company_id', $companyId)
            ->whereIn('type', ['expense', 'cogs', 'asset'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->value('id');
    }

    protected function defaultApAccountId(string $companyId): ?string
    {
        // First check company default
        $company = \App\Models\Company::find($companyId);
        if ($company && $company->ap_account_id) {
            return $company->ap_account_id;
        }

        // Fallback to first AP account
        return Account::where('company_id', $companyId)
            ->where('subtype', 'accounts_payable')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->value('id');
    }

    protected function defaultArAccountId(string $companyId): ?string
    {
        // First check company default
        $company = \App\Models\Company::find($companyId);
        if ($company && $company->ar_account_id) {
            return $company->ar_account_id;
        }

        // Fallback to first AR account
        return Account::where('company_id', $companyId)
            ->where('subtype', 'accounts_receivable')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->value('id');
    }
}
