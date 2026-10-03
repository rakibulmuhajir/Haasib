<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Services\ProductCatalogService;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;

/**
 * Service for handling OGRA rate changes with stock revaluation.
 *
 * When OGRA announces new rates:
 * 1. Create a rate change record
 * 2. Calculate revaluation amount for existing stock
 * 3. Update Item.avg_cost to the new purchase rate
 * 4. Post a GL entry for the revaluation gain/loss
 *
 * Journal Entry for Revaluation:
 * - If new rate > old avg_cost (rate increase):
 *   DR Inventory (increase asset)
 *   CR Inventory Revaluation Gain (other income)
 *
 * - If new rate < old avg_cost (rate decrease):
 *   DR Inventory Revaluation Loss (expense)
 *   CR Inventory (decrease asset)
 */
class RateChangeService
{
    public function __construct(
        private readonly GlPostingService $glPostingService,
    ) {}

    /**
     * Create a rate change with stock revaluation.
     *
     * @param array $data Validated rate change data
     * @return RateChange
     */
    public function createWithRevaluation(array $data): RateChange
    {
        $company = app(CurrentCompany::class)->get();

        return DB::transaction(function () use ($data, $company) {
            // Get the fuel item
            $item = Item::where('company_id', $company->id)
                ->where('id', $data['item_id'])
                ->firstOrFail();
            if (! $item->fuel_category) {
                $fuelCategory = app(ProductCatalogService::class)->inferFuelCategory($item->sku, $item->name);
                if (! $fuelCategory) {
                    throw new \RuntimeException('Selected product is not marked as a fuel product.');
                }
                $item->update(['fuel_category' => $fuelCategory]);
                $item->refresh();
            }

            $existingRateChange = RateChange::where('company_id', $company->id)
                ->where('item_id', $data['item_id'])
                ->whereDate('effective_date', $data['effective_date'])
                ->first();

            // Get previous rate for margin impact calculation. Same-day edits must not compare against themselves.
            $previousRate = RateChange::where('company_id', $company->id)
                ->where('item_id', $data['item_id'])
                ->whereDate('effective_date', '<', $data['effective_date'])
                ->orderByDesc('effective_date')
                ->first();
            $previousAvgCost = (float) ($item->avg_cost ?? 0);

            $snapshotNozzleReadings = $this->cleanSnapshotNozzleReadings($data['snapshot_nozzle_readings'] ?? []);

            // Use the rate-change dip first, then manual stock quantity, then product stock fallback.
            $stockQuantity = $data['snapshot_dip_liters']
                ?? $data['stock_quantity_at_change']
                ?? (float) ($item->current_stock ?? 0);

            // Calculate margin impact (informational)
            $marginImpact = null;
            if ($previousRate && $stockQuantity > 0) {
                $oldMargin = (float) $previousRate->sale_rate - (float) $previousRate->purchase_rate;
                $newMargin = (float) $data['sale_rate'] - (float) $data['purchase_rate'];
                $marginImpact = round(($newMargin - $oldMargin) * $stockQuantity, 2);
            }

            // The purchase rate on a rate change is a reference (the supplier's new price), not a
            // cost: what stock actually cost comes from the deliveries, whose receipts keep the
            // weighted-average cost. So a rate change no longer overwrites avg_cost/cost_price or
            // posts a stock revaluation -- with near-daily changes that booked a journal almost
            // every day from a typed figure. The price-change windfall or loss now shows in the
            // margin as the old stock sells.

            $ratePayload = [
                'company_id' => $company->id,
                'item_id' => $data['item_id'],
                'effective_date' => $data['effective_date'],
                'purchase_rate' => $data['purchase_rate'],
                'sale_rate' => $data['sale_rate'],
                'stock_quantity_at_change' => $stockQuantity > 0 ? $stockQuantity : null,
                'margin_impact' => $marginImpact,
                'revaluation_amount' => null,
                'previous_avg_cost' => $previousAvgCost > 0 ? $previousAvgCost : null,
                'snapshot_tank_id' => $data['snapshot_tank_id'] ?? null,
                'snapshot_stick_reading' => $data['snapshot_stick_reading'] ?? null,
                'snapshot_dip_liters' => $data['snapshot_dip_liters'] ?? null,
                'snapshot_nozzle_readings' => $snapshotNozzleReadings ?: null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => auth()->id(),
            ];

            if ($existingRateChange) {
                unset($ratePayload['company_id'], $ratePayload['item_id'], $ratePayload['effective_date'], $ratePayload['created_by_user_id']);
                $existingRateChange->update($ratePayload);
                $rateChange = $existingRateChange->fresh();
            } else {
                $rateChange = RateChange::create($ratePayload);
            }

            // Only the selling price follows the rate change; cost stays delivery-based.
            $item->update(['selling_price' => (float) $data['sale_rate']]);

            return $rateChange;
        });
    }

    private function cleanSnapshotNozzleReadings(array $readings): array
    {
        return collect($readings)
            ->filter(fn (array $reading) => ! empty($reading['nozzle_id'])
                && (($reading['electronic_reading'] ?? null) !== null || ($reading['manual_reading'] ?? null) !== null))
            ->map(fn (array $reading) => [
                'nozzle_id' => $reading['nozzle_id'],
                'electronic_reading' => ($reading['electronic_reading'] ?? null) !== null
                    ? (float) $reading['electronic_reading']
                    : null,
                'manual_reading' => ($reading['manual_reading'] ?? null) !== null
                    ? (float) $reading['manual_reading']
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * The price each item was last bought at, from the most recent bill line on or before
     * $date (every item when $date is null): what a new purchase rate should start from,
     * rather than the purchase rate stored on the last rate change, which goes stale.
     *
     * @return array<string, array{rate: float, bill_id: string, bill_number: string, bill_date: string}>
     */
    public function lastPurchasePrices(string $companyId, ?string $date = null): array
    {
        $rows = DB::table('acct.bill_line_items as l')
            ->join('acct.bills as b', 'b.id', '=', 'l.bill_id')
            ->where('b.company_id', $companyId)
            ->whereNull('b.deleted_at')
            ->whereNotIn('b.status', ['void', 'cancelled', 'draft'])
            ->whereNotNull('l.item_id')
            ->where('l.quantity', '>', 0)
            ->when($date, fn ($q) => $q->whereDate('b.bill_date', '<=', $date))
            ->orderByDesc('b.bill_date')
            ->orderByDesc('b.created_at')
            ->get(['l.item_id', 'l.unit_price', 'b.id as bill_id', 'b.bill_number', 'b.bill_date']);

        $prices = [];
        foreach ($rows as $row) {
            $prices[$row->item_id] ??= [
                'rate' => round((float) $row->unit_price, 4),
                'bill_id' => $row->bill_id,
                'bill_number' => $row->bill_number,
                'bill_date' => substr((string) $row->bill_date, 0, 10),
            ];
        }

        return $prices;
    }

    /**
     * Get current stock for a fuel item across all tanks.
     */
    public function getCurrentStock(string $companyId, string $itemId): float
    {
        $item = Item::where('company_id', $companyId)
            ->where('id', $itemId)
            ->first();

        return (float) ($item?->current_stock ?? 0);
    }
}
