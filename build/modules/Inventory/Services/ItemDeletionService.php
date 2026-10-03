<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * Deleting an item. One that was never used -- only its own opening stock behind it -- goes
 * away together with that opening stock; one that has been sold, bought or moved is kept
 * (deactivate it instead) because the books and history point at it.
 */
class ItemDeletionService
{
    public const USED_MESSAGE = 'Used in sales or purchases. Deactivate it instead.';

    /** Tables that point at an item by this column: any live row means the item has been used. */
    private const USAGE = [
        ['acct.bill_line_items', 'item_id', true],
        ['acct.invoice_line_items', 'item_id', false],
        ['inv.stock_receipt_lines', 'item_id', false],
        ['inv.warehouses', 'linked_item_id', false],
        ['fuel.nozzles', 'item_id', false],
        ['fuel.nozzle_readings', 'item_id', false],
        ['fuel.tank_readings', 'item_id', false],
        ['fuel.pump_readings', 'item_id', false],
        ['fuel.customer_fuel_discounts', 'item_id', false],
    ];

    public function isUsed(Item $item): bool
    {
        $otherMovements = DB::table('inv.stock_movements')
            ->where('item_id', $item->id)
            ->where('movement_type', '!=', 'opening')
            ->exists();
        if ($otherMovements) {
            return true;
        }

        foreach (self::USAGE as [$table, $column, $softDeletes]) {
            $query = DB::table($table)->where($column, $item->id);
            if ($softDeletes) {
                $query->whereNull('deleted_at');
            }
            if ($query->exists()) {
                return true;
            }
        }

        // Daily closes keep their lines (other sales and the rest) in the close's metadata.
        return DB::table('acct.transactions')
            ->where('company_id', $item->company_id)
            ->whereRaw('metadata::text like ?', ['%'.$item->id.'%'])
            ->exists();
    }

    /**
     * @return bool false when the item is used and was left alone
     */
    public function delete(Item $item, ?string $userId = null): bool
    {
        $deleted = DB::transaction(function () use ($item) {
            if ($this->isUsed($item)) {
                return false;
            }

            $openings = DB::table('inv.stock_movements')
                ->where('item_id', $item->id)
                ->where('movement_type', 'opening')
                ->get(['id', 'company_id', 'warehouse_id', 'quantity']);

            foreach ($openings as $opening) {
                // The insert trigger added the quantity to the stock level; nothing takes it off.
                DB::table('inv.stock_levels')
                    ->where('company_id', $opening->company_id)
                    ->where('warehouse_id', $opening->warehouse_id)
                    ->where('item_id', $item->id)
                    ->update(['quantity' => DB::raw('quantity - '.(float) $opening->quantity)]);
            }
            DB::table('inv.stock_movements')->whereIn('id', $openings->pluck('id'))->delete();
            // Its rates were set up with it (fuel quick add); with nothing sold or bought they go too.
            DB::table('fuel.rate_changes')->where('item_id', $item->id)->delete();
            DB::table('inv.stock_levels')->where('item_id', $item->id)->where('quantity', 0)->delete();

            $item->delete();

            return true;
        });

        if ($deleted) {
            app(OpeningStockService::class)->syncLedger($item->company_id, $userId);
        }

        return $deleted;
    }
}
