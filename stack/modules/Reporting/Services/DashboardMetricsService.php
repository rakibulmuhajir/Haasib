<?php

namespace Modules\Reporting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    public function __construct(
        private DashboardCacheService $cacheService
    ) {}

    /**
     * Get dashboard metrics for a specific layout
     */
    public function getDashboardMetrics(string $companyId, string $layoutId, array $parameters = []): array
    {
        $cacheKey = $this->cacheService->getDashboardCacheKey($companyId, $layoutId, $parameters);

        return Cache::store('reporting_dashboard')->remember(
            $cacheKey,
            config('reporting.cache.dashboard_ttl', 5),
            fn () => $this->computeDashboardMetrics($companyId, $layoutId, $parameters)
        );
    }

    /**
     * Compute dashboard metrics by aggregating data from materialized views
     */
    private function computeDashboardMetrics(string $companyId, string $layoutId, array $parameters = []): array
    {
        // Set company context for RLS
        DB::statement('SET app.current_company_id = ?', [$companyId]);

        try {
            // Get layout configuration
            $layout = $this->getDashboardLayout($companyId, $layoutId);

            // Compute metrics for each card in the layout
            $cards = collect($layout->cards)->map(function ($card) use ($companyId, $parameters) {
                return $this->computeCardMetrics($companyId, $card, $parameters);
            })->all();

            // Compute summary totals
            $totals = $this->computeSummaryTotals($companyId, $parameters);

            return [
                'layout' => [
                    'layout_id' => $layout->layout_id,
                    'name' => $layout->name,
                    'visibility' => $layout->visibility,
                ],
                'refreshed_at' => now()->toISOString(),
                'cards' => $cards,
                'totals' => $totals,
                'parameters' => $parameters,
            ];

        } finally {
            // Reset company context
            DB::statement('RESET app.current_company_id');
        }
    }

    /**
     * Get dashboard layout configuration
     */
    private function getDashboardLayout(string $companyId, string $layoutId): object
    {
        $layout = DB::table('rpt.dashboard_layouts')
            ->where('layout_id', $layoutId)
            ->where('company_id', $companyId)
            ->first();

        if (! $layout) {
            throw new \InvalidArgumentException("Dashboard layout not found: {$layoutId}");
        }

        return $layout;
    }

    /**
     * Compute metrics for a specific card
     */
    private function computeCardMetrics(string $companyId, object $card, array $parameters = []): array
    {
        $cardId = $card->card_id;
        $cardType = $card->type ?? 'kpi';
        $cardConfig = $card->config ?? [];

        return match ($cardType) {
            'kpi' => $this->computeKpiCard($companyId, $cardConfig, $parameters),
            'chart' => $this->computeChartCard($companyId, $cardConfig, $parameters),
            'table' => $this->computeTableCard($companyId, $cardConfig, $parameters),
            default => $this->computeDefaultCard($cardId, $cardConfig, $parameters),
        };
    }

    /**
     * Compute KPI card metrics
     */
    private function computeKpiCard(string $companyId, array $config, array $parameters): array
    {
        $kpiCode = $config['kpi_code'] ?? 'revenue';
        $dateRange = $this->getDateRange($parameters);
        $comparison = $parameters['comparison'] ?? 'prior_period';

        // Get KPI snapshot from database or compute on-the-fly
        $currentValue = $this->getKpiValue($companyId, $kpiCode, $dateRange);
        $previousValue = $this->getKpiValue($companyId, $kpiCode, $this->getComparisonDateRange($dateRange, $comparison));

        // Calculate variance
        $variance = $previousValue ? (($currentValue - $previousValue) / abs($previousValue)) * 100 : 0;

        return [
            'card_id' => $config['card_id'],
            'type' => 'kpi',
            'title' => $config['title'] ?? ucfirst(str_replace('_', ' ', $kpiCode)),
            'data' => [
                'value' => $currentValue,
                'format' => $this->getKpiFormat($kpiCode),
                'currency' => $parameters['currency'] ?? 'USD',
            ],
            'comparison' => [
                'previous_value' => $previousValue,
                'variance_percent' => round($variance, 2),
                'trend' => $variance >= 0 ? 'up' : 'down',
            ],
            'drilldown_url' => $this->generateDrilldownUrl($kpiCode, $parameters),
        ];
    }

    /**
     * Compute chart card data
     */
    private function computeChartCard(string $companyId, array $config, array $parameters): array
    {
        $chartType = $config['chart_type'] ?? 'line';
        $metric = $config['metric'] ?? 'revenue';
        $dateRange = $this->getDateRange($parameters);
        $granularity = $config['granularity'] ?? 'monthly';

        $data = $this->getChartData($companyId, $metric, $dateRange, $granularity);

        return [
            'card_id' => $config['card_id'],
            'type' => 'chart',
            'title' => $config['title'] ?? ucfirst(str_replace('_', ' ', $metric)),
            'data' => [
                'chart_type' => $chartType,
                'labels' => $data['labels'],
                'datasets' => $data['datasets'],
            ],
            'options' => $config['options'] ?? [],
        ];
    }

    /**
     * Compute table card data
     */
    private function computeTableCard(string $companyId, array $config, array $parameters): array
    {
        $tableType = $config['table_type'] ?? 'top_accounts';
        $limit = $config['limit'] ?? 10;
        $dateRange = $this->getDateRange($parameters);

        $data = match ($tableType) {
            'top_accounts' => $this->getTopAccounts($companyId, $dateRange, $limit),
            'recent_transactions' => $this->getRecentTransactions($companyId, $dateRange, $limit),
            default => [],
        };

        return [
            'card_id' => $config['card_id'],
            'type' => 'table',
            'title' => $config['title'] ?? ucfirst(str_replace('_', ' ', $tableType)),
            'data' => $data,
            'columns' => $config['columns'] ?? $this->getTableColumns($tableType),
        ];
    }

    /**
     * Get KPI value from snapshots or compute on-the-fly
     */
    private function getKpiValue(string $companyId, string $kpiCode, array $dateRange): float
    {
        // Try to get from KPI snapshots first
        $snapshot = DB::table('rpt.kpi_snapshots')
            ->join('rpt.kpi_definitions', 'rpt.kpi_snapshots.kpi_id', '=', 'rpt.kpi_definitions.kpi_id')
            ->where('rpt.kpi_definitions.code', $kpiCode)
            ->where('rpt.kpi_snapshots.company_id', $companyId)
            ->where('rpt.kpi_snapshots.period_start', '>=', $dateRange['start'])
            ->where('rpt.kpi_snapshots.period_end', '<=', $dateRange['end'])
            ->orderBy('rpt.kpi_snapshots.captured_at', 'desc')
            ->first();

        if ($snapshot) {
            return (float) $snapshot->value;
        }

        // Compute on-the-fly from materialized views
        return match ($kpiCode) {
            'revenue' => $this->computeRevenue($companyId, $dateRange),
            'expenses' => $this->computeExpenses($companyId, $dateRange),
            'profit' => $this->computeProfit($companyId, $dateRange),
            'cash_balance' => $this->computeCashBalance($companyId),
            default => 0,
        };
    }

    /**
     * Compute revenue from income statement materialized view
     */
    private function computeRevenue(string $companyId, array $dateRange): float
    {
        return DB::table('rpt.mv_income_statement_monthly')
            ->where('company_id', $companyId)
            ->where('month', '>=', $dateRange['start'])
            ->where('month', '<=', $dateRange['end'])
            ->where('account_type', 'Revenue')
            ->sum('amount');
    }

    /**
     * Compute expenses from income statement materialized view
     */
    private function computeExpenses(string $companyId, array $dateRange): float
    {
        return DB::table('rpt.mv_income_statement_monthly')
            ->where('company_id', $companyId)
            ->where('month', '>=', $dateRange['start'])
            ->where('month', '<=', $dateRange['end'])
            ->where('account_type', 'Expense')
            ->sum('amount');
    }

    /**
     * Compute profit (Revenue - Expenses)
     */
    private function computeProfit(string $companyId, array $dateRange): float
    {
        $revenue = $this->computeRevenue($companyId, $dateRange);
        $expenses = $this->computeExpenses($companyId, $dateRange);

        return $revenue + $expenses; // Expenses are stored as negative values
    }

    /**
     * Compute current cash balance from trial balance materialized view
     */
    private function computeCashBalance(string $companyId): float
    {
        return DB::table('rpt.mv_trial_balance_current')
            ->where('company_id', $companyId)
            ->where('account_code', 'like', '1000%') // Cash accounts typically start with 1000
            ->sum('balance');
    }

    /**
     * Get chart data for specified metric and date range
     */
    private function getChartData(string $companyId, string $metric, array $dateRange, string $granularity): array
    {
        $data = match ($metric) {
            'revenue' => $this->getRevenueChartData($companyId, $dateRange, $granularity),
            'expenses' => $this->getExpensesChartData($companyId, $dateRange, $granularity),
            'cash_flow' => $this->getCashFlowChartData($companyId, $dateRange, $granularity),
            default => [],
        };

        return $data;
    }

    /**
     * Get revenue chart data
     */
    private function getRevenueChartData(string $companyId, array $dateRange, string $granularity): array
    {
        $groupBy = $granularity === 'monthly' ? 'DATE_TRUNC(\'month\', month)' : 'month';

        $results = DB::table('rpt.mv_income_statement_monthly')
            ->where('company_id', $companyId)
            ->where('month', '>=', $dateRange['start'])
            ->where('month', '<=', $dateRange['end'])
            ->where('account_type', 'Revenue')
            ->selectRaw("{$groupBy} as period, SUM(amount) as value")
            ->groupBy($groupBy)
            ->orderBy('period')
            ->get();

        return [
            'labels' => $results->pluck('period')->map(fn ($date) => Carbon::parse($date)->format('M Y')),
            'datasets' => [[
                'label' => 'Revenue',
                'data' => $results->pluck('value'),
                'borderColor' => 'rgb(34, 197, 94)',
                'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
            ]],
        ];
    }

    /**
     * Get expenses chart data
     */
    private function getExpensesChartData(string $companyId, array $dateRange, string $granularity): array
    {
        $groupBy = $granularity === 'monthly' ? 'DATE_TRUNC(\'month\', month)' : 'month';

        $results = DB::table('rpt.mv_income_statement_monthly')
            ->where('company_id', $companyId)
            ->where('month', '>=', $dateRange['start'])
            ->where('month', '<=', $dateRange['end'])
            ->where('account_type', 'Expense')
            ->selectRaw("{$groupBy} as period, SUM(amount) as value")
            ->groupBy($groupBy)
            ->orderBy('period')
            ->get();

        return [
            'labels' => $results->pluck('period')->map(fn ($date) => Carbon::parse($date)->format('M Y')),
            'datasets' => [[
                'label' => 'Expenses',
                'data' => $results->pluck('value'),
                'borderColor' => 'rgb(239, 68, 68)',
                'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
            ]],
        ];
    }

    /**
     * Get cash flow chart data
     */
    private function getCashFlowChartData(string $companyId, array $dateRange, string $granularity): array
    {
        $groupBy = $granularity === 'monthly' ? 'DATE_TRUNC(\'month\', cash_flow_date)' : 'cash_flow_date';

        $results = DB::table('rpt.mv_cash_flow_daily')
            ->where('company_id', $companyId)
            ->where('cash_flow_date', '>=', $dateRange['start'])
            ->where('cash_flow_date', '<=', $dateRange['end'])
            ->selectRaw("{$groupBy} as period, SUM(amount) as value")
            ->groupBy($groupBy)
            ->orderBy('period')
            ->get();

        return [
            'labels' => $results->pluck('period')->map(fn ($date) => Carbon::parse($date)->format('M Y')),
            'datasets' => [[
                'label' => 'Cash Flow',
                'data' => $results->pluck('value'),
                'borderColor' => 'rgb(59, 130, 246)',
                'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
            ]],
        ];
    }

    /**
     * Get top accounts by balance
     */
    private function getTopAccounts(string $companyId, array $dateRange, int $limit): array
    {
        return DB::table('rpt.mv_trial_balance_current')
            ->where('company_id', $companyId)
            ->where('balance', '!=', 0)
            ->orderByRaw('ABS(balance) DESC')
            ->limit($limit)
            ->get()
            ->map(function ($account) {
                return [
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'balance' => abs($account->balance),
                    'type' => $account->balance >= 0 ? 'Debit' : 'Credit',
                    'currency' => $account->currency,
                ];
            })
            ->all();
    }

    /**
     * Get recent transactions
     */
    private function getRecentTransactions(string $companyId, array $dateRange, int $limit): array
    {
        return DB::table('rpt.v_transaction_drilldown')
            ->where('company_id', $companyId)
            ->where('entry_date', '>=', $dateRange['start'])
            ->where('entry_date', '<=', $dateRange['end'])
            ->orderBy('entry_date', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($transaction) {
                return [
                    'entry_number' => $transaction->entry_number,
                    'entry_date' => $transaction->entry_date,
                    'account_name' => $transaction->account_name,
                    'amount' => abs($transaction->amount),
                    'description' => $transaction->description,
                    'currency' => $transaction->currency_symbol,
                ];
            })
            ->all();
    }

    /**
     * Compute summary totals for the dashboard
     */
    private function computeSummaryTotals(string $companyId, array $parameters): array
    {
        $dateRange = $this->getDateRange($parameters);

        return [
            [
                'label' => 'Total Revenue',
                'value' => $this->computeRevenue($companyId, $dateRange),
                'currency' => $parameters['currency'] ?? 'USD',
                'trend_percent' => $this->calculateTrend($companyId, 'revenue', $dateRange),
                'direction' => 'up', // Calculate based on trend
            ],
            [
                'label' => 'Total Expenses',
                'value' => $this->computeExpenses($companyId, $dateRange),
                'currency' => $parameters['currency'] ?? 'USD',
                'trend_percent' => $this->calculateTrend($companyId, 'expenses', $dateRange),
                'direction' => 'down', // Expenses going down is good
            ],
            [
                'label' => 'Net Profit',
                'value' => $this->computeProfit($companyId, $dateRange),
                'currency' => $parameters['currency'] ?? 'USD',
                'trend_percent' => $this->calculateTrend($companyId, 'profit', $dateRange),
                'direction' => 'up', // Profit going up is good
            ],
            [
                'label' => 'Cash Balance',
                'value' => $this->computeCashBalance($companyId),
                'currency' => $parameters['currency'] ?? 'USD',
                'trend_percent' => null, // Cash balance is current, not period-based
                'direction' => 'flat',
            ],
        ];
    }

    /**
     * Helper methods
     */
    private function getDateRange(array $parameters): array
    {
        $start = $parameters['date_range']['start'] ?? now()->startOfMonth()->toDateString();
        $end = $parameters['date_range']['end'] ?? now()->toDateString();

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function getComparisonDateRange(array $dateRange, string $comparison): array
    {
        $start = Carbon::parse($dateRange['start']);
        $end = Carbon::parse($dateRange['end']);
        $duration = $start->diffInDays($end);

        return match ($comparison) {
            'prior_period' => [
                'start' => $start->copy()->subDays($duration)->toDateString(),
                'end' => $end->copy()->subDays($duration)->toDateString(),
            ],
            'prior_year' => [
                'start' => $start->copy()->subYear()->toDateString(),
                'end' => $end->copy()->subYear()->toDateString(),
            ],
            default => $dateRange,
        };
    }

    private function getKpiFormat(string $kpiCode): string
    {
        return match ($kpiCode) {
            'cash_balance', 'revenue', 'expenses', 'profit' => 'currency',
            'dso', 'cash_runway' => 'days',
            'profit_margin', 'growth_rate' => 'percentage',
            default => 'number',
        };
    }

    private function calculateTrend(string $companyId, string $metric, array $dateRange): float
    {
        $currentValue = $this->getKpiValue($companyId, $metric, $dateRange);
        $previousDateRange = $this->getComparisonDateRange($dateRange, 'prior_period');
        $previousValue = $this->getKpiValue($companyId, $metric, $previousDateRange);

        if (! $previousValue) {
            return 0;
        }

        return (($currentValue - $previousValue) / abs($previousValue)) * 100;
    }

    private function getTableColumns(string $tableType): array
    {
        return match ($tableType) {
            'top_accounts' => ['Account', 'Balance', 'Type'],
            'recent_transactions' => ['Date', 'Account', 'Amount', 'Description'],
            default => ['Column'],
        };
    }

    private function generateDrilldownUrl(string $kpiCode, array $parameters): string
    {
        return route('reporting.drilldown', ['kpi' => $kpiCode]).'?'.http_build_query($parameters);
    }

    private function computeDefaultCard(string $cardId, array $config, array $parameters): array
    {
        return [
            'card_id' => $cardId,
            'type' => 'stat',
            'title' => $config['title'] ?? 'Unknown Card',
            'data' => [
                'value' => 0,
                'format' => 'number',
            ],
        ];
    }
}
