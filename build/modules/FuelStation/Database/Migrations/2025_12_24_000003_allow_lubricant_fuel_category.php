<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE inv.items DROP CONSTRAINT IF EXISTS items_fuel_category_check');
        DB::statement("ALTER TABLE inv.items ADD CONSTRAINT items_fuel_category_check
            CHECK (fuel_category IS NULL OR fuel_category IN ('petrol', 'diesel', 'high_octane', 'lubricant'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inv.items DROP CONSTRAINT IF EXISTS items_fuel_category_check');
        DB::statement("UPDATE inv.items
            SET fuel_category = NULL
            WHERE fuel_category IS NOT NULL
            AND fuel_category NOT IN ('petrol', 'diesel', 'high_octane')");
        DB::statement("ALTER TABLE inv.items ADD CONSTRAINT items_fuel_category_check
            CHECK (fuel_category IS NULL OR fuel_category IN ('petrol', 'diesel', 'high_octane'))");
    }
};
