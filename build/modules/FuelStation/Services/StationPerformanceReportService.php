<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

        // Posted cost corrections (fuel:recost-closes) laid over each close's own figures.
        app(DailyCloseCostCorrectionService::class)->applyTo($companyId, $transactions);

        // Net profit is the ledger's, the same figure the Profit & Loss shows for these days: it
        // also carries tank gains and losses, discounts, card charges, other income and
        // anything booked outside the close. Worked out here as gross minus expenses minus
        // payroll paid out, it disagreed with the P&L every day.
        $ledgerNet = $product === 'all' ? $this->ledgerNetByDate($companyId, $startDate, $endDate) : [];

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
            $netStationProfit = $product === 'all'
                ? (float) ($ledgerNet[$date->toDateString()] ?? 0)
                : $grossProfit;

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
            $periods[$periodKey]['other'] += $netStationProfit - ($grossProfit - $expenses);
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

        // All products: every money column from the books, so each row adds up by construction.
        if ($product === 'all') {
            $this->applyBooks($companyId, $startDate, $endDate, $groupBy, $periods);
            ksort($periods);
        }

        // Informational: how purchase-rate changes moved the stock in the tanks. Never booked.
        $this->applyPriceEffect($companyId, $startDate, $endDate, $groupBy, $product, $periods);
        ksort($periods);

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

        $valuationMethod = DB::table('fuel.station_settings')->where('company_id', $companyId)
            ->value('month_end_stock_valuation') ?: 'inventory_cost';
        $monthlyFuelProfit = [];
        if ($groupBy === 'month') {
            for ($month = Carbon::parse($startDate)->startOfMonth(); $month->toDateString() <= $endDate; $month->addMonth()) {
                if ($month->toDateString() < $startDate || $month->copy()->endOfMonth()->toDateString() > $endDate) {
                    continue;
                }
                $saved = DB::table('fuel.month_profit_snapshots')->where('company_id', $companyId)
                    ->where('month', $month->toDateString())->whereNull('reopened_at')->exists();
                if ($valuationMethod !== 'next_month_purchase_rate' && ! $saved) {
                    continue;
                }
                $monthlyFuelProfit[] = app(MonthlyFuelProfitService::class)->run($companyId, $month->format('Y-m'), $product);
            }
        }

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
            'monthEndStockValuation' => $valuationMethod,
            'monthlyFuelProfit' => $monthlyFuelProfit,
        ];
    }

    /**
     * The period's money from the books, in four parts that add up to the books' profit (the
     * Profit & Loss figure). The classification is the profit statement's own
     * (ProfitStatementService), so this screen and the home page's statement agree:
     *  - Sales: income on the products' own sales accounts.
     *  - Cost of sales: the products' cost accounts, cost corrections and month-end write-down
     *    included, plus tank losses (gains reduce it).
     *  - Expenses: expense accounts hit by expense entries (Money out > Expenses). Fixed assets
     *    bought that way are assets, not here.
     *  - Other: salaries, other income and other costs together (discounts, card charges,
     *    short/over, rent, fines), signed as income.
     * Reversals count on their own date, as the books have them.
     *
     * @param array<string,array<string,mixed>> $periods
     */
    private function applyBooks(string $companyId, string $startDate, string $endDate, string $groupBy, array &$periods): void
    {
        $books = app(ProfitStatementService::class)->periodBooks(
            $companyId, $startDate, $endDate, fn (Carbon $date) => $this->periodKey($date, $groupBy),
        );
        foreach ($books as $key => $_) {
            $periods[$key] ??= $this->emptyPeriodRow($key, $this->periodLabel(Carbon::parse($groupBy === 'month' ? $key.'-01' : $key), $groupBy));
        }

        foreach ($periods as $key => &$row) {
            $parts = $books[$key] ?? ['sales' => [], 'cost' => [], 'expenses' => [], 'other' => []];
            $sales = array_sum(array_column($parts['sales'], 'amount'));
            $cost = array_sum(array_column($parts['cost'], 'amount'));
            $expenses = array_sum(array_column($parts['expenses'], 'amount'));
            $other = array_sum(array_column($parts['other'], 'amount'));
            $row['revenue'] = $sales;
            $row['cogs'] = $cost;
            $row['gross_profit'] = $sales - $cost;
            $row['expenses'] = $expenses;
            $row['other'] = $other;
            $row['net_station_profit'] = $sales - $cost - $expenses + $other;
            $row['sales_lines'] = $parts['sales'];
            $row['cost_lines'] = $parts['cost'];
            $row['expense_lines'] = $parts['expenses'];
            $row['other_lines'] = $parts['other'];
        }
        unset($row);
    }

    /**
     * The price effect, per period: what the fuel in the tanks is worth at the next day's purchase
     * rate beyond its book cost, as it changed since the previous close. Not in the books.
     *   U(D) = closing dip litres x (purchase rate on D+1 - book cost carried out of D)
     *   E(D) = U(D) - U(previous close day); a period sums its days' E.
     * An item with no cost or no rate counts as 0 for that day.
     *
     * @param array<string,array<string,mixed>> $periods
     */
    private function applyPriceEffect(string $companyId, string $startDate, string $endDate, string $groupBy, string $product, array &$periods): void
    {
        $closeDates = DB::table('acct.transactions')
            ->where('company_id', $companyId)->where('transaction_type', 'fuel_daily_close')
            ->whereIn('status', ['posted', 'locked'])->whereNull('deleted_at')->whereNull('reversed_by_id')
            ->whereDate('transaction_date', '<=', $endDate)
            ->selectRaw('DISTINCT transaction_date::date AS d')->orderBy('d')->pluck('d')
            ->map(fn ($d) => substr((string) $d, 0, 10))->all();
        $inRange = array_values(array_filter($closeDates, fn ($d) => $d >= $startDate));
        if ($inRange === []) {
            return;
        }
        $before = array_values(array_filter($closeDates, fn ($d) => $d < $startDate));
        $seed = $before === [] ? null : end($before);
        $chain = $seed === null ? $inRange : array_merge([$seed], $inRange);

        $items = DB::table('inv.warehouses as w')->join('inv.items as i', 'i.id', '=', 'w.linked_item_id')
            ->where('w.company_id', $companyId)->where('w.warehouse_type', 'tank')->whereNull('w.deleted_at')
            ->distinct()->get(['i.id', 'i.name', 'i.fuel_category'])
            ->filter(fn ($i) => $product === 'all' || ($i->fuel_category ?: str($i->name)->slug('-')->toString()) === $product)
            ->values();
        if ($items->isEmpty()) {
            return;
        }
        $itemIds = $items->pluck('id')->all();

        $dips = [];
        DB::table('fuel.tank_readings')->where('company_id', $companyId)->whereIn('item_id', $itemIds)
            ->whereDate('reading_date', '>=', $chain[0])->whereDate('reading_date', '<=', $endDate)
            ->groupBy('item_id', DB::raw('reading_date::date'))
            ->selectRaw('item_id, reading_date::date AS d, SUM(dip_measurement_liters) AS q')->get()
            ->each(function ($r) use (&$dips) {
                $dips[$r->item_id][substr((string) $r->d, 0, 10)] = (float) $r->q;
            });

        $rates = DB::table('fuel.rate_changes')->where('company_id', $companyId)->whereIn('item_id', $itemIds)
            ->orderBy('effective_date')->get(['item_id', 'effective_date', 'purchase_rate'])->groupBy('item_id');

        $costs = new FuelCostService();
        $prevU = array_fill_keys($itemIds, 0.0);
        $effects = []; // period key => item id => ['effect' => float, 'line' => ?array]
        foreach ($chain as $day) {
            $next = Carbon::parse($day)->addDay()->toDateString();
            foreach ($items as $item) {
                $q = (float) ($dips[$item->id][$day] ?? 0);
                $cost = $costs->costAtEndOf($companyId, $item->id, $day);
                $rate = null;
                foreach ($rates->get($item->id, collect()) as $r) {
                    if (substr((string) $r->effective_date, 0, 10) <= $next) {
                        $rate = (float) $r->purchase_rate;
                    }
                }
                $usable = $cost !== null && $cost > 0 && $rate !== null;
                $u = $usable ? round($q * ($rate - $cost), 2) : 0.0;
                $e = $u - $prevU[$item->id];
                $prevU[$item->id] = $u;
                if ($day === $seed) {
                    continue; // the seed day only anchors the first day in range
                }
                $key = $this->periodKey(Carbon::parse($day), $groupBy);
                $effects[$key][$item->id] ??= ['effect' => 0.0, 'line' => null];
                $effects[$key][$item->id]['effect'] += $e;
                $effects[$key][$item->id]['line'] = $usable
                    ? ['name' => $item->name, 'quantity' => $q, 'cost' => $cost, 'rate' => $rate, 'value' => $u]
                    : null;
            }
        }

        foreach ($effects as $key => $perItem) {
            $periods[$key] ??= $this->emptyPeriodRow($key, $this->periodLabel(Carbon::parse($groupBy === 'month' ? $key.'-01' : $key), $groupBy));
            $lines = [];
            foreach ($items as $item) {
                $slot = $perItem[$item->id] ?? null;
                if (! $slot || (abs($slot['effect']) < 0.005 && $slot['line'] === null)) {
                    continue;
                }
                $lines[] = ($slot['line'] ?? ['name' => $item->name, 'quantity' => 0.0, 'cost' => 0.0, 'rate' => 0.0, 'value' => 0.0])
                    + ['effect' => round($slot['effect'], 2)];
            }
            $periods[$key]['price_effect'] = round(array_sum(array_column($lines, 'effect')), 2);
            $periods[$key]['price_effect_lines'] = $lines;
        }
    }

    /**
     * Profit per business date from the ledger: income less costs on every posted journal.
     *
     * @return array<string,float>
     */
    private function ledgerNetByDate(string $companyId, string $startDate, string $endDate): array
    {
        return DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)
            ->where('t.status', 'posted')
            ->whereNull('t.deleted_at')
            ->whereBetween('t.transaction_date', [$startDate, $endDate])
            ->whereIn('a.type', ['revenue', 'other_income', 'expense', 'cogs', 'other_expense'])
            ->groupBy(DB::raw('t.transaction_date::date'))
            ->selectRaw('t.transaction_date::date AS d, SUM(je.credit_amount) - SUM(je.debit_amount) AS net')
            ->pluck('net', 'd')
            ->mapWithKeys(fn ($net, $d) => [substr((string) $d, 0, 10) => (float) $net])
            ->all();
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
            'other' => 0.0,
            'price_effect' => 0.0,
            'price_effect_lines' => [],
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
            'other' => array_sum(array_column($rows, 'other')),
            'price_effect' => array_sum(array_column($rows, 'price_effect')),
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
