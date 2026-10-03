<?php

namespace App\Modules\FuelStation\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** A management view of fuel trading, independent of inventory journals. */
class MonthlyFuelProfitService
{
    public function __construct(private readonly StockStatementService $statements) {}

    public function run(string $companyId, string $month, string $product = 'all'): array
    {
        $saved = $this->snapshot($companyId, $month);
        if ($saved !== null) {
            return $this->forProduct($saved, $product);
        }
        $start = Carbon::parse($month.'-01');
        $end = $start->copy()->endOfMonth();
        $next = $end->copy()->addDay()->toDateString();
        $lines = [];
        $missing = [];
        $totals = ['sales' => 0.0, 'opening_value' => 0.0, 'purchases' => 0.0, 'closing_value' => 0.0, 'fuel_profit' => 0.0];
        $previous = $this->snapshot($companyId, $start->copy()->subMonth()->format('Y-m'));
        $bookAccountIds = [];
        $itemIds = [];
        $canReconcile = true;

        $items = DB::table('inv.warehouses as w')
            ->join('inv.items as i', 'i.id', '=', 'w.linked_item_id')
            ->where('w.company_id', $companyId)->where('w.warehouse_type', 'tank')
            ->whereNull('w.deleted_at')->whereNull('i.deleted_at')
            ->distinct()->get(['i.id', 'i.name', 'i.fuel_category', 'i.income_account_id', 'i.expense_account_id']);

        foreach ($items as $item) {
            $category = $item->fuel_category ?: str($item->name)->slug('-')->toString();
            if ($product !== 'all' && $category !== $product) {
                continue;
            }

            $statement = $this->statements->run($companyId, $item->id, $start->toDateString(), $end->toDateString());
            $stock = $statement['totals'];
            $openingRate = $this->rateAt($companyId, $item->id, $start->toDateString());
            $closingRate = $this->rateAt($companyId, $item->id, $next);
            $carried = collect($previous['lines'] ?? [])->firstWhere('item_id', $item->id);
            if ($carried !== null) {
                $openingRate = $carried['closing_rate'];
            }
            $lastRow = collect($statement['rows'])->firstWhere('date', $end->toDateString());
            $hasMissingDays = collect($statement['rows'])->contains(fn (array $row) => ! empty($row['missing']) || ! ($row['physical_reading_complete'] ?? false));
            $complete = $stock['opening'] !== null && $stock['closing'] !== null
                && $openingRate !== null && $closingRate !== null
                && ! $hasMissingDays
                && ($carried === null || abs((float) $stock['opening'] - $carried['closing_liters']) < 0.001)
                && $lastRow !== null && empty($lastRow['missing'])
                && ($lastRow['dip'] ?? null) !== null;
            if (! $complete) {
                $missing[] = $item->name;

                continue;
            }

            $opening = $carried['closing_value'] ?? round((float) $stock['opening'] * $openingRate, 2);
            $closing = round((float) $stock['closing'] * $closingRate, 2);
            $sales = round((float) $stock['sale_amount'], 2);
            $purchases = round((float) $stock['purchase_amount'], 2);
            $profit = round($sales + $closing - $opening - $purchases, 2);
            $lines[] = [
                'item_id' => $item->id, 'item' => $item->name, 'category' => $category,
                'opening_liters' => (float) $stock['opening'], 'opening_rate' => $openingRate, 'opening_value' => $opening,
                'purchased_liters' => (float) ($stock['received'] ?? 0), 'purchases' => $purchases,
                'sold_liters' => (float) $stock['sold'], 'sales' => $sales,
                'closing_liters' => (float) $stock['closing'], 'closing_rate' => $closingRate, 'closing_value' => $closing,
                'fuel_profit' => $profit,
            ];
            $bookAccountIds = array_merge($bookAccountIds, array_filter([$item->income_account_id, $item->expense_account_id]));
            $itemIds[] = $item->id;
            $canReconcile = $canReconcile && $item->income_account_id !== null && $item->expense_account_id !== null;
            $totals['sales'] += $sales;
            $totals['opening_value'] += $opening;
            $totals['purchases'] += $purchases;
            $totals['closing_value'] += $closing;
            $totals['fuel_profit'] += $profit;
        }

        // The method's fuel profit counts the dip, so it already holds the tank losses and gains;
        // the books keep those on their own accounts. Compare like with like, or they would be
        // taken off net profit twice. They are one account for all fuels, so only the whole
        // station can be reconciled.
        $varianceAccountIds = $product === 'all' ? app(DailyCloseService::class)->tankVarianceAccountIds($companyId) : [];
        $canReconcile = $canReconcile && $product === 'all';
        $books = $this->bookProfit($companyId, $start->toDateString(), $end->toDateString(), array_merge($bookAccountIds, $varianceAccountIds));
        // A shared fuel/non-fuel account cannot provide a reliable reconciliation.
        $shared = DB::table('inv.items')->where('company_id', $companyId)->whereNull('deleted_at')->whereNotIn('id', $itemIds)
            ->where(fn ($q) => $q->whereIn('income_account_id', $bookAccountIds)->orWhereIn('expense_account_id', $bookAccountIds))->exists();
        $canReconcile = $canReconcile && ! $shared;
        $complete = $missing === [] && $lines !== [];
        $adjustment = $complete ? round($totals['fuel_profit'] - $books['fuel_profit'], 2) : null;

        return [
            'month' => $month, 'next_rate_date' => $next, 'complete' => $missing === [] && $lines !== [],
            'missing_products' => $missing, 'lines' => $lines, 'totals' => $totals,
            'method' => 'next_month_purchase_rate', 'finalized' => false,
            'reconciliation' => $canReconcile ? [
                'book_fuel_profit' => $books['fuel_profit'], 'book_net_profit' => $product === 'all' ? $books['net_profit'] : null,
                'adjustment' => $adjustment,
                'net_station_profit' => $product === 'all' && $complete ? round($books['net_profit'] + $adjustment, 2) : null,
            ] : null,
        ];
    }

    /** Called by the existing Lock month action, inside its transaction. */
    public function finalize(string $companyId, string $month, string $userId): void
    {
        $this->lockCompany($companyId);
        if ($this->snapshot($companyId, $month) !== null) {
            return;
        }
        $method = DB::table('fuel.station_settings')->where('company_id', $companyId)->value('month_end_stock_valuation');
        if ($method !== 'next_month_purchase_rate') {
            return;
        }
        $payload = $this->run($companyId, $month);
        if (! $payload['complete'] || Carbon::parse($month.'-01')->endOfMonth()->gte(now()->startOfDay())) {
            throw ValidationException::withMessages(['month' => 'Complete the month, daily closes, opening stock and boundary purchase rates before locking its profit.']);
        }
        DB::table('fuel.month_profit_snapshots')->insert([
            'id' => (string) Str::uuid(), 'company_id' => $companyId, 'month' => $month.'-01', 'method' => $method,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'finalized_by_user_id' => $userId, 'finalized_at' => now(),
        ]);
    }

    public function reopen(string $companyId, string $month): void
    {
        $this->lockCompany($companyId);
        // Following snapshots depend on this month's carry-forward; preserve them as history.
        DB::table('fuel.month_profit_snapshots')->where('company_id', $companyId)->where('month', '>=', $month.'-01')
            ->whereNull('reopened_at')->update(['reopened_at' => now()]);
    }

    private function lockCompany(string $companyId): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['fuel-month-profit:'.$companyId]);
    }

    private function snapshot(string $companyId, string $month): ?array
    {
        $row = DB::table('fuel.month_profit_snapshots')->where('company_id', $companyId)->where('month', $month.'-01')
            ->whereNull('reopened_at')->first(['payload', 'finalized_at']);
        if ($row === null) {
            return null;
        }
        $payload = is_string($row->payload) ? json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR) : (array) $row->payload;

        return [...$payload, 'finalized' => true, 'finalized_at' => $row->finalized_at];
    }

    private function forProduct(array $payload, string $product): array
    {
        if ($product === 'all') {
            return $payload;
        }
        $payload['lines'] = array_values(array_filter($payload['lines'], fn ($line) => $line['category'] === $product));
        foreach (array_keys($payload['totals']) as $key) {
            $payload['totals'][$key] = array_sum(array_column($payload['lines'], $key));
        }
        $payload['complete'] = $payload['lines'] !== [];
        $payload['reconciliation'] = null;

        return $payload;
    }

    private function bookProfit(string $companyId, string $start, string $end, array $fuelAccountIds): array
    {
        $rows = DB::table('acct.journal_entries as je')->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)->whereIn('t.status', ['posted', 'locked'])->whereNull('t.deleted_at')
            ->whereBetween('t.transaction_date', [$start, $end])
            ->whereIn('a.type', ['revenue', 'other_income', 'expense', 'cogs', 'other_expense'])
            ->groupBy('a.id')->selectRaw('a.id, SUM(je.credit_amount) - SUM(je.debit_amount) AS net')->get();

        return [
            'fuel_profit' => round((float) $rows->whereIn('id', array_unique($fuelAccountIds))->sum('net'), 2),
            'net_profit' => round((float) $rows->sum('net'), 2),
        ];
    }

    private function rateAt(string $companyId, string $itemId, string $date): ?float
    {
        $rate = DB::table('fuel.rate_changes')->where('company_id', $companyId)->where('item_id', $itemId)
            ->whereDate('effective_date', '<=', $date)->orderByDesc('effective_date')->value('purchase_rate');

        return $rate === null ? null : (float) $rate;
    }
}
