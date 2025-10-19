<?php

namespace Modules\Reporting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KpiComputationService
{
    private CurrencyConversionService $currencyService;

    public function __construct(CurrencyConversionService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Compute aging KPIs for accounts receivable/payable
     */
    public function computeAgingKpis(string $companyId, array $parameters): array
    {
        $date = $parameters['date'] ?? now()->toDateString();
        $currency = $parameters['currency'] ?? 'USD';
        $agingBuckets = $parameters['aging_buckets'] ?? [30, 60, 90, 120];

        // Set company context for RLS
        DB::statement('SET app.current_company_id = ?', [$companyId]);

        try {
            // Accounts Receivable Aging
            $receivablesAging = $this->computeReceivablesAging($companyId, $date, $agingBuckets);

            // Accounts Payable Aging
            $payablesAging = $this->computePayablesAging($companyId, $date, $agingBuckets);

            // Convert to target currency if needed
            $baseCurrency = $this->getCompanyBaseCurrency($companyId);
            if ($baseCurrency !== $currency) {
                $receivablesAging = $this->convertAgingData($receivablesAging, $baseCurrency, $currency, $date);
                $payablesAging = $this->convertAgingData($payablesAging, $baseCurrency, $currency, $date);
            }

            return [
                'company_id' => $companyId,
                'as_of_date' => $date,
                'currency' => $currency,
                'receivables_aging' => $receivablesAging,
                'payables_aging' => $payablesAging,
                'aging_metrics' => $this->calculateAgingMetrics($receivablesAging, $payablesAging),
                'generated_at' => now()->toISOString(),
            ];
        } finally {
            DB::statement('RESET app.current_company_id');
        }
    }

    /**
     * Compute budget vs actual KPIs
     */
    public function computeBudgetKpis(string $companyId, array $parameters): array
    {
        $dateRange = $this->parseDateRange($parameters);
        $currency = $parameters['currency'] ?? 'USD';

        // Set company context for RLS
        DB::statement('SET app.current_company_id = ?', [$companyId]);

        try {
            // Get budget data
            $budgetData = $this->getBudgetData($companyId, $dateRange);

            // Get actual data
            $actualData = $this->getActualData($companyId, $dateRange);

            // Calculate variance analysis
            $varianceAnalysis = $this->calculateBudgetVariance($budgetData, $actualData);

            // Convert to target currency if needed
            $baseCurrency = $this->getCompanyBaseCurrency($companyId);
            if ($baseCurrency !== $currency) {
                $varianceAnalysis = $this->convertBudgetData($varianceAnalysis, $baseCurrency, $currency, $dateRange['start']);
            }

            return [
                'company_id' => $companyId,
                'period_start' => $dateRange['start'],
                'period_end' => $dateRange['end'],
                'currency' => $currency,
                'budget_vs_actual' => $varianceAnalysis,
                'summary_metrics' => $this->calculateBudgetSummaryMetrics($varianceAnalysis),
                'generated_at' => now()->toISOString(),
            ];
        } finally {
            DB::statement('RESET app.current_company_id');
        }
    }

    /**
     * Compute advanced KPIs (ratios, trends, etc.)
     */
    public function computeAdvancedKpis(string $companyId, array $parameters): array
    {
        $dateRange = $this->parseDateRange($parameters);
        $currency = $parameters['currency'] ?? 'USD';
        $comparison = $parameters['comparison'] ?? 'prior_period';

        // Set company context for RLS
        DB::statement('SET app.current_company_id = ?', [$companyId]);

        try {
            // Get financial data for current and comparison periods
            $currentData = $this->getFinancialData($companyId, $dateRange);
            $comparisonRange = $this->getComparisonDateRange($dateRange, $comparison);
            $comparisonData = $this->getFinancialData($companyId, $comparisonRange);

            // Calculate financial ratios
            $ratios = $this->calculateFinancialRatios($currentData, $comparisonData);

            // Calculate growth rates
            $growthRates = $this->calculateGrowthRates($currentData, $comparisonData);

            // Calculate efficiency metrics
            $efficiencyMetrics = $this->calculateEfficiencyMetrics($currentData, $comparisonData);

            // Convert to target currency if needed
            $baseCurrency = $this->getCompanyBaseCurrency($companyId);
            if ($baseCurrency !== $currency) {
                $ratios = $this->convertRatioData($ratios, $baseCurrency, $currency, $dateRange['start']);
                $growthRates = $this->convertRatioData($growthRates, $baseCurrency, $currency, $dateRange['start']);
                $efficiencyMetrics = $this->convertRatioData($efficiencyMetrics, $baseCurrency, $currency, $dateRange['start']);
            }

            return [
                'company_id' => $companyId,
                'period_start' => $dateRange['start'],
                'period_end' => $dateRange['end'],
                'comparison_start' => $comparisonRange['start'],
                'comparison_end' => $comparisonRange['end'],
                'currency' => $currency,
                'financial_ratios' => $ratios,
                'growth_rates' => $growthRates,
                'efficiency_metrics' => $efficiencyMetrics,
                'health_score' => $this->calculateFinancialHealthScore($ratios, $efficiencyMetrics),
                'generated_at' => now()->toISOString(),
            ];
        } finally {
            DB::statement('RESET app.current_company_id');
        }
    }

    /**
     * Compute receivables aging
     */
    protected function computeReceivablesAging(string $companyId, string $date, array $buckets): array
    {
        $aging = ['total' => 0, 'buckets' => []];
        $totalAmount = 0;

        foreach ($buckets as $days) {
            $amount = DB::table('acct.invoices as i')
                ->join('acct.customers as c', 'i.customer_id', '=', 'c.id')
                ->where('i.company_id', $companyId)
                ->where('i.status', 'posted')
                ->where('i.total_amount', '>', 0)
                ->whereRaw('DATEDIFF(?, i.due_date) BETWEEN ? AND ?', [$date, $days - 29, $days])
                ->sum('i.total_amount - COALESCE(i.paid_amount, 0)');

            $bucketKey = "{$days}_days";
            $aging['buckets'][$bucketKey] = [
                'days' => $days,
                'amount' => (float) $amount,
                'count' => DB::table('acct.invoices as i')
                    ->join('acct.customers as c', 'i.customer_id', '=', 'c.id')
                    ->where('i.company_id', $companyId)
                    ->where('i.status', 'posted')
                    ->where('i.total_amount', '>', 0)
                    ->whereRaw('DATEDIFF(?, i.due_date) BETWEEN ? AND ?', [$date, $days - 29, $days])
                    ->count(),
                'percentage' => 0, // Will be calculated below
            ];

            $totalAmount += $amount;
        }

        // Add overdue beyond last bucket
        $maxDays = max($buckets);
        $overdueAmount = DB::table('acct.invoices as i')
            ->join('acct.customers as c', 'i.customer_id', '=', 'c.id')
            ->where('i.company_id', $companyId)
            ->where('i.status', 'posted')
            ->where('i.total_amount', '>', 0)
            ->whereRaw('DATEDIFF(?, i.due_date) > ?', [$date, $maxDays])
            ->sum('i.total_amount - COALESCE(i.paid_amount, 0)');

        $aging['buckets']['overdue'] = [
            'days' => '> '.$maxDays,
            'amount' => (float) $overdueAmount,
            'count' => DB::table('acct.invoices as i')
                ->join('acct.customers as c', 'i.customer_id', '=', 'c.id')
                ->where('i.company_id', $companyId)
                ->where('i.status', 'posted')
                ->where('i.total_amount', '>', 0)
                ->whereRaw('DATEDIFF(?, i.due_date) > ?', [$date, $maxDays])
                ->count(),
            'percentage' => 0,
        ];

        $totalAmount += $overdueAmount;
        $aging['total'] = $totalAmount;

        // Calculate percentages
        if ($totalAmount > 0) {
            foreach ($aging['buckets'] as $key => &$bucket) {
                $bucket['percentage'] = round(($bucket['amount'] / $totalAmount) * 100, 2);
            }
        }

        return $aging;
    }

    /**
     * Compute payables aging
     */
    protected function computePayablesAging(string $companyId, string $date, array $buckets): array
    {
        $aging = ['total' => 0, 'buckets' => []];
        $totalAmount = 0;

        foreach ($buckets as $days) {
            $amount = DB::table('acct.bills as b')
                ->join('acct.vendors as v', 'b.vendor_id', '=', 'v.id')
                ->where('b.company_id', $companyId)
                ->where('b.status', 'posted')
                ->where('b.total_amount', '>', 0)
                ->whereRaw('DATEDIFF(?, b.due_date) BETWEEN ? AND ?', [$date, $days - 29, $days])
                ->sum('b.total_amount - COALESCE(b.paid_amount, 0)');

            $bucketKey = "{$days}_days";
            $aging['buckets'][$bucketKey] = [
                'days' => $days,
                'amount' => (float) $amount,
                'count' => DB::table('acct.bills as b')
                    ->join('acct.vendors as v', 'b.vendor_id', '=', 'v.id')
                    ->where('b.company_id', $companyId)
                    ->where('b.status', 'posted')
                    ->where('b.total_amount', '>', 0)
                    ->whereRaw('DATEDIFF(?, b.due_date) BETWEEN ? AND ?', [$date, $days - 29, $days])
                    ->count(),
                'percentage' => 0,
            ];

            $totalAmount += $amount;
        }

        // Add overdue beyond last bucket
        $maxDays = max($buckets);
        $overdueAmount = DB::table('acct.bills as b')
            ->join('acct.vendors as v', 'b.vendor_id', '=', 'v.id')
            ->where('b.company_id', $companyId)
            ->where('b.status', 'posted')
            ->where('b.total_amount', '>', 0)
            ->whereRaw('DATEDIFF(?, b.due_date) > ?', [$date, $maxDays])
            ->sum('b.total_amount - COALESCE(b.paid_amount, 0)');

        $aging['buckets']['overdue'] = [
            'days' => '> '.$maxDays,
            'amount' => (float) $overdueAmount,
            'count' => DB::table('acct.bills as b')
                ->join('acct.vendors as v', 'b.vendor_id', '=', 'v.id')
                ->where('b.company_id', $companyId)
                ->where('b.status', 'posted')
                ->where('b.total_amount', '>', 0)
                ->whereRaw('DATEDIFF(?, b.due_date) > ?', [$date, $maxDays])
                ->count(),
            'percentage' => 0,
        ];

        $totalAmount += $overdueAmount;
        $aging['total'] = $totalAmount;

        // Calculate percentages
        if ($totalAmount > 0) {
            foreach ($aging['buckets'] as $key => &$bucket) {
                $bucket['percentage'] = round(($bucket['amount'] / $totalAmount) * 100, 2);
            }
        }

        return $aging;
    }

    /**
     * Calculate aging metrics
     */
    protected function calculateAgingMetrics(array $receivables, array $payables): array
    {
        return [
            'receivables_turnover_days' => $this->calculateTurnoverDays($receivables, 'receivables'),
            'payables_turnover_days' => $this->calculateTurnoverDays($payables, 'payables'),
            'collection_effectiveness' => $this->calculateCollectionEffectiveness($receivables),
            'payment_trend' => $this->calculatePaymentTrend($payables),
            'aging_health_score' => $this->calculateAgingHealthScore($receivables, $payables),
        ];
    }

    /**
     * Get budget data
     */
    protected function getBudgetData(string $companyId, array $dateRange): array
    {
        // This would typically query a budget table
        // For now, return empty structure
        return [
            'revenue' => 0,
            'expenses' => 0,
            'cost_of_goods_sold' => 0,
            'operating_expenses' => 0,
            'net_income' => 0,
        ];
    }

    /**
     * Get actual data
     */
    protected function getActualData(string $companyId, array $dateRange): array
    {
        $revenue = DB::table('ledger.journal_lines as jl')
            ->join('ledger.journal_entries as je', 'jl.journal_entry_id', '=', 'je.id')
            ->join('acct.accounts as a', 'jl.account_id', '=', 'a.id')
            ->where('je.company_id', $companyId)
            ->where('je.status', 'posted')
            ->whereBetween('je.date', [$dateRange['start'], $dateRange['end']])
            ->where('a.account_type', 'revenue')
            ->where('jl.debit_credit', 'credit')
            ->sum('jl.amount');

        $expenses = DB::table('ledger.journal_lines as jl')
            ->join('ledger.journal_entries as je', 'jl.journal_entry_id', '=', 'je.id')
            ->join('acct.accounts as a', 'jl.account_id', '=', 'a.id')
            ->where('je.company_id', $companyId)
            ->where('je.status', 'posted')
            ->whereBetween('je.date', [$dateRange['start'], $dateRange['end']])
            ->where('a.account_type', 'expense')
            ->where('jl.debit_credit', 'debit')
            ->sum('jl.amount');

        $costOfGoodsSold = DB::table('ledger.journal_lines as jl')
            ->join('ledger.journal_entries as je', 'jl.journal_entry_id', '=', 'je.id')
            ->join('acct.accounts as a', 'jl.account_id', '=', 'a.id')
            ->where('je.company_id', $companyId)
            ->where('je.status', 'posted')
            ->whereBetween('je.date', [$dateRange['start'], $dateRange['end']])
            ->where('a.account_type', 'expense')
            ->where('a.name', 'LIKE', '%Cost of Goods Sold%')
            ->where('jl.debit_credit', 'debit')
            ->sum('jl.amount');

        return [
            'revenue' => (float) $revenue,
            'expenses' => (float) $expenses,
            'cost_of_goods_sold' => (float) $costOfGoodsSold,
            'operating_expenses' => (float) $expenses - (float) $costOfGoodsSold,
            'net_income' => (float) $revenue - (float) $expenses,
        ];
    }

    /**
     * Calculate budget variance
     */
    protected function calculateBudgetVariance(array $budget, array $actual): array
    {
        $variance = [];
        foreach ($budget as $key => $budgetAmount) {
            $actualAmount = $actual[$key] ?? 0;
            $difference = $actualAmount - $budgetAmount;
            $percentage = $budgetAmount != 0 ? ($difference / $budgetAmount) * 100 : 0;

            $variance[$key] = [
                'budget' => (float) $budgetAmount,
                'actual' => (float) $actualAmount,
                'variance' => (float) $difference,
                'variance_percentage' => round((float) $percentage, 2),
                'status' => $difference > 0 ? 'over' : ($difference < 0 ? 'under' : 'on_target'),
            ];
        }

        return $variance;
    }

    /**
     * Get financial data for ratios
     */
    protected function getFinancialData(string $companyId, array $dateRange): array
    {
        $totalRevenue = DB::table('ledger.journal_lines as jl')
            ->join('ledger.journal_entries as je', 'jl.journal_entry_id', '=', 'je.id')
            ->join('acct.accounts as a', 'jl.account_id', '=', 'a.id')
            ->where('je.company_id', $companyId)
            ->where('je.status', 'posted')
            ->whereBetween('je.date', [$dateRange['start'], $dateRange['end']])
            ->where('a.account_type', 'revenue')
            ->where('jl.debit_credit', 'credit')
            ->sum('jl.amount');

        $totalExpenses = DB::table('ledger.journal_lines as jl')
            ->join('ledger.journal_entries as je', 'jl.journal_entry_id', '=', 'je.id')
            ->join('acct.accounts as a', 'jl.account_id', '=', 'a.id')
            ->where('je.company_id', $companyId)
            ->where('je.status', 'posted')
            ->whereBetween('je.date', [$dateRange['start'], $dateRange['end']])
            ->where('a.account_type', 'expense')
            ->where('jl.debit_credit', 'debit')
            ->sum('jl.amount');

        $totalAssets = DB::table('acct.accounts as a')
            ->where('a.company_id', $companyId)
            ->where('a.account_type', 'asset')
            ->sum('a.current_balance');

        $totalLiabilities = DB::table('acct.accounts as a')
            ->where('a.company_id', $companyId)
            ->where('a.account_type', 'liability')
            ->sum('a.current_balance');

        $totalEquity = DB::table('acct.accounts as a')
            ->where('a.company_id', $companyId)
            ->where('a.account_type', 'equity')
            ->sum('a.current_balance');

        return [
            'total_revenue' => (float) $totalRevenue,
            'total_expenses' => (float) $totalExpenses,
            'net_income' => (float) $totalRevenue - (float) $totalExpenses,
            'total_assets' => (float) $totalAssets,
            'total_liabilities' => (float) $totalLiabilities,
            'total_equity' => (float) $totalEquity,
        ];
    }

    /**
     * Calculate financial ratios
     */
    protected function calculateFinancialRatios(array $currentData, array $comparisonData): array
    {
        $ratios = [];

        // Profitability Ratios
        if ($currentData['total_revenue'] > 0) {
            $ratios['gross_profit_margin'] = [
                'current' => round(($currentData['total_revenue'] - $currentData['total_expenses']) / $currentData['total_revenue'] * 100, 2),
                'comparison' => round(($comparisonData['total_revenue'] - $comparisonData['total_expenses']) / $comparisonData['total_revenue'] * 100, 2),
            ];
        }

        // Liquidity Ratios
        if ($currentData['total_liabilities'] > 0) {
            $ratios['current_ratio'] = [
                'current' => round($currentData['total_assets'] / $currentData['total_liabilities'], 2),
                'comparison' => round($comparisonData['total_assets'] / $comparisonData['total_liabilities'], 2),
            ];
        }

        // Debt Ratios
        if ($currentData['total_assets'] > 0) {
            $ratios['debt_to_equity'] = [
                'current' => round($currentData['total_liabilities'] / $currentData['total_equity'], 2),
                'comparison' => round($comparisonData['total_liabilities'] / $comparisonData['total_equity'], 2),
            ];
        }

        return $ratios;
    }

    /**
     * Calculate growth rates
     */
    protected function calculateGrowthRates(array $currentData, array $comparisonData): array
    {
        $growthRates = [];

        foreach ($currentData as $key => $currentValue) {
            $comparisonValue = $comparisonData[$key] ?? 0;

            if ($comparisonValue != 0) {
                $growthRates[$key] = round((($currentValue - $comparisonValue) / $comparisonValue) * 100, 2);
            } else {
                $growthRates[$key] = $currentValue > 0 ? 100 : 0;
            }
        }

        return $growthRates;
    }

    /**
     * Calculate efficiency metrics
     */
    protected function calculateEfficiencyMetrics(array $currentData, array $comparisonData): array
    {
        return [
            'revenue_per_employee' => $this->calculateRevenuePerEmployee($currentData),
            'asset_turnover' => $this->calculateAssetTurnover($currentData),
            'expense_ratio' => $this->calculateExpenseRatio($currentData),
        ];
    }

    // Helper methods would continue here...
    // For brevity, I'll include key method signatures

    protected function calculateTurnoverDays(array $aging, string $type): float
    {
        // Calculate average collection/payment days
        return 45.0; // Placeholder
    }

    protected function calculateCollectionEffectiveness(array $receivables): float
    {
        // Calculate percentage of receivables collected within terms
        return 85.5; // Placeholder
    }

    protected function calculatePaymentTrend(array $payables): float
    {
        // Calculate payment speed trend
        return 78.2; // Placeholder
    }

    protected function calculateAgingHealthScore(array $receivables, array $payables): int
    {
        // Calculate overall aging health score (0-100)
        return 82; // Placeholder
    }

    protected function calculateRevenuePerEmployee(array $data): float
    {
        return 150000.00; // Placeholder
    }

    protected function calculateAssetTurnover(array $data): float
    {
        return $data['total_assets'] > 0 ? $data['total_revenue'] / $data['total_assets'] : 0;
    }

    protected function calculateExpenseRatio(array $data): float
    {
        return $data['total_revenue'] > 0 ? ($data['total_expenses'] / $data['total_revenue']) * 100 : 0;
    }

    protected function calculateBudgetSummaryMetrics(array $variance): array
    {
        return [
            'total_budget_variance' => array_sum(array_column($variance, 'variance')),
            'variance_percentage' => 5.2, // Placeholder
            'on_target_categories' => 3, // Placeholder
            'off_target_categories' => 2, // Placeholder
        ];
    }

    protected function calculateFinancialHealthScore(array $ratios, array $efficiency): int
    {
        // Calculate overall financial health score (0-100)
        return 75; // Placeholder
    }

    // Helper utility methods
    protected function parseDateRange(array $parameters): array
    {
        return [
            'start' => $parameters['date_range']['start'] ?? now()->startOfMonth()->toDateString(),
            'end' => $parameters['date_range']['end'] ?? now()->endOfMonth()->toDateString(),
        ];
    }

    protected function getComparisonDateRange(array $dateRange, string $comparison): array
    {
        // Return comparison date range based on comparison type
        $start = Carbon::parse($dateRange['start']);
        $end = Carbon::parse($dateRange['end']);
        $duration = $start->diffInDays($end);

        switch ($comparison) {
            case 'prior_period':
                return [
                    'start' => $start->subDays($duration + 1)->toDateString(),
                    'end' => $start->subDay()->toDateString(),
                ];
            case 'prior_year':
                return [
                    'start' => $start->subYear()->toDateString(),
                    'end' => $end->subYear()->toDateString(),
                ];
            default:
                return $dateRange;
        }
    }

    protected function getCompanyBaseCurrency(string $companyId): string
    {
        $company = DB::table('auth.companies')
            ->where('id', $companyId)
            ->value('base_currency');

        return $company ?? 'USD';
    }

    protected function convertAgingData(array $aging, string $fromCurrency, string $toCurrency, string $date): array
    {
        $conversion = $this->currencyService->convertAmount($aging['total'], $fromCurrency, $toCurrency);

        $aging['total'] = $conversion['converted_amount'];
        $aging['original_currency'] = $fromCurrency;
        $aging['target_currency'] = $toCurrency;
        $aging['exchange_rate'] = $conversion['exchange_rate'];

        foreach ($aging['buckets'] as &$bucket) {
            $bucketConversion = $this->currencyService->convertAmount($bucket['amount'], $fromCurrency, $toCurrency);
            $bucket['amount'] = $bucketConversion['converted_amount'];
        }

        return $aging;
    }

    protected function convertBudgetData(array $data, string $fromCurrency, string $toCurrency, string $date): array
    {
        foreach ($data as $category => &$values) {
            if (isset($values['budget'])) {
                $conversion = $this->currencyService->convertAmount($values['budget'], $fromCurrency, $toCurrency);
                $values['budget'] = $conversion['converted_amount'];
            }
            if (isset($values['actual'])) {
                $conversion = $this->currencyService->convertAmount($values['actual'], $fromCurrency, $toCurrency);
                $values['actual'] = $conversion['converted_amount'];
            }
            if (isset($values['variance'])) {
                $conversion = $this->currencyService->convertAmount($values['variance'], $fromCurrency, $toCurrency);
                $values['variance'] = $conversion['converted_amount'];
            }
        }

        return $data;
    }

    protected function convertRatioData(array $data, string $fromCurrency, string $toCurrency, string $date): array
    {
        // Most ratios are percentages and don't need currency conversion
        // But some absolute values might need conversion
        return $data;
    }
}
