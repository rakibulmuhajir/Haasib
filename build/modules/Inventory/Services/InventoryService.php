<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\Auth;

class InventoryService
{
    /**
     * Receive a single line item from a bill and create stock movement.
     * Called by ReceiveGoodsAction for each line being received.
     *
     * @param Bill $bill The bill
     * @param BillLineItem $line The line item being received
     * @param float $quantity The quantity being received (may be partial)
     * @param string $warehouseId The warehouse to receive into
     * @return StockMovement|null The created movement, or null if not tracked
     */
    public function receiveLineItem(Bill $bill, BillLineItem $line, float $quantity, string $warehouseId): ?StockMovement
    {
        // Load item if not loaded
        if (! $line->relationLoaded('item')) {
            $line->load('item');
        }

        if (! $this->shouldTrackInventory($line)) {
            return null;
        }

        $unitCost = (float) $line->unit_price;
        $totalCost = $quantity * $unitCost;

        $movement = StockMovement::create([
            'company_id' => $bill->company_id,
            'warehouse_id' => $warehouseId,
            'item_id' => $line->item_id,
            'movement_date' => now()->toDateString(),
            'movement_type' => 'purchase',
            'quantity' => abs($quantity),
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'reference_type' => 'acct.bills',
            'reference_id' => $bill->id,
            'notes' => "Received from Bill #{$bill->bill_number}",
            'created_by_user_id' => Auth::id(),
        ]);

        // Update item's cost price (weighted average)
        $this->updateItemCost($line->item, $quantity, $unitCost);

        return $movement;
    }

    /**
     * Create stock movements for ALL inventory items when a bill is received.
     * @deprecated Use receiveLineItem() via ReceiveGoodsAction for proper goods receipt tracking
     *
     * @param Bill $bill The bill being received
     * @return array Array of created StockMovement instances
     */
    public function receiveFromBill(Bill $bill): array
    {
        $movements = [];
        $bill->load('lineItems.item');

        foreach ($bill->lineItems as $line) {
            if (! $this->shouldTrackInventory($line)) {
                continue;
            }

            $warehouseId = $this->resolveWarehouse($line, $bill->company_id);
            if (! $warehouseId) {
                continue;
            }

            $movement = $this->receiveLineItem($bill, $line, $line->quantity, $warehouseId);
            if ($movement) {
                $movements[] = $movement;
            }
        }

        return $movements;
    }

    /**
     * Reverse stock movements when a bill is voided.
     *
     * @param Bill $bill The bill being voided
     * @return array Array of created reversal StockMovement instances
     */
    public function reverseFromBill(Bill $bill): array
    {
        $movements = [];

        // Find all stock movements for this bill
        $originalMovements = StockMovement::where('reference_type', 'acct.bills')
            ->where('reference_id', $bill->id)
            ->where('movement_type', 'purchase')
            ->get();

        foreach ($originalMovements as $original) {
            $movement = StockMovement::create([
                'company_id' => $original->company_id,
                'warehouse_id' => $original->warehouse_id,
                'item_id' => $original->item_id,
                'movement_date' => now()->toDateString(),
                'movement_type' => 'adjustment_out',
                'quantity' => -abs($original->quantity), // Negative to reverse
                'unit_cost' => $original->unit_cost,
                'total_cost' => -abs($original->total_cost),
                'reference_type' => 'acct.bills',
                'reference_id' => $bill->id,
                'related_movement_id' => $original->id,
                'reason' => 'Bill voided',
                'notes' => "Reversal of Bill #{$bill->bill_number}",
                'created_by_user_id' => Auth::id(),
            ]);

            $movements[] = $movement;
        }

        return $movements;
    }

    /**
     * Check if a bill line item should create inventory movements.
     */
    protected function shouldTrackInventory(BillLineItem $line): bool
    {
        if (! $line->item_id) {
            return false;
        }

        $item = $line->item;
        if (! $item) {
            return false;
        }

        return $item->track_inventory === true;
    }

    /**
     * Resolve which warehouse to use for the stock movement.
     * Priority: line warehouse_id > company primary warehouse
     */
    protected function resolveWarehouse(BillLineItem $line, string $companyId): ?string
    {
        // First priority: explicit warehouse on the line
        if ($line->warehouse_id) {
            return $line->warehouse_id;
        }

        // Second priority: company's primary warehouse
        $primaryWarehouse = Warehouse::where('company_id', $companyId)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->first();

        if ($primaryWarehouse) {
            return $primaryWarehouse->id;
        }

        // Fallback: any active warehouse
        $anyWarehouse = Warehouse::where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        return $anyWarehouse?->id;
    }

    /**
     * Update item's average cost using weighted average method.
     */
    protected function updateItemCost(Item $item, float $newQty, float $newUnitCost): void
    {
        // Get current total stock and value
        $currentQty = $item->stockLevels()->sum('quantity') ?? 0;
        $currentCost = (float) $item->cost_price;

        // Calculate weighted average
        $totalQty = $currentQty + $newQty;
        if ($totalQty > 0) {
            $totalValue = ($currentQty * $currentCost) + ($newQty * $newUnitCost);
            $newAvgCost = $totalValue / $totalQty;

            $item->update(['cost_price' => round($newAvgCost, 6)]);
        }
    }
}
