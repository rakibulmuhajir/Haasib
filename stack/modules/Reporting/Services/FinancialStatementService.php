<?php

namespace Modules\Reporting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialStatementService
{
    private CurrencyConversionService $currencyService;

    public function __construct(CurrencyConversionService $currencyService)
    {
        $this->currencyService = $currencyService;
    }
{
    /**
     * Generate income statement with comparative data
     */
    public function generateIncomeStatement(string $companyId, array $parameters): array
    {
        $dateRange = $this->parseDateRange($parameters);
        $comparison = $parameters['comparison'] ?? 'prior_period';
        $currency = $parameters['currency'] ?? 'USD';

        // Set company context for RLS
        DB::statement('SET app.current_company_id = ?', [$companyId]);

        try {
            // Current period data
            $currentPeriod = $this->getIncomeStatementData($companyId, $dateRange, $currency);

            // Comparison period data
            $comparisonRange = $this->getComparisonDateRange($dateRange, $comparison);
            $comparisonPeriod = $this->getIncomeStatementData($companyId, $comparisonRange, $currency);

            // Get currency conversion snapshot
            $conversionSnapshot = $this->currencyService->getConversionSnapshot($companyId, $currency);
            
            // Apply currency conversion if needed
            $currentPeriod = $this->applyCurrencyConversion($currentPeriod, $companyId, $currency);
            $comparisonPeriod = $this->applyCurrencyConversion($comparisonPeriod, $companyId, $currency);

            // Build income statement structure
            $statement = [
                'statement_type' => 'income_statement',
                'company_id' => $companyId,
                'period_start' => $dateRange['start'],
                'period_end' => $dateRange['end'],
                'comparison_start' => $comparisonRange['start'],
                'comparison_end' => $comparisonRange['end'],
                'currency' => $currency,
                'exchange_rate_snapshot' => $conversionSnapshot,
                'sections' => [
                    'revenue' => $this->buildRevenueSection($currentPeriod, $comparisonPeriod),
                    'expenses' => $this->buildExpensesSection($currentPeriod, $comparisonPeriod),
                    'other_income' => $this->buildOtherIncomeSection($currentPeriod, $comparisonPeriod),
                    'other_expenses' => $this->buildOtherExpensesSection($currentPeriod, $comparisonPeriod),
                ],
                'totals' => $this->calculateIncomeStatementTotals($currentPeriod, $comparisonPeriod),
                'generated_at' => now()->toISOString(),
            ];

            return $statement;

        } finally {
            DB::statement('RESET app.current_company_id');
        }
    }

    /**
     * Generate balance sheet with comparative data
     */
    public function generateBalanceSheet(string $companyId, array $parameters): array
    {
        $dateRange = $this->parseDateRange($parameters);
        $comparison = $parameters['comparison'] ?? 'prior_period';
        $currency = $parameters['currency'] ?? 'USD';

        DB::statement('SET app.current_company_id = ?', [$companyId]);

        try {
            // Current period balance sheet
            $currentPeriod = $this->getBalanceSheetData($companyId, $dateRange['end'], $currency);

            // Comparison period balance sheet
            $comparisonDate = $this->getComparisonDateForBalanceSheet($dateRange['end'], $comparison);
            $comparisonPeriod = $this->getBalanceSheetData($companyId, $comparisonDate, $currency);

            // Get currency conversion snapshot
            $conversionSnapshot = $this->currencyService->getConversionSnapshot($companyId, $currency);
            
            // Apply currency conversion if needed
            $currentPeriod = $this->applyCurrencyConversion($currentPeriod, $companyId, $currency);
            $comparisonPeriod = $this->applyCurrencyConversion($comparisonPeriod, $companyId, $currency);

            $statement = [
                'statement_type' => 'balance_sheet',
                'company_id' => $companyId,
                'as_of_date' => $dateRange['end'],
                'comparison_as_of_date' => $comparisonDate,
                'currency' => $currency,
                'exchange_rate_snapshot' => $conversionSnapshot,
                'sections' => [
                    'assets' => $this->buildAssetsSection($currentPeriod, $comparisonPeriod),
                    'liabilities' => $this->buildLiabilitiesSection($currentPeriod, $comparisonPeriod),
                    'equity' => $this->buildEquitySection($currentPeriod, $comparisonPeriod),
                ],
                'totals' => $this->calculateBalanceSheetTotals($currentPeriod, $comparisonPeriod),
                'generated_at' => now()->toISOString(),
            ];

            return $statement;

        } finally {
            DB::statement('RESET app.current_company_id');
        }
    }

    /**
     * Generate cash flow statement
     */
    public function generateCashFlowStatement(string $companyId, array $parameters): array
    {
        $dateRange = $this->parseDateRange($parameters);
        $comparison = $parameters['comparison'] ?? 'prior_period';
        $currency = $parameters['currency'] ?? 'USD';

        DB::statement('SET app.current_company_id = ?', [$companyId]);

        try {
            // Current period cash flow
            $currentPeriod = $this->getCashFlowData($companyId, $dateRange, $currency);

            // Comparison period cash flow
            $comparisonRange = $this->getComparisonDateRange($dateRange, $comparison);
            $comparisonPeriod = $this->getCashFlowData($companyId, $comparisonRange, $currency);

            $statement = [
                'statement_type' => 'cash_flow',
                'period_start' => $dateRange['start'],
                'period_end' => $dateRange['end'],
                'comparison_start' => $comparisonRange['start'],
                'comparison_end' => $comparisonRange['end'],
                'currency' => $currency,
                'sections' => [
                    'operating_activities' => $this->buildOperatingActivitiesSection($currentPeriod, $comparisonPeriod),
                    'investing_activities' => $this->buildInvestingActivitiesSection($currentPeriod, $comparisonPeriod),
                    'financing_activities' => $this->buildFinancingActivitiesSection($currentPeriod, $comparisonPeriod),
                ],
                'totals' => $this->calculateCashFlowTotals($currentPeriod, $comparisonPeriod),
                'generated_at' => now()->toISOString(),
            ];

            return $statement;

        } finally {
            DB::statement('RESET app.current_company_id');
        }
    }

    /**
     * Get income statement data from database
     */
    private function getIncomeStatementData(string $companyId, array $dateRange, string $currency): array
    {
        $data = DB::table('rpt.mv_income_statement_monthly')
            ->where('company_id', $companyId)
            ->where('month', '>=', $dateRange['start'])
            ->where('month', '<=', $dateRange['end'])
            ->where('currency_code', $currency)
            ->selectRaw('
                account_category,
                account_type,
                SUM(amount) as total_amount,
                COUNT(*) as month_count
            ')
            ->groupBy('account_category', 'account_type')
            ->get()
            ->keyBy('account_category');

        return $data->toArray();
    }

    /**
     * Get balance sheet data from database
     */
    private function getBalanceSheetData(string $companyId, string $asOfDate, string $currency): array
    {
        // Get trial balance data as of the specified date
        $data = DB::table('rpt.v_transaction_drilldown')
            ->where('company_id', $companyId)
            ->where('entry_date', '<=', $asOfDate)
            ->selectRaw('
                a.account_category,
                a.account_type,
                a.account_code,
                a.account_name,
                SUM(jl.amount) as balance
            ')
            ->leftJoin('acct.accounts as a', 'rpt.v_transaction_drilldown.account_id', '=', 'a.id')
            ->groupBy('a.account_category', 'a.account_type', 'a.account_code', 'a.account_name')
            ->get()
            ->groupBy('account_category');

        return $data->toArray();
    }

    /**
     * Get cash flow data from database
     */
    private function getCashFlowData(string $companyId, array $dateRange, string $currency): array
    {
        $data = DB::table('rpt.mv_cash_flow_daily')
            ->where('company_id', $companyId)
            ->where('cash_flow_date', '>=', $dateRange['start'])
            ->where('cash_flow_date', '<=', $dateRange['end'])
            ->where('currency_code', $currency)
            ->selectRaw('
                cash_flow_type,
                account_category,
                SUM(amount) as total_amount
            ')
            ->groupBy('cash_flow_type', 'account_category')
            ->get()
            ->groupBy('cash_flow_type');

        return $data->toArray();
    }

    /**
     * Build revenue section for income statement
     */
    private function buildRevenueSection(array $current, array $comparison): array
    {
        $section = [
            'title' => 'Revenue',
            'type' => 'revenue',
            'lines' => [],
            'total' => 0,
            'comparison_total' => 0,
            'variance_amount' => 0,
            'variance_percent' => 0,
        ];

        $revenueCategories = ['Sales Revenue', 'Service Revenue', 'Other Revenue'];

        foreach ($revenueCategories as $category) {
            $currentAmount = $current[$category]->total_amount ?? 0;
            $comparisonAmount = $comparison[$category]->total_amount ?? 0;

            $section['lines'][] = [
                'account_category' => $category,
                'current_amount' => $currentAmount,
                'comparison_amount' => $comparisonAmount,
                'variance_amount' => $currentAmount - $comparisonAmount,
                'variance_percent' => $comparisonAmount ? (($currentAmount - $comparisonAmount) / abs($comparisonAmount)) * 100 : 0,
            ];

            $section['total'] += $currentAmount;
            $section['comparison_total'] += $comparisonAmount;
        }

        $section['variance_amount'] = $section['total'] - $section['comparison_total'];
        $section['variance_percent'] = $section['comparison_total'] ? (($section['total'] - $section['comparison_total']) / abs($section['comparison_total'])) * 100 : 0;

        return $section;
    }

    /**
     * Build expenses section for income statement
     */
    private function buildExpensesSection(array $current, array $comparison): array
    {
        $section = [
            'title' => 'Expenses',
            'type' => 'expenses',
            'lines' => [],
            'total' => 0,
            'comparison_total' => 0,
            'variance_amount' => 0,
            'variance_percent' => 0,
        ];

        $expenseCategories = ['Operating Expenses', 'Administrative Expenses', 'Sales & Marketing'];

        foreach ($expenseCategories as $category) {
            $currentAmount = abs($current[$category]->total_amount ?? 0); // Expenses are stored as negative
            $comparisonAmount = abs($comparison[$category]->total_amount ?? 0);

            $section['lines'][] = [
                'account_category' => $category,
                'current_amount' => $currentAmount,
                'comparison_amount' => $comparisonAmount,
                'variance_amount' => $currentAmount - $comparisonAmount,
                'variance_percent' => $comparisonAmount ? (($currentAmount - $comparisonAmount) / $comparisonAmount) * 100 : 0,
            ];

            $section['total'] += $currentAmount;
            $section['comparison_total'] += $comparisonAmount;
        }

        $section['variance_amount'] = $section['total'] - $section['comparison_total'];
        $section['variance_percent'] = $section['comparison_total'] ? (($section['total'] - $section['comparison_total']) / $section['comparison_total']) * 100 : 0;

        return $section;
    }

    /**
     * Build assets section for balance sheet
     */
    private function buildAssetsSection(array $current, array $comparison): array
    {
        $section = [
            'title' => 'Assets',
            'type' => 'assets',
            'subsections' => [
                'current_assets' => $this->buildBalanceSheetSubsection('Current Assets', $current, $comparison),
                'non_current_assets' => $this->buildBalanceSheetSubsection('Non-Current Assets', $current, $comparison),
            ],
            'total' => 0,
            'comparison_total' => 0,
        ];

        foreach ($section['subsections'] as $subsection) {
            $section['total'] += $subsection['total'];
            $section['comparison_total'] += $subsection['comparison_total'];
        }

        return $section;
    }

    /**
     * Build liabilities section for balance sheet
     */
    private function buildLiabilitiesSection(array $current, array $comparison): array
    {
        $section = [
            'title' => 'Liabilities',
            'type' => 'liabilities',
            'subsections' => [
                'current_liabilities' => $this->buildBalanceSheetSubsection('Current Liabilities', $current, $comparison),
                'non_current_liabilities' => $this->buildBalanceSheetSubsection('Non-Current Liabilities', $current, $comparison),
            ],
            'total' => 0,
            'comparison_total' => 0,
        ];

        foreach ($section['subsections'] as $subsection) {
            $section['total'] += $subsection['total'];
            $section['comparison_total'] += $subsection['comparison_total'];
        }

        return $section;
    }

    /**
     * Build equity section for balance sheet
     */
    private function buildEquitySection(array $current, array $comparison): array
    {
        $section = [
            'title' => "Owner's Equity",
            'type' => 'equity',
            'subsections' => [
                'share_capital' => $this->buildBalanceSheetSubsection('Share Capital', $current, $comparison),
                'retained_earnings' => $this->buildBalanceSheetSubsection('Retained Earnings', $current, $comparison),
            ],
            'total' => 0,
            'comparison_total' => 0,
        ];

        foreach ($section['subsections'] as $subsection) {
            $section['total'] += $subsection['total'];
            $section['comparison_total'] += $subsection['comparison_total'];
        }

        return $section;
    }

    /**
     * Build balance sheet subsection
     */
    private function buildBalanceSheetSubsection(string $title, array $current, array $comparison): array
    {
        $subsection = [
            'title' => $title,
            'lines' => [],
            'total' => 0,
            'comparison_total' => 0,
        ];

        // This would be expanded with actual account mappings
        // For now, it's a placeholder structure
        return $subsection;
    }

    /**
     * Build operating activities section for cash flow
     */
    private function buildOperatingActivitiesSection(array $current, array $comparison): array
    {
        $section = [
            'title' => 'Operating Activities',
            'type' => 'operating',
            'lines' => [],
            'total' => ($current['operating']->total_amount ?? 0),
            'comparison_total' => ($comparison['operating']->total_amount ?? 0),
        ];

        return $section;
    }

    /**
     * Build investing activities section for cash flow
     */
    private function buildInvestingActivitiesSection(array $current, array $comparison): array
    {
        $section = [
            'title' => 'Investing Activities',
            'type' => 'investing',
            'lines' => [],
            'total' => ($current['investing']->total_amount ?? 0),
            'comparison_total' => ($comparison['investing']->total_amount ?? 0),
        ];

        return $section;
    }

    /**
     * Build financing activities section for cash flow
     */
    private function buildFinancingActivitiesSection(array $current, array $comparison): array
    {
        $section = [
            'title' => 'Financing Activities',
            'type' => 'financing',
            'lines' => [],
            'total' => ($current['financing']->total_amount ?? 0),
            'comparison_total' => ($comparison['financing']->total_amount ?? 0),
        ];

        return $section;
    }

    /**
     * Calculate income statement totals
     */
    private function calculateIncomeStatementTotals(array $current, array $comparison): array
    {
        $revenue = $this->buildRevenueSection($current, $comparison);
        $expenses = $this->buildExpensesSection($current, $comparison);

        $grossProfit = $revenue['total'];
        $netIncome = $revenue['total'] - $expenses['total'];
        $comparisonNetIncome = $revenue['comparison_total'] - $expenses['comparison_total'];

        return [
            'revenue' => $revenue['total'],
            'comparison_revenue' => $revenue['comparison_total'],
            'expenses' => $expenses['total'],
            'comparison_expenses' => $expenses['comparison_total'],
            'gross_profit' => $grossProfit,
            'comparison_gross_profit' => $revenue['comparison_total'],
            'net_income' => $netIncome,
            'comparison_net_income' => $comparisonNetIncome,
            'profit_margin' => $revenue['total'] ? ($netIncome / $revenue['total']) * 100 : 0,
            'comparison_profit_margin' => $revenue['comparison_total'] ? ($comparisonNetIncome / $revenue['comparison_total']) * 100 : 0,
        ];
    }

    /**
     * Calculate balance sheet totals
     */
    private function calculateBalanceSheetTotals(array $current, array $comparison): array
    {
        $assets = $this->buildAssetsSection($current, $comparison);
        $liabilities = $this->buildLiabilitiesSection($current, $comparison);
        $equity = $this->buildEquitySection($current, $comparison);

        return [
            'total_assets' => $assets['total'],
            'comparison_total_assets' => $assets['comparison_total'],
            'total_liabilities' => $liabilities['total'],
            'comparison_total_liabilities' => $liabilities['comparison_total'],
            'total_equity' => $equity['total'],
            'comparison_total_equity' => $equity['comparison_total'],
            'liabilities_to_equity' => $equity['total'] ? ($liabilities['total'] / $equity['total']) : 0,
            'comparison_liabilities_to_equity' => $equity['comparison_total'] ? ($liabilities['comparison_total'] / $equity['comparison_total']) : 0,
        ];
    }

    /**
     * Calculate cash flow totals
     */
    private function calculateCashFlowTotals(array $current, array $comparison): array
    {
        $operating = $this->buildOperatingActivitiesSection($current, $comparison);
        $investing = $this->buildInvestingActivitiesSection($current, $comparison);
        $financing = $this->buildFinancingActivitiesSection($current, $comparison);

        $netCashFlow = $operating['total'] + $investing['total'] + $financing['total'];
        $comparisonNetCashFlow = $operating['comparison_total'] + $investing['comparison_total'] + $financing['comparison_total'];

        return [
            'operating_cash_flow' => $operating['total'],
            'comparison_operating_cash_flow' => $operating['comparison_total'],
            'investing_cash_flow' => $investing['total'],
            'comparison_investing_cash_flow' => $investing['comparison_total'],
            'financing_cash_flow' => $financing['total'],
            'comparison_financing_cash_flow' => $financing['comparison_total'],
            'net_cash_flow' => $netCashFlow,
            'comparison_net_cash_flow' => $comparisonNetCashFlow,
        ];
    }

    /**
     * Helper methods
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

    private function getComparisonDateRange(array $dateRange, string $comparison): array
    {
        $start = Carbon::parse($dateRange['start']);
        $end = Carbon::parse($dateRange['end']);
        $duration = $start->diffInDays($end);

        return match ($comparison) {
            'prior_period' => [
                'start' => $start->copy()->subDays($duration + 1)->toDateString(),
                'end' => $end->copy()->subDays($duration + 1)->toDateString(),
            ],
            'prior_year' => [
                'start' => $start->copy()->subYear()->toDateString(),
                'end' => $end->copy()->subYear()->toDateString(),
            ],
            default => $dateRange,
        };
    }

    private function getComparisonDateForBalanceSheet(string $asOfDate, string $comparison): string
    {
        return match ($comparison) {
            'prior_period' => Carbon::parse($asOfDate)->subMonths(1)->endOfMonth()->toDateString(),
            'prior_year' => Carbon::parse($asOfDate)->subYear()->toDateString(),
            default => $asOfDate,
        };
    }

    private function buildOtherIncomeSection(array $current, array $comparison): array
    {
        return $this->buildSectionTemplate('Other Income', $current, $comparison, ['Interest Income', 'Dividend Income']);
    }

    private function buildOtherExpensesSection(array $current, array $comparison): array
    {
        return $this->buildSectionTemplate('Other Expenses', $current, $comparison, ['Interest Expense']);
    }

    private function buildSectionTemplate(string $title, array $current, array $comparison, array $categories): array
    {
        $section = [
            'title' => $title,
            'lines' => [],
            'total' => 0,
            'comparison_total' => 0,
        ];

        foreach ($categories as $category) {
            $currentAmount = $current[$category]->total_amount ?? 0;
            $comparisonAmount = $comparison[$category]->total_amount ?? 0;

            $section['lines'][] = [
                'account_category' => $category,
                'current_amount' => $currentAmount,
                'comparison_amount' => $comparisonAmount,
            ];

            $section['total'] += $currentAmount;
            $section['comparison_total'] += $comparisonAmount;
        }

        return $section;
    }

    /**
     * Apply currency conversion to financial statement data
     */
    protected function applyCurrencyConversion(array $data, string $companyId, string $targetCurrency): array
    {
        // If no currency conversion is needed, return data as-is
        if (empty($data)) {
            return $data;
        }

        // Get company base currency
        $baseCurrency = $this->getCompanyBaseCurrency($companyId);
        
        if ($baseCurrency === $targetCurrency) {
            return $data;
        }

        // Convert monetary values in the data
        foreach ($data as &$item) {
            if (isset($item['amount']) && is_numeric($item['amount'])) {
                $conversion = $this->currencyService->convertAmount(
                    $item['amount'],
                    $baseCurrency,
                    $targetCurrency
                );
                $item['amount'] = $conversion['converted_amount'];
                $item['original_amount'] = $conversion['original_amount'];
                $item['exchange_rate'] = $conversion['exchange_rate'];
            }
        }

        return $data;
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

    /**
     * Parse date range from parameters
     */
    protected function parseDateRange(array $parameters): array
    {
        if (isset($parameters['date_range'])) {
            return [
                'start' => $parameters['date_range']['start'],
                'end' => $parameters['date_range']['end'],
            ];
        }

        // Default to current month
        return [
            'start' => now()->startOfMonth()->toDateString(),
            'end' => now()->endOfMonth()->toDateString(),
        ];
    }

    /**
     * Get comparison date range
     */
    protected function getComparisonDateRange(array $dateRange, string $comparison): array
    {
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

    /**
     * Get comparison date for balance sheet
     */
    protected function getComparisonDateForBalanceSheet(string $date, string $comparison): string
    {
        $date = Carbon::parse($date);

        switch ($comparison) {
            case 'prior_period':
                return $date->subYear()->toDateString();
            case 'prior_year':
                return $date->subYear()->toDateString();
            default:
                return $date->subYear()->toDateString();
        }
    }
}
