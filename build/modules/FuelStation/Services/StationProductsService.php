<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Carbon;

/**
 * The rows of Products & stock: every product the station sells with its price, cost, margin,
 * what is on hand, and whether that is low.
 *
 * Tank products (fuels, open lubricant) are on hand as the latest live close's dip; a tank the
 * close does not list falls back to its latest dip reading, then to the stock ledger. Everything
 * else is the stock ledger. A handful of queries however many products there are.
 */
class StationProductsService
{
    private const POSTED = ['posted', 'locked'];

    private const PACK_UNITS = ['pack', 'packs', 'bottle', 'bottles', 'can', 'cans', 'carton', 'box', 'tin', 'drum'];

    /** @return array{rows: array<int, array<string,mixed>>, low_count: int} */
    public function run(string $companyId, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();

        $items = Item::where('company_id', $companyId)
            ->where('is_sellable', true)
            ->whereIn('item_type', ['product', 'non_inventory'])
            ->with('category:id,name')
            ->orderBy('name')
            ->get();
        if ($items->isEmpty()) {
            return ['rows' => [], 'low_count' => 0];
        }
        $ids = $items->pluck('id')->all();

        $tanksByItem = Warehouse::where('company_id', $companyId)
            ->where('warehouse_type', 'tank')->where('is_active', true)->whereNotNull('linked_item_id')
            ->get(['id', 'capacity', 'low_level_alert', 'linked_item_id'])
            ->groupBy('linked_item_id');
        $dips = $this->dips($companyId, $tanksByItem->flatten(1)->pluck('id')->all());

        $stock = StockLevel::where('company_id', $companyId)->whereIn('item_id', $ids)
            ->selectRaw('item_id, SUM(quantity) as quantity, MAX(COALESCE(reorder_point, 0)) as reorder_point')
            ->groupBy('item_id')->get()->keyBy('item_id');

        $rates = $this->currentRates($companyId, $ids, $today);

        $rows = [];
        foreach ($items as $item) {
            $tanks = $tanksByItem->get($item->id, collect());
            $level = $stock->get($item->id);
            $isFuel = $item->fuel_category !== null && $item->fuel_category !== 'lubricant';
            $type = match (true) {
                $isFuel => 'fuel',
                $item->fuel_category === 'lubricant', $tanks->isNotEmpty() => 'open',
                in_array(strtolower((string) $item->unit_of_measure), self::PACK_UNITS, true),
                str_contains(strtolower((string) $item->category?->name), 'lubricant') => 'pack',
                default => 'other',
            };

            $onHand = $level ? (float) $level->quantity : 0.0;
            $percent = null;
            $threshold = (float) ($item->reorder_point ?: ($level->reorder_point ?? 0));
            if ($tanks->isNotEmpty()) {
                $measured = $tanks->filter(fn ($t) => isset($dips[$t->id]));
                if ($measured->isNotEmpty()) {
                    $onHand = (float) $measured->sum(fn ($t) => $dips[$t->id]);
                    $capacity = (float) $measured->sum(fn ($t) => (float) $t->capacity);
                    $percent = $capacity > 0 ? round(min(100, max(0, $onHand / $capacity * 100)), 1) : null;
                }
                $alert = (float) $tanks->sum(fn ($t) => (float) $t->low_level_alert);
                $threshold = $alert > 0 ? $alert : $threshold;
            }

            $rate = $rates[$item->id] ?? null;
            $cost = (float) ($item->avg_cost ?: $item->cost_price ?: ($rate?->purchase_rate ?? 0));
            $price = (float) ($rate?->sale_rate ?? $item->selling_price ?? 0);

            $rows[] = [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'type' => $type,
                'unit' => $item->unit_of_measure,
                'is_active' => (bool) $item->is_active,
                'sale_price' => round($price, 2),
                'cost' => round($cost, 2),
                'margin' => round($price - $cost, 2),
                'on_hand' => round($onHand, 3),
                'percent_full' => $percent,
                'value' => round($onHand * $cost, 2),
                'low_level' => $threshold > 0 ? round($threshold, 3) : null,
                'low' => (bool) ($item->is_active && $item->track_inventory && $threshold > 0 && $onHand <= $threshold),
            ];
        }

        return ['rows' => $rows, 'low_count' => count(array_filter($rows, fn ($r) => $r['low']))];
    }

    /**
     * Litres in each tank: the latest live close's dip, else the tank's latest dip reading.
     *
     * @param  array<int, string>  $tankIds
     * @return array<string, float>
     */
    private function dips(string $companyId, array $tankIds): array
    {
        if (! $tankIds) {
            return [];
        }

        $levels = [];
        $close = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereIn('status', self::POSTED)
            ->whereNull('deleted_at')->whereNull('reversed_by_id')
            ->orderByDesc('transaction_date')->orderByDesc('created_at')
            ->first(['id', 'metadata']);
        foreach ((array) (((array) ($close?->metadata ?? []))['posting_snapshot']['tanks'] ?? []) as $t) {
            if (! empty($t['tank_id'])) {
                $levels[$t['tank_id']] = (float) ($t['physical_liters'] ?? 0);
            }
        }

        $missing = array_values(array_diff($tankIds, array_keys($levels)));
        if ($missing) {
            TankReading::where('company_id', $companyId)->whereIn('tank_id', $missing)
                ->orderByDesc('reading_date')->orderByDesc('created_at')
                ->get(['tank_id', 'dip_measurement_liters'])
                ->unique('tank_id')
                ->each(function ($r) use (&$levels) {
                    $levels[$r->tank_id] = (float) $r->dip_measurement_liters;
                });
        }

        return $levels;
    }

    /**
     * @param  array<int, string>  $itemIds
     * @return array<string, RateChange>
     */
    private function currentRates(string $companyId, array $itemIds, Carbon $today): array
    {
        return RateChange::where('company_id', $companyId)->whereIn('item_id', $itemIds)
            ->whereDate('effective_date', '<=', $today->toDateString())
            ->orderByDesc('effective_date')->orderByDesc('created_at')
            ->get(['item_id', 'sale_rate', 'purchase_rate'])
            ->unique('item_id')->keyBy('item_id')->all();
    }
}
