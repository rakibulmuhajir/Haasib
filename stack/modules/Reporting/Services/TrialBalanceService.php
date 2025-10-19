<?php

namespace Modules\Reporting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TrialBalanceService
{
    private CurrencyConversionService $currencyService;

    public function __construct(CurrencyConversionService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Generate trial balance with variance analysis
     */
    public function generateTrialBalance(string $companyId, array $parameters): array
    {
        $dateRange = $this->parseDateRange($parameters);
        $currency = $parameters['currency'] ?? 'USD';
        $includeZeroBalances = $parameters['include_zero_balances'] ?? false;

        // Set company context for RLS
        DB::statement('SET app.current_company_id = ?', [$companyId]);

        try {
            // Get current trial balance (in company base currency first)
            $baseCurrency = $this->getCompanyBaseCurrency($companyId);
            $currentBalances = $this->getTrialBalanceData($companyId, $dateRange['end'], $baseCurrency);

            // Get prior period balances for variance analysis
            $priorPeriodDate = Carbon::parse($dateRange['end'])->subMonths(1)->endOfMonth()->toDateString();
            $priorBalances = $this->getTrialBalanceData($companyId, $priorPeriodDate, $baseCurrency);

            // Apply currency conversion if needed
            if ($baseCurrency !== $currency) {
                $currentBalances = $this->convertTrialBalanceData($currentBalances, $baseCurrency, $currency);
                $priorBalances = $this->convertTrialBalanceData($priorBalances, $baseCurrency, $currency);
            }

            // Get currency conversion snapshot
            $conversionSnapshot = $this->currencyService->getConversionSnapshot($companyId, $currency);

            // Combine and analyze data
            $trialBalance = $this->buildTrialBalanceReport($currentBalances, $priorBalances, $parameters);

            $trialBalance['metadata'] = [
                'company_id' => $companyId,
                'as_of_date' => $dateRange['end'],
                'comparison_date' => $priorPeriodDate,
                'currency' => $currency,
                'base_currency' => $baseCurrency,
                'exchange_rate_snapshot' => $conversionSnapshot,
                'include_zero_balances' => $includeZeroBalances,
                'generated_at' => now()->toISOString(),
                'total_accounts' => count($trialBalance['accounts']),
            ];

            return $trialBalance;

        } finally {
            DB::statement('RESET app.current_company_id');
        }
    }

    /**
     * Get trial balance data from materialized view
     */
    private function getTrialBalanceData(string $companyId, string $asOfDate, string $currency): array
    {
        $data = DB::table('rpt.mv_trial_balance_current')
            ->where('company_id', $companyId)
            ->where('currency_code', $currency)
            ->where('balance', '!=', 0)
            ->select([
                'account_code',
                'account_name',
                'account_type',
                'debit_balance',
                'credit_balance',
                'balance',
                'currency',
            ])
            ->orderBy('account_code')
            ->get();

        return $data->toArray();
    }

    /**
     * Build trial balance report with variance analysis
     */
    private function buildTrialBalanceReport(array $currentBalances, array $priorBalances, array $parameters): array
    {
        $accounts = [];
        $totals = [
            'current_period' => [
                'total_debits' => 0,
                'total_credits' => 0,
                'total_balance' => 0,
            ],
            'prior_period' => [
                'total_debits' => 0,
                'total_credits' => 0,
                'total_balance' => 0,
            ],
            'variance' => [
                'debit_variance' => 0,
                'credit_variance' => 0,
                'balance_variance' => 0,
                'debit_variance_percent' => 0,
                'credit_variance_percent' => 0,
            ],
        ];

        // Create lookup for prior period balances
        $priorLookup = [];
        foreach ($priorBalances as $prior) {
            $priorLookup[$prior['account_code']] = $prior;
        }

        // Process each account
        foreach ($currentBalances as $current) {
            $accountCode = $current['account_code'];
            $prior = $priorLookup[$accountCode] ?? null;

            $debitBalance = $current['debit_balance'] ?? 0;
            $creditBalance = $current['credit_balance'] ?? 0;
            $balance = $current['balance'] ?? 0;

            $priorDebit = $prior['debit_balance'] ?? 0;
            $priorCredit = $prior['credit_balance'] ?? 0;
            $priorBalance = $prior['balance'] ?? 0;

            // Calculate variances
            $debitVariance = $debitBalance - $priorDebit;
            $creditVariance = $creditBalance - $priorCredit;
            $balanceVariance = $balance - $priorBalance;

            $debitVariancePercent = $priorDebit ? ($debitVariance / abs($priorDebit)) * 100 : 0;
            $creditVariancePercent = $priorCredit ? ($creditVariance / abs($priorCredit)) * 100 : 0;

            // Variance analysis
            $varianceAnalysis = $this->analyzeVariance($accountCode, $current['account_type'], $debitVariance, $creditVariance, $balanceVariance);

            $account = [
                'account_code' => $accountCode,
                'account_name' => $current['account_name'],
                'account_type' => $current['account_type'],
                'currency' => $current['currency'],

                // Current period
                'current_period' => [
                    'debit_balance' => $debitBalance,
                    'credit_balance' => $creditBalance,
                    'balance' => $balance,
                    'balance_type' => $balance >= 0 ? 'Debit' : 'Credit',
                ],

                // Prior period
                'prior_period' => $prior ? [
                    'debit_balance' => $priorDebit,
                    'credit_balance' => $priorCredit,
                    'balance' => $priorBalance,
                    'balance_type' => $priorBalance >= 0 ? 'Debit' : 'Credit',
                ] : null,

                // Variance analysis
                'variance' => [
                    'debit_variance' => $debitVariance,
                    'credit_variance' => $creditVariance,
                    'balance_variance' => $balanceVariance,
                    'debit_variance_percent' => $debitVariancePercent,
                    'credit_variance_percent' => $creditVariancePercent,
                    'significance' => $varianceAnalysis['significance'],
                    'trend' => $varianceAnalysis['trend'],
                    'notes' => $varianceAnalysis['notes'],
                ],
            ];

            // Include zero balances if requested
            if ($parameters['include_zero_balances'] || $balance != 0) {
                $accounts[] = $account;
            }

            // Update totals
            $totals['current_period']['total_debits'] += $debitBalance;
            $totals['current_period']['total_credits'] += $creditBalance;
            $totals['current_period']['total_balance'] += abs($balance);

            $totals['prior_period']['total_debits'] += $priorDebit;
            $totals['prior_period']['total_credits'] += $priorCredit;
            $totals['prior_period']['total_balance'] += abs($priorBalance);

            $totals['variance']['debit_variance'] += abs($debitVariance);
            $totals['variance']['credit_variance'] += abs($creditVariance);
            $totals['variance']['balance_variance'] += abs($balanceVariance);
        }

        // Calculate variance percentages for totals
        $totals['variance']['debit_variance_percent'] = $totals['prior_period']['total_debits'] ?
            ($totals['variance']['debit_variance'] / $totals['prior_period']['total_debits']) * 100 : 0;
        $totals['variance']['credit_variance_percent'] = $totals['prior_period']['total_credits'] ?
            ($totals['variance']['credit_variance'] / $totals['prior_period']['total_credits']) * 100 : 0;

        // Sort accounts by account code
        usort($accounts, function ($a, $b) {
            return strnatcmp($a['account_code'], $b['account_code']);
        });

        // Group by account type
        $accountsByType = [];
        foreach ($accounts as $account) {
            $type = $account['account_type'];
            if (! isset($accountsByType[$type])) {
                $accountsByType[$type] = [
                    'type' => $type,
                    'accounts' => [],
                    'total_debits' => 0,
                    'total_credits' => 0,
                    'total_balance' => 0,
                ];
            }
            $accountsByType[$type]['accounts'][] = $account;
            $accountsByType[$type]['total_debits'] += $account['current_period']['debit_balance'];
            $accountsByType[$type]['total_credits'] += $account['current_period']['credit_balance'];
            $accountsByType[$type]['total_balance'] += abs($account['current_period']['balance']);
        }

        return [
            'is_balanced' => $this->isTrialBalanceBalanced($totals['current_period']),
            'accounts' => $accounts,
            'accounts_by_type' => array_values($accountsByType),
            'totals' => $totals,
            'variance_summary' => $this->generateVarianceSummary($accounts),
        ];
    }

    /**
     * Analyze variance significance and trends
     */
    private function analyzeVariance(string $accountCode, string $accountType, float $debitVariance, float $creditVariance, float $balanceVariance): array
    {
        $totalVariance = abs($debitVariance) + abs($creditVariance);

        // Determine significance based on variance magnitude
        $significance = 'low';
        if ($totalVariance > 100000) {
            $significance = 'high';
        } elseif ($totalVariance > 10000) {
            $significance = 'medium';
        }

        // Determine trend
        $trend = 'stable';
        if ($totalVariance > 1000) {
            if ($balanceVariance > 0) {
                $trend = 'increasing';
            } elseif ($balanceVariance < 0) {
                $trend = 'decreasing';
            }
        }

        // Generate notes for significant variances
        $notes = [];
        if ($significance === 'high') {
            $notes[] = 'Significant variance detected - requires investigation';
        }

        if ($accountType === 'Revenue' && $balanceVariance < -10000) {
            $notes[] = 'Revenue decline detected';
        }

        if ($accountType === 'Expense' && $balanceVariance > 10000) {
            $notes[] = 'Expense increase detected';
        }

        return [
            'significance' => $significance,
            'trend' => $trend,
            'notes' => $notes,
        ];
    }

    /**
     * Check if trial balance is balanced
     */
    private function isTrialBalanceBalanced(array $totals): bool
    {
        $tolerance = 0.01; // Small tolerance for floating point precision

        return abs($totals['total_debits'] - $totals['total_credits']) <= $tolerance;
    }

    /**
     * Generate variance summary
     */
    private function generateVarianceSummary(array $accounts): array
    {
        $summary = [
            'significant_variances' => 0,
            'high_variances' => 0,
            'medium_variances' => 0,
            'increasing_accounts' => 0,
            'decreasing_accounts' => 0,
            'stable_accounts' => 0,
        ];

        foreach ($accounts as $account) {
            $variance = $account['variance'];
            $totalVariance = abs($variance['debit_variance']) + abs($variance['credit_variance']);

            if ($totalVariance > 1000) {
                $summary['significant_variances']++;
            }

            if ($variance['significance'] === 'high') {
                $summary['high_variances']++;
            } elseif ($variance['significance'] === 'medium') {
                $summary['medium_variances']++;
            }

            if ($variance['trend'] === 'increasing') {
                $summary['increasing_accounts']++;
            } elseif ($variance['trend'] === 'decreasing') {
                $summary['decreasing_accounts']++;
            } else {
                $summary['stable_accounts']++;
            }
        }

        return $summary;
    }

    /**
     * Get detailed account analysis with drill-down capability
     */
    public function getAccountAnalysis(string $companyId, string $accountCode, array $parameters): array
    {
        $dateRange = $this->parseDateRange($parameters);
        $currency = $parameters['currency'] ?? 'USD';

        DB::statement('SET app.current_company_id = ?', [$companyId]);

        try {
            // Get account details
            $account = DB::table('rpt.mv_trial_balance_current')
                ->where('company_id', $companyId)
                ->where('account_code', $accountCode)
                ->where('currency_code', $currency)
                ->first();

            if (! $account) {
                throw new \InvalidArgumentException("Account not found: {$accountCode}");
            }

            // Get monthly trend data
            $monthlyData = $this->getAccountMonthlyTrend($companyId, $accountCode, $dateRange, $currency);

            // Get transaction breakdown
            $transactionBreakdown = $this->getTransactionBreakdown($companyId, $accountCode, $dateRange);

            return [
                'account' => [
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'account_type' => $account->account_type,
                    'current_balance' => $account->balance,
                    'debit_balance' => $account->debit_balance,
                    'credit_balance' => $account->credit_balance,
                    'currency' => $account->currency,
                ],
                'trend_analysis' => $monthlyData,
                'transaction_breakdown' => $transactionBreakdown,
                'generated_at' => now()->toISOString(),
            ];

        } finally {
            DB::statement('RESET app.current_company_id');
        }
    }

    /**
     * Get monthly trend data for an account
     */
    private function getAccountMonthlyTrend(string $companyId, string $accountCode, array $dateRange, string $currency): array
    {
        $data = DB::table('rpt.v_transaction_drilldown')
            ->join('acct.accounts as a', 'rpt.v_transaction_drilldown.account_id', '=', 'a.id')
            ->where('rpt.v_transaction_drilldown.company_id', $companyId)
            ->where('a.account_code', $accountCode)
            ->where('rpt.v_transaction_drilldown.entry_date', '>=', $dateRange['start'])
            ->where('rpt.v_transaction_drilldown.entry_date', '<=', $dateRange['end'])
            ->selectRaw('
                DATE_TRUNC(\'month\', entry_date) as month,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as debit_total,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as credit_total,
                SUM(amount) as balance,
                COUNT(*) as transaction_count
            ')
            ->groupBy(DB::raw('DATE_TRUNC(\'month\', entry_date)'))
            ->orderBy('month')
            ->get();

        return $data->toArray();
    }

    /**
     * Get transaction breakdown by category
     */
    private function getTransactionBreakdown(string $companyId, string $accountCode, array $dateRange): array
    {
        $data = DB::table('rpt.v_transaction_drilldown')
            ->join('acct.accounts as a', 'rpt.v_transaction_drilldown.account_id', '=', 'a.id')
            ->leftJoin('acct.counterparties as c', 'rpt.v_transaction_drilldown.counterparty_id', '=', 'c.id')
            ->where('rpt.v_transaction_drilldown.company_id', $companyId)
            ->where('a.account_code', $accountCode)
            ->where('rpt.v_transaction_drilldown.entry_date', '>=', $dateRange['start'])
            ->where('rpt.v_transaction_drilldown.entry_date', '<=', $dateRange['end'])
            ->selectRaw('
                COALESCE(c.counterparty_name, \'General\') as counterparty,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as debit_amount,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as credit_amount,
                SUM(amount) as net_amount,
                COUNT(*) as transaction_count
            ')
            ->groupBy('c.counterparty_name')
            ->orderByRaw('ABS(SUM(amount)) DESC')
            ->limit(10)
            ->get();

        return $data->toArray();
    }

    /**
     * Get trial balance adjustments
     */
    public function getTrialBalanceAdjustments(string $companyId, array $parameters): array
    {
        $dateRange = $this->parseDateRange($parameters);

        DB::statement('SET app.current_company_id = ?', [$companyId]);

        try {
            // Get adjusting journal entries in the period
            $adjustments = DB::table('rpt.v_transaction_drilldown')
                ->join('acct.accounts as a', 'rpt.v_transaction_drilldown.account_id', '=', 'a.id')
                ->join('ledger.journal_entries as je', 'rpt.v_transaction_drilldown.journal_entry_id', '=', 'je.id')
                ->where('rpt.v_transaction_drilldown.company_id', $companyId)
                ->where('rpt.v_transaction_drilldown.entry_date', '>=', $dateRange['start'])
                ->where('rpt.v_transaction_drilldown.entry_date', '<=', $dateRange['end'])
                ->where('je.entry_type', 'adjusting')
                ->select([
                    'je.entry_number',
                    'je.entry_date',
                    'je.description as entry_description',
                    'a.account_code',
                    'a.account_name',
                    'rpt.v_transaction_drilldown.amount',
                    'rpt.v_transaction_drilldown.description as line_description',
                ])
                ->orderBy('je.entry_date')
                ->orderBy('je.entry_number')
                ->get();

            return [
                'adjustments' => $adjustments->toArray(),
                'total_adjustments' => $adjustments->count(),
                'period_start' => $dateRange['start'],
                'period_end' => $dateRange['end'],
                'generated_at' => now()->toISOString(),
            ];

        } finally {
            DB::statement('RESET app.current_company_id');
        }
    }

    /**
     * Helper method to parse date range
     */
    private function parseDateRange(array $parameters): array
    {
        $start = $parameters['date_range']['start'] ?? now()->startOfMonth()->toDateString();
        $end = $parameters['date_range']['end'] ?? now()->endOfMonth()->toDateString();

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Convert trial balance data to target currency
     */
    protected function convertTrialBalanceData(array $balances, string $fromCurrency, string $toCurrency): array
    {
        if (empty($balances) || $fromCurrency === $toCurrency) {
            return $balances;
        }

        $conversion = $this->currencyService->convertBatch(
            array_column($balances, 'balance'),
            $fromCurrency,
            $toCurrency
        );

        foreach ($balances as $index => &$balance) {
            $convertedAmount = $conversion['conversions'][$index]['converted_amount'];

            // Update balance amount
            $balance['balance'] = $convertedAmount;

            // Update debit/credit balances
            if ($balance['balance'] > 0) {
                $balance['debit_balance'] = abs($convertedAmount);
                $balance['credit_balance'] = 0;
            } else {
                $balance['credit_balance'] = abs($convertedAmount);
                $balance['debit_balance'] = 0;
            }

            // Update currency field
            $balance['currency'] = $toCurrency;

            // Add conversion info
            $balance['original_balance'] = $conversion['conversions'][$index]['original_amount'];
            $balance['exchange_rate'] = $conversion['exchange_rate'];
        }

        return $balances;
    }

    /**
     * Get company base currency
     */
    protected function getCompanyBaseCurrency(string $companyId): string
    {
        $company = DB::table('auth.companies')
            ->where('id', $companyId)
            ->value('base_currency');

        return $company ?? 'USD';
    }
}
