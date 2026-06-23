<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Transaction;
use Carbon\Carbon;
class StationPerformanceReportService
{
    /**
     * @return array{
     *   filters: array{start_date:string,end_date:string,group_by:string,product:string},
     *   totals: array<string,float|int>,
     *   rows: array<int,array<string,mixed>>,
     *   productRows: array<int,array<string,mixed>>,
     *   productOptions: array<int,array{key:string,name:string}>,
     *   cashRows: array<int,array<string,mixed>>,
     *   movementTotals: array<string,float>
     * }
     */
    public function run(string $companyId, string $startDate, string $endDate, string $groupBy = 'day', string $product = 'all'): array
    {
        $groupBy = in_array($groupBy, ['day', 'week', 'month'], true) ? $groupBy : 'day';

        $transactions = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->where('status', 'posted')
            ->whereNull('deleted_at')
            ->whereNull('reversed_by_id')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')
            ->get(['id', 'transaction_number', 'transaction_date', 'metadata', 'is_locked']);

        $periods = [];
        $products = [];
        $productOptions = [];
        $cashRows = [];
        $movementTotals = $this->emptyMovementTotals();

        foreach ($transactions as $transaction) {
            $metadata = $this->metadata($transaction->metadata);
            $date = $transaction->transaction_date instanceof Carbon
                ? $transaction->transaction_date->copy()
                : Carbon::parse($transaction->transaction_date);

            foreach ($this->filteredFuelSales($metadata['fuel_sales'] ?? [], 'all') as $productKey => $sale) {
                if (!isset($productOptions[$productKey])) {
                    $productOptions[$productKey] = [
                        'key' => $productKey,
                        'name' => $this->label($productKey),
                    ];
                }
            }

            $sales = $this->filteredFuelSales($metadata['fuel_sales'] ?? [], $product);
            $fuelRevenue = array_sum(array_column($sales, 'revenue'));
            $fuelCogs = array_sum(array_column($sales, 'cogs'));
            $liters = array_sum(array_column($sales, 'liters'));
            $otherSales = $product === 'all' ? (float) ($metadata['other_sales'] ?? 0) : 0.0;
            $revenue = $fuelRevenue + $otherSales;
            $cogs = $fuelCogs;
            $grossProfit = $revenue - $cogs;
            $expenses = (float) ($metadata['expenses'] ?? 0);
            $cashBillPayments = (float) ($metadata['cash_bill_payments'] ?? 0);
            $payrollPayouts = (float) ($metadata['payroll_payouts'] ?? 0);
            $netStationProfit = $grossProfit - $expenses - $payrollPayouts;

            $periodKey = $this->periodKey($date, $groupBy);
            if (!isset($periods[$periodKey])) {
                $periods[$periodKey] = $this->emptyPeriodRow($periodKey, $this->periodLabel($date, $groupBy));
            }

            $periods[$periodKey]['days_count']++;
            $periods[$periodKey]['daily_close_ids'][] = $transaction->id;
            $periods[$periodKey]['daily_close_numbers'][] = $transaction->transaction_number;
            $periods[$periodKey]['liters'] += $liters;
            $periods[$periodKey]['revenue'] += $revenue;
            $periods[$periodKey]['fuel_revenue'] += $fuelRevenue;
            $periods[$periodKey]['other_sales'] += $otherSales;
            $periods[$periodKey]['cogs'] += $cogs;
            $periods[$periodKey]['gross_profit'] += $grossProfit;
            $periods[$periodKey]['expenses'] += $expenses;
            $periods[$periodKey]['payroll_payouts'] += $payrollPayouts;
            $periods[$periodKey]['net_station_profit'] += $netStationProfit;
            $periods[$periodKey]['cash_variance'] += (float) ($metadata['variance'] ?? 0);
            $periods[$periodKey]['stock_loss'] += (float) ($metadata['total_shrinkage'] ?? 0);
            $periods[$periodKey]['stock_gain'] += (float) ($metadata['total_gain'] ?? 0);
            $periods[$periodKey]['purchases_paid'] += (float) ($metadata['bill_payments'] ?? 0);
            $periods[$periodKey]['closing_cash'] = (float) ($metadata['closing_cash'] ?? 0);

            foreach ($sales as $productKey => $sale) {
                if (!isset($products[$productKey])) {
                    $products[$productKey] = [
                        'key' => $productKey,
                        'name' => $this->label($productKey),
                        'liters' => 0.0,
                        'revenue' => 0.0,
                        'cogs' => 0.0,
                        'gross_profit' => 0.0,
                    ];
                }

                $products[$productKey]['liters'] += (float) ($sale['liters'] ?? 0);
                $products[$productKey]['revenue'] += (float) ($sale['revenue'] ?? 0);
                $products[$productKey]['cogs'] += (float) ($sale['cogs'] ?? 0);
                $products[$productKey]['gross_profit'] = $products[$productKey]['revenue'] - $products[$productKey]['cogs'];
            }

            $cashRows[] = [
                'date' => $date->toDateString(),
                'label' => $date->format('d M Y'),
                'transaction_id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'opening_cash' => (float) ($metadata['opening_cash'] ?? 0),
                'cash_sales' => max(0, $revenue - (float) ($metadata['bank_transfers_received'] ?? 0) - (float) ($metadata['card_swipes'] ?? 0) - (float) ($metadata['fuel_cards'] ?? 0)),
                'money_in' => (float) ($metadata['partner_deposits'] ?? 0) + (float) ($metadata['amanat_deposits'] ?? 0) + (float) ($metadata['other_deposits'] ?? 0),
                'money_out' => (float) ($metadata['bank_deposits'] ?? 0)
                    + (float) ($metadata['partner_withdrawals'] ?? 0)
                    + (float) ($metadata['employee_advances'] ?? 0)
                    + $payrollPayouts
                    + (float) ($metadata['amanat_disbursements'] ?? 0)
                    + $expenses
                    + $cashBillPayments,
                'expected_closing' => (float) ($metadata['expected_closing'] ?? 0),
                'closing_cash' => (float) ($metadata['closing_cash'] ?? 0),
                'variance' => (float) ($metadata['variance'] ?? 0),
            ];

            $this->addMovements($movementTotals, $metadata);
        }

        $rows = array_values($periods);
        foreach ($rows as &$row) {
            $row['gross_margin_percent'] = $row['revenue'] > 0 ? ($row['gross_profit'] / $row['revenue']) * 100 : 0;
            $row['avg_rate'] = $row['liters'] > 0 ? $row['fuel_revenue'] / $row['liters'] : 0;
            $row['daily_close_count'] = count($row['daily_close_ids']);
            $row['detail_url_id'] = $row['daily_close_count'] === 1 ? $row['daily_close_ids'][0] : null;
        }
        unset($row);

        $productRows = array_values($products);
        foreach ($productRows as &$productRow) {
            $productRow['avg_rate'] = $productRow['liters'] > 0 ? $productRow['revenue'] / $productRow['liters'] : 0;
            $productRow['margin_per_liter'] = $productRow['liters'] > 0 ? $productRow['gross_profit'] / $productRow['liters'] : 0;
            $productRow['gross_margin_percent'] = $productRow['revenue'] > 0 ? ($productRow['gross_profit'] / $productRow['revenue']) * 100 : 0;
        }
        unset($productRow);

        usort($productRows, fn (array $a, array $b) => $b['revenue'] <=> $a['revenue']);

        return [
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'group_by' => $groupBy,
                'product' => $product,
            ],
            'totals' => $this->totals($rows),
            'rows' => $rows,
            'productRows' => $productRows,
            'productOptions' => array_values($productOptions),
            'cashRows' => $cashRows,
            'movementTotals' => $movementTotals,
        ];
    }

    /**
     * @param mixed $metadata
     * @return array<string,mixed>
     */
    private function metadata(mixed $metadata): array
    {
        return is_array($metadata) ? $metadata : [];
    }

    /**
     * @param mixed $fuelSales
     * @return array<string,array{liters:float,revenue:float,cogs:float}>
     */
    private function filteredFuelSales(mixed $fuelSales, string $product): array
    {
        if (!is_array($fuelSales)) {
            return [];
        }

        $sales = [];
        foreach ($fuelSales as $key => $sale) {
            if (!is_array($sale)) {
                continue;
            }
            if ($product !== 'all' && $key !== $product) {
                continue;
            }
            $sales[$key] = [
                'liters' => (float) ($sale['liters'] ?? 0),
                'revenue' => (float) ($sale['revenue'] ?? 0),
                'cogs' => (float) ($sale['cogs'] ?? 0),
            ];
        }

        return $sales;
    }

    private function periodKey(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => $date->copy()->startOfWeek()->toDateString(),
            'month' => $date->format('Y-m'),
            default => $date->toDateString(),
        };
    }

    private function periodLabel(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => 'Week of ' . $date->copy()->startOfWeek()->format('d M Y'),
            'month' => $date->format('F Y'),
            default => $date->format('d M Y'),
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyPeriodRow(string $key, string $label): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'days_count' => 0,
            'daily_close_ids' => [],
            'daily_close_numbers' => [],
            'liters' => 0.0,
            'revenue' => 0.0,
            'fuel_revenue' => 0.0,
            'other_sales' => 0.0,
            'cogs' => 0.0,
            'gross_profit' => 0.0,
            'gross_margin_percent' => 0.0,
            'avg_rate' => 0.0,
            'expenses' => 0.0,
            'payroll_payouts' => 0.0,
            'net_station_profit' => 0.0,
            'cash_variance' => 0.0,
            'stock_loss' => 0.0,
            'stock_gain' => 0.0,
            'purchases_paid' => 0.0,
            'closing_cash' => 0.0,
            'daily_close_count' => 0,
            'detail_url_id' => null,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,float|int>
     */
    private function totals(array $rows): array
    {
        $totals = [
            'days' => array_sum(array_column($rows, 'days_count')),
            'liters' => array_sum(array_column($rows, 'liters')),
            'revenue' => array_sum(array_column($rows, 'revenue')),
            'fuel_revenue' => array_sum(array_column($rows, 'fuel_revenue')),
            'other_sales' => array_sum(array_column($rows, 'other_sales')),
            'cogs' => array_sum(array_column($rows, 'cogs')),
            'gross_profit' => array_sum(array_column($rows, 'gross_profit')),
            'expenses' => array_sum(array_column($rows, 'expenses')),
            'payroll_payouts' => array_sum(array_column($rows, 'payroll_payouts')),
            'net_station_profit' => array_sum(array_column($rows, 'net_station_profit')),
            'cash_variance' => array_sum(array_column($rows, 'cash_variance')),
            'stock_loss' => array_sum(array_column($rows, 'stock_loss')),
            'stock_gain' => array_sum(array_column($rows, 'stock_gain')),
            'purchases_paid' => array_sum(array_column($rows, 'purchases_paid')),
            'closing_cash' => empty($rows) ? 0 : (float) end($rows)['closing_cash'],
        ];

        $totals['gross_margin_percent'] = $totals['revenue'] > 0
            ? ($totals['gross_profit'] / $totals['revenue']) * 100
            : 0;

        return $totals;
    }

    /**
     * @return array<string,float>
     */
    private function emptyMovementTotals(): array
    {
        return [
            'partner_deposits' => 0.0,
            'amanat_deposits' => 0.0,
            'other_deposits' => 0.0,
            'payment_receipts' => 0.0,
            'bank_deposits' => 0.0,
            'partner_withdrawals' => 0.0,
            'employee_advances' => 0.0,
            'payroll_payouts' => 0.0,
            'amanat_disbursements' => 0.0,
            'expenses' => 0.0,
            'bill_payments' => 0.0,
        ];
    }

    /**
     * @param array<string,float> $movementTotals
     * @param array<string,mixed> $metadata
     */
    private function addMovements(array &$movementTotals, array $metadata): void
    {
        foreach ($movementTotals as $key => $value) {
            if ($key === 'payment_receipts') {
                $movementTotals[$key] += (float) ($metadata['bank_transfers_received'] ?? 0)
                    + (float) ($metadata['card_swipes'] ?? 0)
                    + (float) ($metadata['fuel_cards'] ?? 0);
                continue;
            }

            $movementTotals[$key] += (float) ($metadata[$key] ?? 0);
        }
    }

    private function label(string $key): string
    {
        return str($key)->replace(['_', '-'], ' ')->title()->toString();
    }
}
