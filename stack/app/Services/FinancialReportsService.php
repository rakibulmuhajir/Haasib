<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportsService
{
    /**
     * Generate trial balance for a company
     */
    public function generateTrialBalance(Company $company, array $options = []): array
    {
        $dateFrom = $options['date_from'] ?? null;
        $dateTo = $options['date_to'] ?? now();
        $currency = $options['currency'] ?? $company->currency_code ?? 'USD';
        $includeZeroBalances = $options['include_zero_balances'] ?? false;

        // Build the base query for journal lines
        $query = JournalLine::join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_lines.account_id', '=', 'chart_of_accounts.id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.posted_at', '<=', $dateTo)
            ->when($dateFrom, function ($query) use ($dateFrom) {
                $query->where('journal_entries.posted_at', '>=', $dateFrom);
            })
            ->select([
                'chart_of_accounts.id',
                'chart_of_accounts.account_number',
                'chart_of_accounts.account_name',
                'chart_of_accounts.account_type',
                DB::raw('SUM(journal_lines.debit_amount) as total_debit'),
                DB::raw('SUM(journal_lines.credit_amount) as total_credit'),
            ])
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.account_number',
                'chart_of_accounts.account_name',
                'chart_of_accounts.account_type'
            )
            ->orderBy('chart_of_accounts.account_type')
            ->orderBy('chart_of_accounts.account_number');

        $accounts = $query->get();

        // Calculate balances for each account type
        $processedAccounts = [];
        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($accounts as $account) {
            $balance = $this->calculateAccountBalance($account->account_type, $account->total_debit, $account->total_credit);

            // Skip zero balance accounts if not requested
            if (! $includeZeroBalances && abs($balance) < 0.01) {
                continue;
            }

            $accountData = [
                'id' => $account->id,
                'account_number' => $account->account_number,
                'account_name' => $account->account_name,
                'account_type' => $account->account_type,
                'debit' => $account->total_debit,
                'credit' => $account->total_credit,
                'balance' => $balance,
                'balance_type' => $balance >= 0 ? 'debit' : 'credit',
                'balance_abs' => abs($balance),
                'opening_balance' => $this->getOpeningBalance($company, $account->id, $dateFrom),
            ];

            $processedAccounts[] = $accountData;
            $totalDebits += $account->total_debit;
            $totalCredits += $account->total_credit;
        }

        $totalDifference = $totalDebits - $totalCredits;

        return [
            'period' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'generated_at' => now()->toISOString(),
            'company_id' => $company->id,
            'company_name' => $company->name,
            'currency' => $currency,
            'accounts' => $processedAccounts,
            'summary' => [
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'total_difference' => $totalDifference,
                'is_balanced' => abs($totalDifference) < 0.01,
                'account_count' => count($processedAccounts),
            ],
            'metadata' => [
                'include_zero_balances' => $includeZeroBalances,
                'date_filter_applied' => ! empty($dateFrom),
                'account_filter_applied' => false,
                'currency_filter_applied' => $currency !== ($company->currency_code ?? 'USD'),
            ],
        ];
    }

    /**
     * Generate balance sheet for a company
     */
    public function generateBalanceSheet(Company $company, array $options = []): array
    {
        $dateTo = $options['date_to'] ?? now();
        $currency = $options['currency'] ?? $company->currency_code ?? 'USD';

        // Get trial balance data as base
        $trialBalance = $this->generateTrialBalance($company, [
            'date_to' => $dateTo,
            'currency' => $currency,
            'include_zero_balances' => true,
        ]);

        // Organize accounts into balance sheet sections
        $assets = [];
        $liabilities = [];
        $equity = [];
        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;

        foreach ($trialBalance['accounts'] as $account) {
            $accountBalance = $account['balance_abs'];
            $balanceType = $this->getBalanceSheetBalanceType($account['account_type'], $account['balance']);

            switch ($account['account_type']) {
                case 'Asset':
                    $assets[] = [
                        'id' => $account['id'],
                        'account_number' => $account['account_number'],
                        'account_name' => $account['account_name'],
                        'balance' => $accountBalance,
                        'balance_type' => $balanceType,
                    ];
                    if ($balanceType === 'debit') {
                        $totalAssets += $accountBalance;
                    } else {
                        $totalAssets -= $accountBalance;
                    }
                    break;

                case 'Liability':
                    $liabilities[] = [
                        'id' => $account['id'],
                        'account_number' => $account['account_number'],
                        'account_name' => $account['account_name'],
                        'balance' => $accountBalance,
                        'balance_type' => $balanceType,
                    ];
                    if ($balanceType === 'credit') {
                        $totalLiabilities += $accountBalance;
                    } else {
                        $totalLiabilities -= $accountBalance;
                    }
                    break;

                case 'Equity':
                    $equity[] = [
                        'id' => $account['id'],
                        'account_number' => $account['account_number'],
                        'account_name' => $account['account_name'],
                        'balance' => $accountBalance,
                        'balance_type' => $balanceType,
                    ];
                    if ($balanceType === 'credit') {
                        $totalEquity += $accountBalance;
                    } else {
                        $totalEquity -= $accountBalance;
                    }
                    break;
            }
        }

        // Add current period net income/loss to equity
        $incomeStatement = $this->generateIncomeStatement($company, [
            'date_to' => $dateTo,
            'currency' => $currency,
        ]);

        $currentPeriodNetIncome = $incomeStatement['summary']['net_income'];
        if ($currentPeriodNetIncome != 0) {
            $equity[] = [
                'id' => null,
                'account_number' => '9999',
                'account_name' => 'Current Period Net Income (Loss)',
                'balance' => abs($currentPeriodNetIncome),
                'balance_type' => $currentPeriodNetIncome > 0 ? 'credit' : 'debit',
                'is_calculated' => true,
            ];

            if ($currentPeriodNetIncome > 0) {
                $totalEquity += $currentPeriodNetIncome;
            } else {
                $totalEquity += $currentPeriodNetIncome; // negative value
            }
        }

        // Check if balance sheet balances
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;
        $isBalanced = abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01;

        return [
            'as_of_date' => $dateTo,
            'generated_at' => now()->toISOString(),
            'company_id' => $company->id,
            'company_name' => $company->name,
            'currency' => $currency,
            'sections' => [
                'assets' => [
                    'accounts' => $assets,
                    'total' => $totalAssets,
                ],
                'liabilities' => [
                    'accounts' => $liabilities,
                    'total' => $totalLiabilities,
                ],
                'equity' => [
                    'accounts' => $equity,
                    'total' => $totalEquity,
                ],
            ],
            'summary' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'total_equity' => $totalEquity,
                'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
                'is_balanced' => $isBalanced,
                'difference' => $totalAssets - $totalLiabilitiesAndEquity,
            ],
        ];
    }

    /**
     * Generate income statement for a company
     */
    public function generateIncomeStatement(Company $company, array $options = []): array
    {
        $dateFrom = $options['date_from'] ?? now()->startOfMonth();
        $dateTo = $options['date_to'] ?? now();
        $currency = $options['currency'] ?? $company->currency_code ?? 'USD';

        // Get revenue accounts
        $revenues = $this->getIncomeStatementAccounts($company, 'Revenue', $dateFrom, $dateTo);

        // Get expense accounts
        $expenses = $this->getIncomeStatementAccounts($company, 'Expense', $dateFrom, $dateTo);

        $totalRevenue = array_sum(array_column($revenues, 'total'));
        $totalExpenses = array_sum(array_column($expenses, 'total'));
        $grossProfit = $totalRevenue;
        $netIncome = $grossProfit - $totalExpenses;
        $profitMargin = $totalRevenue > 0 ? ($netIncome / $totalRevenue) * 100 : 0;

        return [
            'period' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'generated_at' => now()->toISOString(),
            'company_id' => $company->id,
            'company_name' => $company->name,
            'currency' => $currency,
            'sections' => [
                'revenues' => [
                    'accounts' => $revenues,
                    'total' => $totalRevenue,
                ],
                'expenses' => [
                    'accounts' => $expenses,
                    'total' => $totalExpenses,
                ],
            ],
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_expenses' => $totalExpenses,
                'gross_profit' => $grossProfit,
                'net_income' => $netIncome,
                'profit_margin' => $profitMargin,
                'is_profitable' => $netIncome > 0,
            ],
        ];
    }

    /**
     * Get accounts for income statement with totals
     */
    private function getIncomeStatementAccounts(Company $company, string $accountType, Carbon $dateFrom, Carbon $dateTo): array
    {
        $query = JournalLine::join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_lines.account_id', '=', 'chart_of_accounts.id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.posted_at', '>=', $dateFrom)
            ->where('journal_entries.posted_at', '<=', $dateTo)
            ->where('chart_of_accounts.account_type', $accountType)
            ->select([
                'chart_of_accounts.id',
                'chart_of_accounts.account_number',
                'chart_of_accounts.account_name',
                'chart_of_accounts.account_category',
            ])
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.account_number', 'chart_of_accounts.account_name', 'chart_of_accounts.account_category');

        if ($accountType === 'Revenue') {
            $query->addSelect(DB::raw('SUM(journal_lines.credit_amount) as total'));
        } else {
            $query->addSelect(DB::raw('SUM(journal_lines.debit_amount) as total'));
        }

        $accounts = $query->orderBy('chart_of_accounts.account_category')
            ->orderBy('chart_of_accounts.account_number')
            ->get();

        return $accounts->map(function ($account) {
            return [
                'id' => $account->id,
                'account_number' => $account->account_number,
                'account_name' => $account->account_name,
                'account_category' => $account->account_category ?? 'Uncategorized',
                'total' => $account->total,
            ];
        })->toArray();
    }

    /**
     * Calculate account balance based on account type
     */
    private function calculateAccountBalance(string $accountType, float $debits, float $credits): float
    {
        switch ($accountType) {
            case 'Asset':
            case 'Expense':
                return $debits - $credits;
            case 'Liability':
            case 'Equity':
            case 'Revenue':
                return $credits - $debits;
            default:
                return 0;
        }
    }

    /**
     * Get opening balance for an account
     */
    private function getOpeningBalance(Company $company, string $accountId, ?Carbon $dateFrom): float
    {
        if (! $dateFrom) {
            return 0;
        }

        $account = Account::find($accountId);
        if (! $account) {
            return 0;
        }

        $balance = JournalLine::join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'journal_lines.account_id', '=', 'chart_of_accounts.id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_lines.account_id', $accountId)
            ->where('journal_entries.posted_at', '<', $dateFrom)
            ->sum(DB::raw('CASE 
                WHEN chart_of_accounts.account_type IN (\'Asset\', \'Expense\') 
                THEN journal_lines.debit_amount - journal_lines.credit_amount 
                ELSE journal_lines.credit_amount - journal_lines.debit_amount 
            END'));

        return $balance;
    }

    /**
     * Get balance sheet balance type for display
     */
    private function getBalanceSheetBalanceType(string $accountType, float $trialBalanceBalance): string
    {
        // Normalize balance based on account type
        switch ($accountType) {
            case 'Asset':
            case 'Expense':
                return $trialBalanceBalance >= 0 ? 'debit' : 'credit';
            case 'Liability':
            case 'Equity':
            case 'Revenue':
                return $trialBalanceBalance >= 0 ? 'credit' : 'debit';
            default:
                return 'debit';
        }
    }
}
