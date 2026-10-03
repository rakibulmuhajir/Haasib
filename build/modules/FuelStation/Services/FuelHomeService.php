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
        $money = $this->money($company, $todayDate);
        $tanks = $this->tanks($company, $closes['last_date']);
        $stock = $this->stockRuns($id, $monthStart, $todayDate);
        $fig = $this->figures($id, $monthStart, $todayDate, $stock);
        $overdue = $this->overdueCustomers($id, $todayDate);

        $month = [
            'label' => $today->format('F Y'),
            'sales' => $fig['sales'],
            'sales_total' => (float) ($summary['sales_total'] ?? 0),
            'gross_profit' => $fig['gross_profit'],
            'expenses' => $fig['expenses'],
            'short_over' => (float) ($summary['cash']['short_over'] ?? 0),
            'purchases' => $fig['purchases'],
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
            'rates' => $this->rates($id, $todayDate),
            'attention' => $this->attention($company, $closes, $payroll, $tanks, $stock, $overdue, $monthStart, $todayDate),
        ];
    }

    public function period(Company $company, string $from, string $to): array
    {
        $id = $company->id;
        $slug = $company->slug;
        $today = Carbon::today();

        $stock = $this->stockRuns($id, $from, $to);
        $fig = $this->figures($id, $from, $to, $stock);

        $rows = $this->liveCloses($id)
            ->whereBetween('transaction_date', [$from, $to])
            ->orderBy('transaction_date')->orderBy('created_at')
            ->get(['id', 'transaction_date', 'metadata']);
        $byDate = [];
        foreach ($rows as $t) {
            $byDate[Carbon::parse($t->transaction_date)->toDateString()] = $t;
        }
        $shortOver = 0.0;
        foreach ($byDate as $t) {
            $m = (array) ($t->metadata ?? []);
            $shortOver += (float) (($m['posting_snapshot']['totals']['variance'] ?? null) ?? ($m['variance'] ?? 0));
        }

        $lastDay = Carbon::parse($to)->min($today);
        $days = Carbon::parse($from)->gt($lastDay) ? 0 : (int) Carbon::parse($from)->diffInDays($lastDay) + 1;

        return [
            'from' => $from,
            'to' => $to,
            'sales' => $fig['sales'],
            'sales_total' => $fig['sales_total'],
            'gross_profit' => $fig['gross_profit'],
            'expenses' => $fig['expenses'],
            'short_over' => $shortOver,
            'purchases' => $fig['purchases'],
            'stock' => $this->stockTable($stock),
            'money' => $this->money($company, $to),
            'closes' => ['count' => count($byDate), 'days' => $days],
            'links' => [
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
            ->first(['id', 'transaction_date']);
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

        return [
            'last_date' => $lastDate,
            'last_id' => $last?->id,
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
        $out = ['enabled' => false, 'reminder' => null, 'owed_count' => 0, 'owed_total' => 0.0];
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
        } catch (\Throwable $e) {
            Log::warning('Fuel home payroll block failed', ['company_id' => $company->id, 'error' => $e->getMessage()]);
        }

        return $out;
    }

    /** Cash, each bank, what customers owe, what is owed to suppliers, amanat held, all as of a date. */
    private function money(Company $company, string $asOf): array
    {
        $id = $company->id;

        $cashId = app(DailyCloseService::class)->cashAccountId($id);
        $banks = Account::where('company_id', $id)->whereNull('deleted_at')->where('is_active', true)
            ->where('subtype', 'bank')->orderBy('code')->get(['id', 'code', 'name']);
        $amanatId = Account::where('company_id', $id)->whereNull('deleted_at')->where('is_active', true)
            ->where('code', '2200')->value('id');

        $ids = array_values(array_filter(array_merge([$cashId, $amanatId], $banks->pluck('id')->all())));
        $balance = $this->ledgerBalances($id, $ids, $asOf);

        // The aging reports read each invoice's / bill's current balance, so as of a past date
        // they show what was then billed and is still unpaid today, not a time-travelled ledger.
        return [
            'as_of' => $asOf,
            'cash' => $cashId ? ($balance[$cashId] ?? 0.0) : null,
            'banks' => $banks->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'balance' => $balance[$b->id] ?? 0.0,
            ])->values()->all(),
            'receivable' => (float) app(ReceivablesAgingReportService::class)->run($id, $asOf)['totals']['total'],
            'payable' => (float) app(PayablesAgingReportService::class)->run($id, $asOf)['totals']['total'],
            'amanat' => $amanatId ? ($balance[$amanatId] ?? 0.0) : null,
        ];
    }

    private function tanks(Company $company, ?string $lastCloseDate): array
    {
        $id = $company->id;
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

        return $tanks->map(function ($tank) use ($levels, $sold, $closeCount, $itemNames, $lastCloseDate, $today, $id, $dailyService) {
            $level = $levels[$tank->id] ?? null;
            $capacity = (float) $tank->capacity;
            $average = $closeCount > 0 && isset($sold[$tank->id]) ? $sold[$tank->id] / $closeCount : null;
            $average = $average !== null && $average > 0 ? $average : null;

            $pending = 0.0;
            if ($tank->linked_item_id) {
                try {
                    $pending = (float) $dailyService
                        ->pendingDeliveries($id, $tank->id, $tank->linked_item_id, $lastCloseDate, $today)
                        ->sum('remaining');
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
            ];
        })->values()->all();
    }

    private function rates(string $companyId, string $date): array
    {
        $items = DB::table('inv.items')->where('company_id', $companyId)->whereNotNull('fuel_category')
            ->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        return $items->map(function ($item) use ($companyId, $date) {
            $rate = RateChange::getRateForDate($companyId, $item->id, $date);
            $sale = $rate ? (float) $rate->sale_rate : null;
            $purchase = $rate ? (float) $rate->purchase_rate : null;

            return [
                'item_id' => $item->id,
                'name' => $item->name,
                'sale_rate' => $sale,
                'purchase_rate' => $purchase,
                'margin' => $sale !== null && $purchase !== null ? round($sale - $purchase, 2) : null,
                'effective_date' => $rate?->effective_date ? Carbon::parse($rate->effective_date)->toDateString() : null,
            ];
        })->values()->all();
    }

    /** @return array<int, array{label:string, detail:?string, href:string}> */
    private function attention(Company $company, array $close, array $payroll, array $tanks, array $stock, array $overdue, string $from, string $to): array
    {
        $slug = $company->slug;
        $items = [];
        $plural = fn (int $n, string $one, string $many) => $n.' '.($n === 1 ? $one : $many);

        if ($close['missing_dates']) {
            $n = count($close['missing_dates']);
            $items[] = [
                'label' => $plural($n, 'close missing', 'closes missing'),
                'detail' => 'This month',
                'href' => "/{$slug}/fuel/daily-close/month?month=".Carbon::parse($to)->format('Y-m'),
            ];
        }
        if ($close['parked_dates']) {
            $n = count($close['parked_dates']);
            $items[] = [
                'label' => $plural($n, 'parked close', 'parked closes'),
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
                'detail' => $plural((int) $payroll['owed_count'], 'payslip', 'payslips').' · '.number_format((float) $payroll['owed_total'], 0),
                'href' => "/{$slug}/payroll",
            ];
        }

        $negative = array_filter($stock, fn ($p) => ! $p['has_tank'] && $p['totals']['closing'] !== null && (float) $p['totals']['closing'] < -0.0001);
        if ($negative) {
            $n = count($negative);
            $items[] = [
                'label' => $n.' '.($n === 1 ? 'product' : 'products').': purchases not recorded',
                'detail' => 'Stock below zero',
                'href' => $this->stockLink($slug, $from, $to),
            ];
        }
        if ($overdue['count'] > 0) {
            $items[] = [
                'label' => $plural($overdue['count'], 'customer overdue', 'customers overdue').' 30+ days',
                'detail' => number_format($overdue['total'], 0),
                'href' => "/{$slug}/reports/receivables-aging",
            ];
        }
        foreach ($tanks as $t) {
            if ($t['days_left'] !== null && $t['days_left'] < 1) {
                $items[] = [
                    'label' => $t['name'].': under a day left',
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
            ];
        }

        return $out;
    }

    /** Sales per fuel, gross profit, expenses and purchases for the range. */
    private function figures(string $companyId, string $from, string $to, array $stock): array
    {
        $profit = app(ProductProfitabilityReportService::class)->run($companyId, $from, $to)['totals'];
        $expenses = app(ExpenseReportService::class)->run($companyId, $from, $to)['totals'];

        $sales = [];
        $litres = 0.0;
        $amount = 0.0;
        foreach ($stock as $p) {
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

        return [
            'sales' => $sales,
            'sales_total' => (float) ($profit['revenue'] ?? 0),
            'gross_profit' => (float) ($profit['gross_profit'] ?? 0),
            'expenses' => (float) ($expenses['amount'] ?? 0),
            'purchases' => ['liters' => $litres, 'amount' => $amount],
        ];
    }

    /** The stock table: one row per product that had any stock or movement in the range. */
    private function stockTable(array $stock): array
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
            ];
        }

        return $rows;
    }

    /** Customers whose oldest unpaid invoice is more than 30 days past due. */
    private function overdueCustomers(string $companyId, string $asOf): array
    {
        $rows = array_filter(
            app(ReceivablesAgingReportService::class)->run($companyId, $asOf)['rows'],
            fn ($r) => (int) $r['oldest_days_past_due'] > 30,
        );

        return ['count' => count($rows), 'total' => (float) array_sum(array_column($rows, 'total'))];
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

    private function stockLink(string $slug, string $from, string $to): string
    {
        return "/{$slug}/fuel/reports/stock-statement?item=all&start_date={$from}&end_date={$to}";
    }
}
