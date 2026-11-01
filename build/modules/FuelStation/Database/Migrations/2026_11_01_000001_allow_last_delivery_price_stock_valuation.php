<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function replace(array $methods): void
    {
        $in = "'".implode("', '", $methods)."'";
        DB::statement('ALTER TABLE fuel.station_settings DROP CONSTRAINT IF EXISTS station_settings_month_end_stock_valuation_check');
        DB::statement("ALTER TABLE fuel.station_settings ADD CONSTRAINT station_settings_month_end_stock_valuation_check CHECK (month_end_stock_valuation IN ($in))");
        DB::statement('ALTER TABLE fuel.month_profit_snapshots DROP CONSTRAINT IF EXISTS month_profit_snapshots_method_check');
        DB::statement("ALTER TABLE fuel.month_profit_snapshots ADD CONSTRAINT month_profit_snapshots_method_check CHECK (method IN ($in))");
    }

    public function up(): void
    {
        $this->replace(['inventory_cost', 'next_month_purchase_rate', 'last_delivery_price']);
    }

    public function down(): void
    {
        $this->replace(['inventory_cost', 'next_month_purchase_rate']);
    }
};
