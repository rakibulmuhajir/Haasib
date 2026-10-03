<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel.station_settings', function (Blueprint $table) {
            $table->string('month_end_stock_valuation', 30)->default('inventory_cost');
        });

        DB::statement("ALTER TABLE fuel.station_settings ADD CONSTRAINT station_settings_month_end_stock_valuation_check CHECK (month_end_stock_valuation IN ('inventory_cost', 'next_month_purchase_rate'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fuel.station_settings DROP CONSTRAINT IF EXISTS station_settings_month_end_stock_valuation_check');
        Schema::table('fuel.station_settings', function (Blueprint $table) {
            $table->dropColumn('month_end_stock_valuation');
        });
    }
};
