<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\PayablesAgingReportService;
use App\Modules\Accounting\Services\ReceivablesAgingReportService;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Services\MonthEndPayrollDraft;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * What a fuel station's home page shows: where today's close stands, the money, the tanks, the
 * month so far, the rates and what needs doing -- and the same figures for any past range.
 *
 * Nothing here is calculated afresh. Each block reads the service that owns that figure (month
 * summary, stock statement, product profitability, expense report, aging reports), so the home
 * page cannot disagree with the report it links to.
 */
class FuelHomeService
{
    private const POSTED = ['posted', 'locked'];

    /** Days of closes the "days of stock left" average is taken over. */
    private const AVERAGE_CLOSES = 7;

    public function today(Company $company): array
    {
        $id = $company->id;
        $slug = $company->slug;
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $todayDate = $today->toDateString();

        $summary = app(DailyCloseMonthSummaryService::class)->run($id, $today->format('Y-m'));
        $closes = $this->close($company, $summary);
        $payroll = $this->payroll($company);
        $money = $this->money($company, $monthStart, $todayDate);
        $tanks = $this->tanks($company, $closes['last_date'], $closes['last_id'], $monthStart, $todayDate);
        $stock = $this->stockRuns($id, $monthStart, $todayDate);
        $fig = $this->figures($id, $monthStart, $todayDate, $stock, $slug);
        $overdue = $money['overdue'];

        $month = [
            'label' => $today->format('F Y'),
            'sales' => $this->saleLinks($slug, $fig['sales'], $monthStart, $todayDate),
            'products' => $fig['products'],
            'expense_accounts' => $fig['expense_accounts'],
            'purchase_products' => $fig['purchase_products'],
            'sales_total' => (float) ($summary['sales_total'] ?? 0),
            'gross_profit' => $fig['gross_profit'],
            'expenses' => $fig['expenses'],
            'short_over' => (float) ($summary['cash']['short_over'] ?? 0),
            'purchases' => $fig['purchases'],
            'revenue' => $fig['revenue'],
            'cogs' => $fig['cogs'],
            'short_days' => (int) ($summary['cash']['short_days'] ?? 0),
            'over_days' => (int) ($summary['cash']['over_days'] ?? 0),
            'links' => [
                'month_summary' => "/{$slug}/fuel/daily-close/month?month=".$today->format('Y-m'),
                'stock_statement' => $this->stockLink($slug, $monthStart, $todayDate),
                'expenses' => "/{$slug}/fuel/reports/expenses?start_date={$monthStart}&end_date={$todayDate}",
                'profit' => "/{$slug}/fuel/reports/product-profitability?start_date={$monthStart}&end_date={$todayDate}",
            ],
        ];

        return [
            'as_of' => $todayDate,
            'close' => $closes,
            'payroll' => $payroll,
            'money' => $money,
            'tanks' => $tanks,
            'month' => $month,
            'rates' => $this->rates($company, $todayDate),
            'attention' => $this->attention($company, $closes, $payroll, $tanks, $stock, $overdue, $monthStart, $todayDate),
        ];
    }

    public function period(Company $company, string $from, string $to): array
    {
        $id = $company->id;
        $slug = $company->slug;
        $today = Carbon::today();

        $stock = $this->stockRuns($id, $from, $to);
        $fig = $this->figures($id, $from, $to, $stock, $slug);

        $rows = $this->liveCloses($id)
            ->whereBetween('transaction_date', [$from, $to])
            ->orderBy('transaction_date')->orderBy('created_at')
            ->get(['id', 'transaction_date', 'metadata']);
        $byDate = [];
        foreach ($rows as $t) {
            $byDate[Carbon::parse($t->transaction_date)->toDateString()] = $t;
        }
        $shortOver = 0.0;
        $shortDays = 0;
        $overDays = 0;
        foreach ($byDate as $t) {
            $m = (array) ($t->metadata ?? []);
            $v = (float) (($m['posting_snapshot']['totals']['variance'] ?? null) ?? ($m['variance'] ?? 0));
            $shortOver += $v;
            $shortDays += round($v) < 0 ? 1 : 0;
            $overDays += round($v) > 0 ? 1 : 0;
        }
        $lastInRange = $byDate ? end($byDate) : null;
        $sameMonth = Carbon::parse($from)->format('Y-m') === Carbon::parse($to)->format('Y-m');

        $lastDay = Carbon::parse($to)->min($today);
        $days = Carbon::parse($from)->gt($lastDay) ? 0 : (int) Carbon::parse($from)->diffInDays($lastDay) + 1;

        return [
            'from' => $from,
            'to' => $to,
            'sales' => $this->saleLinks($slug, $fig['sales'], $from, $to),
            'products' => $fig['products'],
            'expense_accounts' => $fig['expense_accounts'],
            'purchase_products' => $fig['purchase_products'],
            'sales_total' => $fig['sales_total'],
            'gross_profit' => $fig['gross_profit'],
            'expenses' => $fig['expenses'],
            'short_over' => $shortOver,
            'short_days' => $shortDays,
            'over_days' => $overDays,
            'revenue' => $fig['revenue'],
            'cogs' => $fig['cogs'],
            'purchases' => $fig['purchases'],
            'stock' => $this->stockTable($stock, $slug, $from, $to, $lastInRange ? "/{$slug}/fuel/daily-close/{$lastInRange->id}" : null),
            'money' => $this->money($company, $from, $to),
            'closes' => ['count' => count($byDate), 'days' => $days],
            'links' => [
                'short_over' => $sameMonth ? "/{$slug}/fuel/daily-close/month?month=".Carbon::parse($from)->format('Y-m') : null,
                'stock_variance' => "/{$slug}/fuel/reports/stock-variance?start_date={$from}&end_date={$to}",
                'month_summary' => "/{$slug}/fuel/daily-close/month?month=".Carbon::parse($from)->format('Y-m'),
                'stock_statement' => $this->stockLink($slug, $from, $to),
                'expenses' => "/{$slug}/fuel/reports/expenses?start_date={$from}&end_date={$to}",
                'profit_loss' => "/{$slug}/reports/profit-loss?start={$from}&end={$to}",
                'profit' => "/{$slug}/fuel/reports/product-profitability?start_date={$from}&end_date={$to}",
            ],
        ];
    }

    // ---- blocks -------------------------------------------------------------------------------

    private function close(Company $company, array $summary): array
    {
        $id = $company->id;
        $today = Carbon::today();

        $last = $this->liveCloses($id)->orderByDesc('transaction_date')->orderByDesc('created_at')
            ->first(['id', 'transaction_date', 'metadata', 'posted_at', 'posted_by_user_id']);
        $first = $this->liveCloses($id)->min('transaction_date');
        $lastDate = $last ? Carbon::parse($last->transaction_date)->toDateString() : null;
        $nextDate = $lastDate ? Carbon::parse($lastDate)->addDay()->toDateString() : $today->toDateString();
        $nextHas = $this->liveCloses($id)->whereDate('transaction_date', $nextDate)->exists();

        $parked = DB::table('fuel.daily_close_drafts')->where('company_id', $id)
            ->orderBy('business_date')->pluck('business_date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->values()->all();

        // Today is not missing yet -- it is closed the next morning. Days before the station's
        // first close are not missing either.
        $firstDate = $first ? Carbon::parse($first)->toDateString() : null;
        $missing = $firstDate === null ? [] : array_values(array_filter(
            (array) ($summary['missing_dates'] ?? []),
            fn ($d) => $d < $today->toDateString() && $d > $firstDate,
        ));

        $lastMeta = (array) ($last?->metadata ?? []);
        $counted = ($lastMeta['posting_snapshot']['totals']['closing_cash'] ?? null) ?? ($lastMeta['closing_cash'] ?? null);
        $postedBy = null;
        if ($last?->posted_by_user_id) {
            try {
                $postedBy = DB::table('auth.users')->where('id', $last->posted_by_user_id)->value('name');
            } catch (\Throwable $e) {
                $postedBy = null;
            }
        }

        return [
            'last_date' => $lastDate,
            'last_id' => $last?->id,
            'last_posted_at' => $last?->posted_at ? Carbon::parse($last->posted_at)->format('Y-m-d H:i') : null,
            'last_posted_by' => $postedBy,
            'last_counted_cash' => $counted !== null ? (float) $counted : null,
            'first_date' => $firstDate,
            'next_date' => $nextDate,
            'next_has_close' => $nextHas,
            'next_is_due' => $nextDate <= $today->toDateString(),
            'parked_dates' => $parked,
            'missing_dates' => $missing,
        ];
    }

    private function payroll(Company $company): array
    {
        $out = ['enabled' => false, 'reminder' => null, 'owed_count' => 0, 'owed_total' => 0.0, 'owed' => [], 'owed_month' => null];
        if (! $company->isModuleEnabled('payroll')) {
            return $out;
        }

        try {
            $out['enabled'] = true;
            $out['reminder'] = app(MonthEndPayrollDraft::class)->reminder($company);
            DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
            $owed = Payslip::where('company_id', $company->id)
                ->where('status', 'approved')
                ->whereNull('paid_at')
                ->where('net_pay', '>', 0);
            $out['owed_count'] = (clone $owed)->count();
            $out['owed_total'] = (float) (clone $owed)->sum('net_pay');
            $list = (clone $owed)->with(['employee:id,first_name,last_name', 'payrollPeriod:id,period_start'])->get();
            $out['owed'] = $list->map(fn ($p) => [
                'name' => trim(($p->employee?->first_name ?? '').' '.($p->employee?->last_name ?? '')) ?: 'Employee',
                'amount' => (float) $p->net_pay,
            ])->sortByDesc('amount')->values()->all();
            $oldest = $list->map(fn ($p) => $p->payrollPeriod?->period_start)->filter()->map(fn ($d) => Carbon::parse($d))->sort()->first();
            $out['owed_month'] = $oldest?->format('Y-m');
        } catch (\Throwable $e) {
            Log::warning('Fuel home payroll block failed', ['company_id' => $company->id, 'error' => $e->getMessage()]);
        }

        return $out;
    }

    /**
     * Cash, each bank, what customers owe, what is owed to suppliers, amanat held, all as of a date.
     * $from is where the statements the figures link to begin.
     */
    private function money(Company $company, string $from, string $asOf): array
    {
        $id = $company->id;
        $slug = $company->slug;

        $cashId = app(DailyCloseService::class)->cashAccountId($id);
        $banks = Account::where('company_id', $id)->whereNull('deleted_at')->where('is_active', true)
            ->where('subtype', 'bank')->orderBy('code')->get(['id', 'code', 'name']);
        $amanatId = Account::where('company_id', $id)->whereNull('deleted_at')->where('is_active', true)
            ->where('code', '2200')->value('id');

        $ids = array_values(array_filter(array_merge([$cashId, $amanatId], $banks->pluck('id')->all())));
        $balance = $this->ledgerBalances($id, $ids, $asOf);

        $range = "from={$from}&to={$asOf}";
        $statement = fn (string $kind, string $partyId) => "/{$slug}/reports/statements?kind={$kind}&id={$partyId}&{$range}";

        // The aging reports read each invoice's / bill's current balance, so as of a past date
        // they show what was then billed and is still unpaid today, not a time-travelled ledger.
        $receivables = app(ReceivablesAgingReportService::class)->run($id, $asOf);
        $payables = app(PayablesAgingReportService::class)->run($id, $asOf);

        $unpaid = DB::table('acct.bills')->where('company_id', $id)->whereNull('deleted_at')
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])
            ->where('balance', '>', 0)
            ->whereDate('bill_date', '<=', $asOf);
        $oldest = (clone $unpaid)->orderBy('bill_date')->orderBy('created_at')->first(['id', 'bill_number', 'bill_date']);

        $holders = (int) DB::table('fuel.customer_profiles')->where('company_id', $id)
            ->where('is_amanat_holder', true)->count();

        return [
            'as_of' => $asOf,
            'cash' => $cashId ? ($balance[$cashId] ?? 0.0) : null,
            'cash_href' => $cashId ? $statement('bank', $cashId) : null,
            'banks' => $banks->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'code' => $b->code,
                'balance' => $balance[$b->id] ?? 0.0,
                'href' => $statement('bank', $b->id),
            ])->values()->all(),
            'receivable' => (float) $receivables['totals']['total'],
            'receivable_href' => $statement('customer', 'all'),
            'receivable_aging_href' => "/{$slug}/reports/receivables-aging",
            'receivable_customers' => count($receivables['rows']),
            'overdue' => $this->overdueCustomers($receivables),
            'payable' => (float) $payables['totals']['total'],
            'payable_href' => $statement('supplier', 'all'),
            'payable_aging_href' => "/{$slug}/reports/payables-aging",
            'unpaid_bills' => (int) (clone $unpaid)->count(),
            'oldest_bill' => $oldest ? [
                'number' => $oldest->bill_number,
                'date' => substr((string) $oldest->bill_date, 0, 10),
                'href' => "/{$slug}/bills/{$oldest->id}",
            ] : null,
            'amanat' => $amanatId ? ($balance[$amanatId] ?? 0.0) : null,
            'amanat_href' => $statement('amanat', 'all'),
            'amanat_holders' => $holders,
        ];
    }

    private function tanks(Company $company, ?string $lastCloseDate, ?string $lastCloseId, string $from, string $to): array
    {
        $id = $company->id;
        $slug = $company->slug;
        $tanks = Warehouse::where('company_id', $id)->where('warehouse_type', 'tank')->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'capacity', 'linked_item_id']);
        if ($tanks->isEmpty()) {
            return [];
        }

        $itemNames = DB::table('inv.items')->where('company_id', $id)
            ->whereIn('id', $tanks->pluck('linked_item_id')->filter()->all())->pluck('name', 'id')->all();

        // The newest live close per date, last seven days of closing.
        $recent = [];
        foreach ($this->liveCloses($id)->orderByDesc('transaction_date')->orderByDesc('created_at')
            ->limit(40)->get(['id', 'transaction_date', 'metadata']) as $t) {
            $recent[Carbon::parse($t->transaction_date)->toDateString()] ??= (array) ($t->metadata ?? []);
        }
        $recent = array_slice($recent, 0, self::AVERAGE_CLOSES, true);
        $latest = $recent ? reset($recent) : null;

        $levels = [];
        foreach ((array) ($latest['posting_snapshot']['tanks'] ?? []) as $t) {
            if (! empty($t['tank_id'])) {
                $levels[$t['tank_id']] = (float) ($t['physical_liters'] ?? 0);
            }
        }

        $sold = [];
        foreach ($recent as $m) {
            $snap = (array) ($m['posting_snapshot'] ?? []);
            $nozzles = (array) ($snap['nozzles'] ?? []);
            foreach ($nozzles as $z) {
                $tid = $z['tank_id'] ?? null;
                if ($tid) {
                    $sold[$tid] = ($sold[$tid] ?? 0.0) + (isset($z['liters_dispensed'])
                        ? (float) $z['liters_dispensed']
                        : (float) ($z['meter_liters'] ?? 0) - (float) ($z['returned_liters'] ?? 0));
                }
            }
            // A tank with no nozzle (open lubricant) is drawn down by its product's other sales.
            $nozzleTanks = array_column($nozzles, 'tank_id');
            $tankByItem = [];
            foreach ((array) ($snap['tanks'] ?? []) as $t) {
                if (! empty($t['item_id']) && ! empty($t['tank_id']) && ! in_array($t['tank_id'], $nozzleTanks, true)) {
                    $tankByItem[$t['item_id']] = $t['tank_id'];
                }
            }
            foreach ((array) ($m['other_sales_details'] ?? []) as $o) {
                $tid = $tankByItem[$o['item_id'] ?? ''] ?? null;
                if ($tid) {
                    $sold[$tid] = ($sold[$tid] ?? 0.0) + (float) ($o['quantity'] ?? 0);
                }
            }
        }
        $closeCount = count($recent);
        $today = Carbon::today()->toDateString();
        $dailyService = app(DailyCloseService::class);

        return $tanks->map(function ($tank) use ($levels, $sold, $closeCount, $itemNames, $lastCloseDate, $lastCloseId, $today, $id, $slug, $from, $to, $dailyService) {
            $level = $levels[$tank->id] ?? null;
            $capacity = (float) $tank->capacity;
            $average = $closeCount > 0 && isset($sold[$tank->id]) ? $sold[$tank->id] / $closeCount : null;
            $average = $average !== null && $average > 0 ? $average : null;

            $pending = 0.0;
            $pendingBills = [];
            if ($tank->linked_item_id) {
                try {
                    $deliveries = $dailyService
                        ->pendingDeliveries($id, $tank->id, $tank->linked_item_id, $lastCloseDate, $today);
                    $pending = (float) $deliveries->sum('remaining');
                    $pendingBills = $deliveries->groupBy('bill_id')->map(fn ($rows, $billId) => [
                        'number' => $rows->first()['bill_number'],
                        'liters' => (float) $rows->sum('remaining'),
                        'href' => "/{$slug}/bills/{$billId}",
                    ])->values()->all();
                } catch (\Throwable $e) {
                    Log::warning('Fuel home pending deliveries failed', ['tank_id' => $tank->id, 'error' => $e->getMessage()]);
                }
            }

            return [
                'id' => $tank->id,
                'name' => $tank->name,
                'item_name' => $itemNames[$tank->linked_item_id] ?? null,
                'capacity' => $capacity > 0 ? $capacity : null,
                'level' => $level,
                'percent' => $level !== null && $capacity > 0 ? round(min(100, max(0, $level / $capacity * 100)), 1) : null,
                'avg_daily_sold' => $average,
                'days_left' => $level !== null && $average !== null ? round(max(0, $level) / $average, 1) : null,
                'pending_liters' => $pending,
                'pending_bills' => $pendingBills,
                'level_href' => $lastCloseId ? "/{$slug}/fuel/daily-close/{$lastCloseId}" : null,
                'stock_href' => $tank->linked_item_id ? $this->stockItemLink($slug, $tank->linked_item_id, $from, $to) : null,
            ];
        })->values()->all();
    }

    private function rates(Company $company, string $date): array
    {
        $companyId = $company->id;
        $slug = $company->slug;
        $items = DB::table('inv.items')->where('company_id', $companyId)->whereNotNull('fuel_category')
            ->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);
        $lastBills = app(RateChangeService::class)->lastPurchasePrices($companyId, $date);

        return $items->map(function ($item) use ($companyId, $date, $slug, $lastBills) {
            $rate = RateChange::getRateForDate($companyId, $item->id, $date);
            $sale = $rate ? (float) $rate->sale_rate : null;
            $purchase = $rate ? (float) $rate->purchase_rate : null;
            $bill = $lastBills[$item->id] ?? null;

            return [
                'item_id' => $item->id,
                'name' => $item->name,
                'sale_rate' => $sale,
                'purchase_rate' => $purchase,
                'margin' => $sale !== null && $purchase !== null ? round($sale - $purchase, 2) : null,
                'effective_date' => $rate?->effective_date ? Carbon::parse($rate->effective_date)->toDateString() : null,
                'sale_href' => "/{$slug}/fuel/rates",
                'cost_bill' => $bill ? [
                    'number' => $bill['bill_number'],
                    'date' => $bill['bill_date'],
                    'rate' => $bill['rate'],
                    'href' => "/{$slug}/bills/{$bill['bill_id']}",
                ] : null,
            ];
        })->values()->all();
    }

    /** @return array<int, array{label:string, detail:?string, href:string, hint?:array<int,string>}> */
    private function attention(Company $company, array $close, array $payroll, array $tanks, array $stock, array $overdue, string $from, string $to): array
    {
        $slug = $company->slug;
        $items = [];
        $plural = fn (int $n, string $one, string $many) => $n.' '.($n === 1 ? $one : $many);

        if ($close['missing_dates']) {
            $n = count($close['missing_dates']);
            $items[] = [
                'label' => $plural($n, 'close missing', 'closes missing'),
                'hint' => [implode(', ', array_map(fn ($d) => Carbon::parse($d)->format('j M'), array_slice($close['missing_dates'], 0, 10))).($n > 10 ? ' and '.($n - 10).' more' : '')],
                'detail' => 'This month',
                'href' => "/{$slug}/fuel/daily-close/month?month=".Carbon::parse($to)->format('Y-m'),
            ];
        }
        if ($close['parked_dates']) {
            $n = count($close['parked_dates']);
            $items[] = [
                'label' => $plural($n, 'parked close', 'parked closes'),
                'hint' => [implode(', ', array_map(fn ($d) => Carbon::parse($d)->format('j M'), $close['parked_dates']))],
                'detail' => 'Not posted yet',
                'href' => "/{$slug}/fuel/daily-close",
            ];
        }
        if (! empty($payroll['reminder'])) {
            $items[] = [
                'label' => 'Payroll due',
                'detail' => $payroll['reminder']['label'],
                'href' => "/{$slug}/payroll",
            ];
        }
        if (($payroll['owed_count'] ?? 0) > 0) {
            $items[] = [
                'label' => 'Salaries owed',
                'hint' => $this->topList(array_map(fn ($o) => [$o['name'], $o['amount']], (array) ($payroll['owed'] ?? []))),
                'detail' => $plural((int) $payroll['owed_count'], 'payslip', 'payslips').' · '.number_format((float) $payroll['owed_total'], 0),
                'href' => "/{$slug}/payroll".(! empty($payroll['owed_month']) ? '?month='.$payroll['owed_month'] : ''),
            ];
        }

        $negative = array_filter($stock, fn ($p) => ! $p['has_tank'] && $p['totals']['closing'] !== null && (float) $p['totals']['closing'] < -0.0001);
        if ($negative) {
            $n = count($negative);
            $items[] = [
                'label' => $n.' '.($n === 1 ? 'product' : 'products').': purchases not recorded',
                'hint' => $this->topList(array_map(fn ($p) => [$p['name'], (float) $p['totals']['closing']], array_values($negative)), ' short '),
                'detail' => 'Stock below zero',
                'href' => $this->stockLink($slug, $from, $to),
            ];
        }
        if ($overdue['count'] > 0) {
            $items[] = [
                'label' => $plural($overdue['count'], 'customer overdue', 'customers overdue').' 30+ days',
                'hint' => $this->topList($overdue['top']),
                'detail' => number_format($overdue['total'], 0),
                'href' => "/{$slug}/reports/receivables-aging",
            ];
        }
        foreach ($tanks as $t) {
            if ($t['days_left'] !== null && $t['days_left'] < 1) {
                $items[] = [
                    'label' => $t['name'].': under a day left',
                    'hint' => [number_format((float) $t['level'], 0).' L left, about '.number_format((float) $t['avg_daily_sold'], 0).' L sold a day'],
                    'detail' => $t['item_name'],
                    'href' => "/{$slug}/fuel/receipts",
                ];
            }
        }

        return $items;
    }

    // ---- figures over a range -------------------------------------------------------------------

    /** Every sellable product's stock statement for the range: one run each, shared by the blocks. */
    private function stockRuns(string $companyId, string $from, string $to): array
    {
        $statement = app(StockStatementService::class);
        $products = $statement->run($companyId, '', $from, $to)['products'];

        $out = [];
        foreach ($products as $p) {
            $r = $statement->run($companyId, $p['id'], $from, $to);
            $out[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'unit' => $p['unit'] ?? null,
                'has_tank' => (bool) ($r['has_tank'] ?? $p['has_tank'] ?? false),
                'totals' => $r['totals'],
                // Days, for what went straight off the tanker (its invoices and its bills' cost).
                'rows' => $r['rows'],
            ];
        }

        return $out;
    }

    /** Sales per fuel, gross profit, expenses and purchases for the range. */
    private function figures(string $companyId, string $from, string $to, array $stock, string $slug = ''): array
    {
        $profitRun = app(ProductProfitabilityReportService::class)->run($companyId, $from, $to);
        $profit = $profitRun['totals'];
        $expenseRun = app(ExpenseReportService::class)->run($companyId, $from, $to);
        $expenses = $expenseRun['totals'];

        // Each product's sales, cost and profit -- packaged lubricants too -- linked to its statement.
        $itemByName = [];
        foreach ($stock as $p) {
            $itemByName[$p['name']] = $p['id'];
        }
        $products = array_map(fn ($r) => [
            'name' => $r['name'],
            'unit' => $r['unit'] ?? null,
            'quantity' => (float) $r['quantity'],
            'revenue' => (float) $r['revenue'],
            'cogs' => (float) $r['cogs'],
            'gross_profit' => (float) $r['gross_profit'],
            'estimated_cogs' => (bool) ($r['estimated_cogs'] ?? false),
            'href' => isset($itemByName[$r['name']]) && $slug !== '' ? $this->stockItemLink($slug, $itemByName[$r['name']], $from, $to) : null,
        ], array_values(array_filter($profitRun['productRows'], fn ($r) => abs((float) $r['revenue']) > 0.005 || abs((float) $r['quantity']) > 0.0001)));

        // Sold straight off the tanker: never through a pump or a close's own sale, so the
        // profitability figures above miss it. The stock statement has it -- litres, the invoices'
        // amount, and its cost from the bill's own rate -- so each product takes its share.
        $direct = [];
        foreach ($stock as $p) {
            $q = 0.0; $amount = 0.0; $cost = 0.0;
            foreach ((array) ($p['rows'] ?? []) as $row) {
                $q += (float) ($row['sold_direct'] ?? 0);
                $amount += (float) ($row['direct_amount'] ?? 0);
                foreach ((array) ($row['bills'] ?? []) as $b) {
                    if ((float) ($b['direct'] ?? 0) > 0 && (float) ($b['quantity'] ?? 0) > 0) {
                        $cost += (float) ($b['amount'] ?? 0) * (float) $b['direct'] / (float) $b['quantity'];
                    }
                }
            }
            if ($q > 0.0001) {
                $direct[$p['name']] = ['quantity' => $q, 'revenue' => $amount, 'cogs' => round($cost, 2)];
            }
        }
        $directRevenue = array_sum(array_column($direct, 'revenue'));
        $directCost = array_sum(array_column($direct, 'cogs'));
        foreach ($products as &$line) {
            if ($d = $direct[$line['name']] ?? null) {
                $line['quantity'] += $d['quantity'];
                $line['revenue'] += $d['revenue'];
                $line['cogs'] += $d['cogs'];
                $line['gross_profit'] = $line['revenue'] - $line['cogs'];
                $line['direct_quantity'] = $d['quantity'];
                unset($direct[$line['name']]);
            }
        }
        unset($line);
        foreach ($direct as $name => $d) {
            $products[] = ['name' => $name, 'unit' => 'L', 'quantity' => $d['quantity'], 'revenue' => $d['revenue'], 'cogs' => $d['cogs'],
                'gross_profit' => $d['revenue'] - $d['cogs'], 'estimated_cogs' => false, 'direct_quantity' => $d['quantity'],
                'href' => isset($itemByName[$name]) && $slug !== '' ? $this->stockItemLink($slug, $itemByName[$name], $from, $to) : null];
        }

        // Expenses by the account they were entered against, each opening its expense statement.
        $expenseAccounts = array_map(fn ($a) => [
            'name' => $a['account_name'],
            'code' => $a['account_code'],
            'amount' => (float) $a['amount'],
            'asset' => ($a['account_type'] ?? null) === 'asset',
            'href' => $slug !== '' ? "/{$slug}/reports/statements?kind=expense&id={$a['account_id']}&from={$from}&to={$to}" : null,
        ], $expenseRun['accountRows']);

        // What was bought, per product.
        $purchaseProducts = [];
        foreach ($stock as $p) {
            $q = (float) ($p['totals']['received'] ?? 0);
            if ($q > 0.0001) {
                $purchaseProducts[] = [
                    'name' => $p['name'],
                    'unit' => $p['has_tank'] ? 'L' : ($p['unit'] ?: null),
                    'quantity' => $q,
                    'amount' => (float) ($p['totals']['purchase_amount'] ?? 0),
                    'href' => $slug !== '' ? $this->stockItemLink($slug, $p['id'], $from, $to) : null,
                ];
            }
        }

        $sales = [];
        $litres = 0.0;
        $amount = 0.0;
        $fuelIds = [];
        foreach ($stock as $p) {
            if ($p['has_tank']) {
                $fuelIds[] = $p['id'];
            }
            $litres += (float) ($p['totals']['received'] ?? 0) * ($p['has_tank'] ? 1 : 0);
            $amount += (float) ($p['totals']['purchase_amount'] ?? 0);
            if ($p['has_tank'] && (float) ($p['totals']['sold'] ?? 0) > 0) {
                $sales[] = [
                    'item_id' => $p['id'],
                    'name' => $p['name'],
                    'liters' => (float) $p['totals']['sold'],
                    'amount' => (float) ($p['totals']['sale_amount'] ?? 0),
                ];
            }
        }

        // Bills that brought fuel in over the range, counted the way the stock statement receives them.
        $bills = $fuelIds ? (int) DB::table('acct.bill_line_items as l')
            ->join('acct.bills as b', 'b.id', '=', 'l.bill_id')
            ->where('b.company_id', $companyId)->whereNull('b.deleted_at')
            ->whereNotIn('b.status', ['void', 'cancelled', 'draft'])
            ->whereIn('l.item_id', $fuelIds)
            ->whereDate('b.bill_date', '>=', $from)->whereDate('b.bill_date', '<=', $to)
            ->distinct()->count('b.id') : 0;

        return [
            'sales' => $sales,
            'products' => $products,
            'expense_accounts' => $expenseAccounts,
            'purchase_products' => $purchaseProducts,
            // Pump and close sales plus what went straight off the tanker.
            'revenue' => (float) ($profit['revenue'] ?? 0) + $directRevenue,
            'cogs' => (float) ($profit['cogs'] ?? 0) + $directCost,
            'sales_total' => (float) ($profit['revenue'] ?? 0) + $directRevenue,
            'gross_profit' => (float) ($profit['gross_profit'] ?? 0) + $directRevenue - $directCost,
            'expenses' => (float) ($expenses['amount'] ?? 0),
            'purchases' => ['liters' => $litres, 'amount' => $amount, 'bills' => $bills],
        ];
    }

    /** The stock table: one row per product that had any stock or movement in the range. */
    private function stockTable(array $stock, string $slug, string $from, string $to, ?string $lastCloseHref): array
    {
        $rows = [];
        foreach ($stock as $p) {
            $t = $p['totals'];
            $vals = [$t['opening'] ?? null, $t['received'] ?? null, $t['sold'] ?? 0, $t['closing'] ?? null, $t['variance'] ?? 0];
            if (! array_filter($vals, fn ($v) => $v !== null && abs((float) $v) > 0.0001)) {
                continue;
            }
            $rows[] = [
                'item_id' => $p['id'],
                'name' => $p['name'],
                'unit' => $p['has_tank'] ? 'L' : ($p['unit'] ?: null),
                'has_tank' => $p['has_tank'],
                'opening' => $t['opening'] !== null ? (float) $t['opening'] : null,
                'bought' => $t['received'] !== null ? (float) $t['received'] : null,
                'sold' => (float) ($t['sold'] ?? 0),
                'closing' => $t['closing'] !== null ? (float) $t['closing'] : null,
                'variance' => $p['has_tank'] ? (float) ($t['variance'] ?? 0) : null,
                'href' => $this->stockItemLink($slug, $p['id'], $from, $to),
                'closing_href' => $lastCloseHref,
            ];
        }

        return $rows;
    }

    /** Customers whose oldest unpaid invoice is more than 30 days past due, from an aging report. */
    private function overdueCustomers(array $report): array
    {
        $rows = array_values(array_filter(
            $report['rows'],
            fn ($r) => (int) $r['oldest_days_past_due'] > 30,
        ));

        return [
            'count' => count($rows),
            'total' => (float) array_sum(array_column($rows, 'total')),
            'top' => array_map(fn ($r) => [$r['customer_name'], (float) $r['total']], $rows),
        ];
    }

    /**
     * "Name amount" lines, the five biggest then "and N more".
     *
     * @param  array<int, array{0:string,1:float}>  $pairs
     * @return array<int, string>
     */
    private function topList(array $pairs, string $glue = ' · '): array
    {
        usort($pairs, fn ($a, $b) => abs($b[1]) <=> abs($a[1]));
        $lines = array_map(fn ($p) => $p[0].$glue.number_format(abs($p[1]), 0), array_slice($pairs, 0, 5));
        if (count($pairs) > 5) {
            $lines[] = 'and '.(count($pairs) - 5).' more';
        }

        return $lines;
    }

    /** @param array<int, array<string,mixed>> $sales */
    private function saleLinks(string $slug, array $sales, string $from, string $to): array
    {
        return array_map(fn ($s) => $s + ['href' => $this->stockItemLink($slug, $s['item_id'], $from, $to)], $sales);
    }

    // ---- helpers ------------------------------------------------------------------------------

    private function liveCloses(string $companyId)
    {
        return Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereIn('status', self::POSTED)
            ->whereNull('deleted_at')
            ->whereNull('reversed_by_id');
    }

    /**
     * Ledger balance per account as of a date: debits minus credits, flipped for credit-normal
     * accounts. Counted like the Balance Sheet: every posted transaction, a reversed original
     * together with its reversal (which nets them to nothing).
     *
     * @param  array<int, string>  $accountIds
     * @return array<string, float>
     */
    private function ledgerBalances(string $companyId, array $accountIds, string $asOf): array
    {
        if (! $accountIds) {
            return [];
        }

        $rows = DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)
            ->whereIn('je.account_id', $accountIds)
            ->whereIn('t.status', self::POSTED)
            ->whereNull('t.deleted_at')
            ->whereDate('t.transaction_date', '<=', $asOf)
            ->groupBy('a.id', 'a.normal_balance')
            ->selectRaw('a.id, a.normal_balance, COALESCE(SUM(je.debit_amount),0) AS debit, COALESCE(SUM(je.credit_amount),0) AS credit')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $net = (float) $r->debit - (float) $r->credit;
            $out[$r->id] = round($r->normal_balance === 'credit' ? -$net : $net, 2);
        }

        return $out;
    }

    private function stockItemLink(string $slug, string $itemId, string $from, string $to): string
    {
        return "/{$slug}/fuel/reports/stock-statement?item={$itemId}&start_date={$from}&end_date={$to}";
    }

    private function stockLink(string $slug, string $from, string $to): string
    {
        return "/{$slug}/fuel/reports/stock-statement?item=all&start_date={$from}&end_date={$to}";
    }
}
