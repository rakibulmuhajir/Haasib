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
     * Post an invoice to the general ledger.
     * Creates: DR AR, CR revenue per line (line total includes tax/discount for now).
     */
    public function postInvoice(Invoice $invoice): Transaction
    {
        $invoice->loadMissing(['customer', 'lineItems']);

        $companyId = $invoice->company_id;
        $transactionDate = $invoice->invoice_date ?? now();
        $period = $this->resolveOpenPeriod($companyId, $transactionDate);

        $arAccountId = $invoice->customer?->ar_account_id ?? $this->defaultArAccountId($companyId);
        if (!$arAccountId) {
            throw new \RuntimeException('Customer is missing an AR control account and no company default AR account is configured.');
        }

        if ($invoice->lineItems->isEmpty()) {
            throw new \RuntimeException('Cannot post invoice without line items.');
        }

        $creditLines = [];
        $creditTotal = 0.0;
        foreach ($invoice->lineItems as $idx => $line) {
            $accountId = $line->income_account_id ?? $this->defaultRevenueAccountId($companyId);
            if (!$accountId) {
                $lineNumber = $line->line_number ?? ($idx + 1);
                throw new \RuntimeException("Income account is required on invoice line {$lineNumber}.");
            }

            $lineAmount = (float) $line->total;
            $creditTotal += $lineAmount;
            $creditLines[] = [
                'account_id' => $accountId,
                'type' => 'credit',
                'amount' => $lineAmount,
                'description' => $line->description,
            ];
        }

        $debitAmount = (float) $invoice->total_amount;
        $difference = round($debitAmount - $creditTotal, 2);
        if (abs($difference) >= 0.01 && !empty($creditLines)) {
            // Adjust the first credit line to maintain balance (handles rounding drift)
            $creditLines[0]['amount'] = round($creditLines[0]['amount'] + $difference, 2);
            $creditTotal = array_sum(array_column($creditLines, 'amount'));
        }

        return $this->createTransaction([
            'company_id' => $companyId,
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
        ], array_merge([
            [
                'account_id' => $arAccountId,
                'type' => 'debit',
                'amount' => $debitAmount,
                'description' => 'Accounts Receivable',
            ],
        ], $creditLines));
    }

    /**
     * Post a payment to the general ledger.
     * Creates: DR Bank/Cash, CR AR.
     */
    public function postPayment(Payment $payment, string $depositAccountId, string $arAccountId): Transaction
    {
        $companyId = $payment->company_id;
        $transactionDate = $payment->payment_date ?? now();
        $period = $this->resolveOpenPeriod($companyId, $transactionDate);

        $amount = (float) $payment->amount;

        return $this->createTransaction([
            'company_id' => $companyId,
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

    /**
     * Post a bill to the general ledger: DR expense lines, CR AP.
     */
    public function postBill(Bill $bill): Transaction
    {
        $bill->loadMissing(['vendor', 'lineItems']);
        $companyId = $bill->company_id;
        $transactionDate = $bill->bill_date ?? now();
        $period = $this->resolveOpenPeriod($companyId, $transactionDate);

        $apAccountId = $bill->vendor?->ap_account_id ?? $this->defaultApAccountId($companyId);
        if (!$apAccountId) {
            throw new \RuntimeException('Vendor is missing an AP control account and no company default AP account is configured.');
        }

        if ($bill->lineItems->isEmpty()) {
            throw new \RuntimeException('Cannot post bill without line items.');
        }

        $debitLines = [];
        $debitTotal = 0.0;
        foreach ($bill->lineItems as $idx => $line) {
            $accountId = $line->expense_account_id ?? $this->defaultExpenseAccountId($companyId);
            if (!$accountId) {
                $lineNumber = $line->line_number ?? ($idx + 1);
                throw new \RuntimeException("Expense account is required on bill line {$lineNumber}.");
            }
            $lineAmount = (float) $line->total;
            $debitTotal += $lineAmount;
            $debitLines[] = [
                'account_id' => $accountId,
                'type' => 'debit',
                'amount' => $lineAmount,
                'description' => $line->description,
            ];
        }

        $creditAmount = (float) $bill->total_amount;
        $difference = round($creditAmount - $debitTotal, 2);
        if (abs($difference) >= 0.01 && !empty($debitLines)) {
            $debitLines[0]['amount'] = round($debitLines[0]['amount'] + $difference, 2);
            $debitTotal = array_sum(array_column($debitLines, 'amount'));
        }

        return $this->createTransaction([
            'company_id' => $companyId,
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
        ], array_merge($debitLines, [
            [
                'account_id' => $apAccountId,
                'type' => 'credit',
                'amount' => $creditAmount,
                'description' => 'Accounts Payable',
            ],
        ]));
    }

    /**
     * Post a bill payment: DR AP, CR payment account.
     */
    public function postBillPayment(BillPayment $payment, string $paymentAccountId, string $apAccountId): Transaction
    {
        $companyId = $payment->company_id;
        $transactionDate = $payment->payment_date ?? now();
        $period = $this->resolveOpenPeriod($companyId, $transactionDate);

        $amount = (float) $payment->amount;

        return $this->createTransaction([
            'company_id' => $companyId,
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

    /**
     * Post a vendor credit: DR AP, CR expense reversal.
     */
    public function postVendorCredit(VendorCredit $credit): Transaction
    {
        $credit->loadMissing(['vendor', 'items']);
        $companyId = $credit->company_id;
        $transactionDate = $credit->credit_date ?? now();
        $period = $this->resolveOpenPeriod($companyId, $transactionDate);

        $apAccountId = $credit->vendor?->ap_account_id ?? $this->defaultApAccountId($companyId);
        if (!$apAccountId) {
            throw new \RuntimeException('Vendor is missing an AP control account and no company default AP account is configured.');
        }

        $creditLines = [];
        $creditTotal = 0.0;

        if ($credit->items->isNotEmpty()) {
            foreach ($credit->items as $idx => $item) {
                $accountId = $item->expense_account_id ?? $this->defaultExpenseAccountId($companyId);
                if (!$accountId) {
                    $lineNumber = $item->line_number ?? ($idx + 1);
                    throw new \RuntimeException("Expense account is required on vendor credit line {$lineNumber}.");
                }
                $lineAmount = (float) $item->total;
                $creditTotal += $lineAmount;
                $creditLines[] = [
                    'account_id' => $accountId,
                    'type' => 'credit',
                    'amount' => $lineAmount,
                    'description' => $item->description,
                ];
            }
        } else {
            $creditTotal = (float) $credit->amount;
            $accountId = $this->defaultExpenseAccountId($companyId);
            if (!$accountId) {
                throw new \RuntimeException('Expense account required to post vendor credit.');
            }
            $creditLines[] = [
                'account_id' => $accountId,
                'type' => 'credit',
                'amount' => $creditTotal,
                'description' => 'Vendor credit',
            ];
        }

        $debitAmount = (float) $credit->amount;
        $difference = round($debitAmount - $creditTotal, 2);
        if (abs($difference) >= 0.01 && !empty($creditLines)) {
            $creditLines[0]['amount'] = round($creditLines[0]['amount'] + $difference, 2);
            $creditTotal = array_sum(array_column($creditLines, 'amount'));
        }

        return $this->createTransaction([
            'company_id' => $companyId,
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
        ], array_merge([
            [
                'account_id' => $apAccountId,
                'type' => 'debit',
                'amount' => $debitAmount,
                'description' => 'Accounts Payable',
            ],
        ], $creditLines));
    }

    /**
     * Post a credit note: DR revenue (reverse), CR AR.
     */
    public function postCreditNote(CreditNote $creditNote): Transaction
    {
        $creditNote->loadMissing(['customer', 'invoice.lineItems']);
        $companyId = $creditNote->company_id;
        $transactionDate = $creditNote->credit_date ?? now();
        $period = $this->resolveOpenPeriod($companyId, $transactionDate);

        $arAccountId = $creditNote->customer?->ar_account_id ?? $this->defaultArAccountId($companyId);
        if (!$arAccountId) {
            throw new \RuntimeException('Customer is missing an AR control account and no company default AR account is configured.');
        }

        $debitLines = [];
        $debitTotal = 0.0;

        if ($creditNote->invoice && $creditNote->invoice->lineItems->isNotEmpty()) {
            foreach ($creditNote->invoice->lineItems as $idx => $line) {
                $accountId = $line->income_account_id ?? $this->defaultRevenueAccountId($companyId);
                $lineAmount = (float) $line->total;
                $debitTotal += $lineAmount;
                $debitLines[] = [
                    'account_id' => $accountId,
                    'type' => 'debit',
                    'amount' => $lineAmount,
                    'description' => "Reverse revenue: {$line->description}",
                ];
            }
        } else {
            $debitTotal = (float) $creditNote->amount;
            $accountId = $this->defaultRevenueAccountId($companyId);
            if (!$accountId) {
                throw new \RuntimeException('Revenue account required to post credit note.');
            }
            $debitLines[] = [
                'account_id' => $accountId,
                'type' => 'debit',
                'amount' => $debitTotal,
                'description' => 'Credit note',
            ];
        }

        $creditAmount = (float) $creditNote->amount;
        $difference = round($debitTotal - $creditAmount, 2);
        if (abs($difference) >= 0.01 && !empty($debitLines)) {
            $debitLines[0]['amount'] = round($debitLines[0]['amount'] - $difference, 2);
            $debitTotal = array_sum(array_column($debitLines, 'amount'));
        }

        return $this->createTransaction([
            'company_id' => $companyId,
            'transaction_number' => $creditNote->credit_note_number,
            'transaction_type' => 'credit_note',
            'transaction_date' => $transactionDate,
            'posting_date' => $transactionDate,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period_id' => $period->id,
            'currency' => $creditNote->base_currency,
            'base_currency' => $creditNote->base_currency,
            'exchange_rate' => null,
            'reference_type' => 'acct.credit_notes',
            'reference_id' => $creditNote->id,
            'description' => $creditNote->reason ?? "Credit note {$creditNote->credit_note_number}",
        ], array_merge($debitLines, [
            [
                'account_id' => $arAccountId,
                'type' => 'credit',
                'amount' => $creditAmount,
                'description' => 'Accounts Receivable',
            ],
        ]));
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
        $debitTotal = 0.0;
        $creditTotal = 0.0;

        foreach ($entries as $entry) {
            if ($entry['type'] === 'debit') {
                $debitTotal += (float) $entry['amount'];
            } else {
                $creditTotal += (float) $entry['amount'];
            }
        }

        if (abs($debitTotal - $creditTotal) >= 0.01) {
            throw new \RuntimeException('Transaction not balanced; debits must equal credits.');
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
                'posted_at' => now(),
                'posted_by_user_id' => Auth::id(),
                'created_by_user_id' => Auth::id(),
            ]);

            foreach ($entries as $index => $entry) {
                JournalEntry::create([
                    'company_id' => $data['company_id'],
                    'transaction_id' => $transaction->id,
                    'account_id' => $entry['account_id'],
                    'line_number' => $index + 1,
                    'description' => $entry['description'] ?? null,
                    'debit_amount' => $entry['type'] === 'debit' ? $entry['amount'] : 0,
                    'credit_amount' => $entry['type'] === 'credit' ? $entry['amount'] : 0,
                    'currency_debit' => null,
                    'currency_credit' => null,
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
            $fiscalYearService = new FiscalYearService();
            $company = \App\Models\Company::find($companyId);

            if ($company && $company->getAutoCreateFiscalYear()) {
                $fiscalYear = $fiscalYearService->ensureCurrentFiscalYearExists($companyId);

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
