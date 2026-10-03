<?php

namespace App\Modules\FuelStation\Services;

use Illuminate\Support\Facades\DB;

/**
 * What a litre of a fuel cost on a given business day: the weighted average of the stock in the
 * tanks that day, from the opening stock, every delivery received up to and including that day,
 * and each earlier day's closing dip.
 *
 * The daily close used to read inv.items.avg_cost, a single "current" figure. Closes are often
 * posted days later, after newer deliveries are in, and nothing kept avg_cost in step with
 * deliveries anyway -- one mistyped rate (462.72 for 362.72) sat on Petrol for weeks and every
 * day since showed a loss. Working it out per day makes a close, and an Edit-day re-post of an
 * old close, carry the cost of its own day.
 */
class FuelCostService
{
    /** @var array<string,float> */
    private array $memo = [];

    public function costForDay(string $companyId, string $itemId, string $date, float $fallback = 0.0): float
    {
        $key = "{$companyId}|{$itemId}|{$date}";
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $this->walk($companyId, $itemId, $date) ?? $fallback;
    }

    /**
     * The book cost per litre of the stock carried out of a day: costForDay's walk, plus the
     * month-end write-down dated on that day itself (costForDay leaves it for the next day).
     */
    public function costAtEndOf(string $companyId, string $itemId, string $date): ?float
    {
        $key = "end|{$companyId}|{$itemId}|{$date}";
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $this->walk($companyId, $itemId, $date, true);
    }

    public function forget(): void
    {
        $this->memo = [];
    }

    private function walk(string $companyId, string $itemId, string $date, bool $includeDayRevaluation = false): ?float
    {
        $opening = DB::table('inv.stock_movements')
            ->where('company_id', $companyId)->where('item_id', $itemId)
            ->where('movement_type', 'opening')
            ->whereDate('movement_date', '<=', $date)
            ->selectRaw('COALESCE(SUM(quantity),0) q, COALESCE(SUM(total_cost),0) v, MAX(movement_date) d')
            ->first();

        // Deliveries: bill receipts, and any correction of one (an un-receive books an
        // adjustment against the bill at the bill's own price).
        $purchases = DB::table('inv.stock_movements')
            ->where('company_id', $companyId)->where('item_id', $itemId)
            ->where(fn ($q) => $q->where('movement_type', 'purchase')->orWhere('reference_type', 'acct.bills'))
            ->whereDate('movement_date', '<=', $date)
            ->selectRaw('movement_date::date d, SUM(quantity) q, SUM(quantity * unit_cost) v')
            ->groupBy(DB::raw('movement_date::date'))
            ->get()->keyBy(fn ($r) => substr((string) $r->d, 0, 10));

        // What was physically in the tanks at the end of each earlier day.
        $dips = DB::table('fuel.tank_readings')
            ->where('company_id', $companyId)->where('item_id', $itemId)
            ->whereDate('reading_date', $includeDayRevaluation ? '<=' : '<', $date) // end of day: its own dip first, so a write-down that day spreads over what was left
            ->selectRaw('reading_date::date d, SUM(dip_measurement_liters) q')
            ->groupBy(DB::raw('reading_date::date'))
            ->get()->keyBy(fn ($r) => substr((string) $r->d, 0, 10));

        // Month-end write-downs to the new purchase rate (MonthEndStockValuationService): a value
        // change on the last day of a month, applied after that day's dip like the dips are.
        $revaluations = DB::table('inv.stock_movements')
            ->where('company_id', $companyId)->where('item_id', $itemId)
            ->where('movement_type', 'revaluation')
            ->whereDate('movement_date', $includeDayRevaluation ? '<=' : '<', $date)
            ->selectRaw('movement_date::date d, SUM(total_cost) v')
            ->groupBy(DB::raw('movement_date::date'))
            ->get()->keyBy(fn ($r) => substr((string) $r->d, 0, 10));

        $qty = (float) $opening->q;
        $value = (float) $opening->v;
        $cost = $qty > 0 ? $value / $qty : null;
        $start = $opening->d ? substr((string) $opening->d, 0, 10) : null;

        $days = collect($purchases->keys())->merge($dips->keys())->merge($revaluations->keys())->unique()->sort()->values();
        foreach ($days as $day) {
            if ($start !== null && $day < $start) {
                continue; // before the opening stock: the opening already counts it
            }
            if ($p = $purchases->get($day)) {
                if ($qty <= 0) {
                    $qty = 0.0;
                    $value = 0.0;
                }
                $qty += (float) $p->q;
                $value += (float) $p->v;
                if ($qty > 0) {
                    $cost = $value / $qty;
                }
            }
            if (($d = $dips->get($day)) && $cost !== null) {
                // Selling and dip gains/losses leave the cost per litre as it was.
                $qty = max(0.0, (float) $d->q);
                $value = $qty * $cost;
            }
            if (($r = $revaluations->get($day)) && $cost !== null) {
                $value += (float) $r->v;
                if ($qty > 0) {
                    $cost = $value / $qty;
                }
            }
        }

        return $cost !== null ? round($cost, 4) : null;
    }
}
