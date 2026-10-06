<?php

namespace App\Modules\Inventory\Services;

use App\Modules\FuelStation\Services\OpeningStockLedgerService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;

/**
 * The one place an item's opening stock is recorded: an 'opening' stock movement (which the
 * database trigger turns into the stock level) and the opening-stock journal that carries its
 * value into the books. Items -> New item and the fuel quick add both come through here.
 */
class OpeningStockService
{
    /**
     * Record opening stock for an item.
     *
     * @param  string|null  $warehouseId  a tank or warehouse; the standard warehouse when null
     * @param  float|null  $unitCost  the item's cost price when null
     * @param  string|null  $date  today when null
     * @param  bool  $syncLedger  false when the caller syncs once itself (after its transaction)
     */
    public function record(
        string $companyId,
        Item $item,
        float $quantity,
        ?float $unitCost = null,
        ?string $date = null,
        ?string $warehouseId = null,
        ?string $userId = null,
        ?string $notes = null,
        bool $syncLedger = true,
    ): ?StockMovement {
        if ($quantity <= 0) {
            return null;
        }

        $warehouseId ??= $this->resolveStandardWarehouse($companyId, $userId)->id;
        $movement = $this->recordMovement(
            $companyId,
            $warehouseId,
            $item->id,
            $date ?: now()->toDateString(),
            $quantity,
            $unitCost ?? (float) $item->cost_price,
            $userId,
            $notes ?? 'Opening stock'
        );

        if ($syncLedger) {
            $this->syncLedger($companyId, $userId);
        }

        return $movement;
    }

    /** Post the opening-stock journal; a books problem is logged, not thrown. */
    public function syncLedger(string $companyId, ?string $userId = null): void
    {
        app(OpeningStockLedgerService::class)->syncQuietly($companyId, $userId);
    }

    public function resolveStandardWarehouse(string $companyId, ?string $userId = null): Warehouse
    {
        $warehouse = Warehouse::where('company_id', $companyId)
            ->where('warehouse_type', 'standard')
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->first();

        if ($warehouse) {
            return $warehouse;
        }

        return Warehouse::create([
            'company_id' => $companyId,
            'code' => 'WH-MAIN',
            'name' => 'Main Warehouse',
            'warehouse_type' => 'standard',
            'is_active' => true,
            'created_by_user_id' => $userId,
        ]);
    }

    /** Insert, or update the matching movement (same item, warehouse, date and note). */
    public function recordMovement(
        string $companyId,
        string $warehouseId,
        string $itemId,
        string $date,
        float $quantity,
        float $unitCost,
        ?string $userId,
        string $notes
    ): StockMovement {
        $movement = StockMovement::where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('movement_type', 'opening')
            ->where('movement_date', $date)
            ->where('notes', $notes)
            ->first();

        $payload = [
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'movement_date' => $date,
        ];

        if ($movement) {
            // The stock level follows a movement only when it is inserted (the database trigger
            // inv.update_stock_level_on_movement is AFTER INSERT), so a changed opening quantity
            // moves the level by the difference here, or stock on hand would keep the old figure.
            $delta = round($quantity - (float) $movement->quantity, 6);
            $movement->update($payload);
            if (abs($delta) > 0.0000001) {
                \Illuminate\Support\Facades\DB::statement(
                    'INSERT INTO inv.stock_levels (company_id, warehouse_id, item_id, quantity, reserved_quantity) VALUES (?, ?, ?, ?, 0)
                     ON CONFLICT (company_id, warehouse_id, item_id) DO UPDATE SET quantity = inv.stock_levels.quantity + EXCLUDED.quantity, updated_at = NOW()',
                    [$companyId, $warehouseId, $itemId, $delta]
                );
            }

            return $movement;
        }

        return StockMovement::create($payload + [
            'company_id' => $companyId,
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'movement_type' => 'opening',
            'notes' => $notes,
            'created_by_user_id' => $userId,
        ]);
    }
}
