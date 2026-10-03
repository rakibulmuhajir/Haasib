<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A month-end write-down of tank fuel to the new purchase rate changes what the litres are
 * worth, not how many there are: a 'revaluation' movement (quantity 0, total_cost = the value
 * change) lets FuelCostService see it. No new table, so no new RLS policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE inv.stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check');
        DB::statement("ALTER TABLE inv.stock_movements ADD CONSTRAINT stock_movements_type_check
            CHECK (movement_type IN ('purchase', 'sale', 'adjustment_in', 'adjustment_out',
                'transfer_in', 'transfer_out', 'return_in', 'return_out', 'opening', 'revaluation'))");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM inv.stock_movements WHERE movement_type = 'revaluation'");
        DB::statement('ALTER TABLE inv.stock_movements DROP CONSTRAINT IF EXISTS stock_movements_type_check');
        DB::statement("ALTER TABLE inv.stock_movements ADD CONSTRAINT stock_movements_type_check
            CHECK (movement_type IN ('purchase', 'sale', 'adjustment_in', 'adjustment_out',
                'transfer_in', 'transfer_out', 'return_in', 'return_out', 'opening'))");
    }
};
