<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.visa_vendors', function (Blueprint $table) {
            $table->decimal('standard_bus_retail_amount', 15, 2)->default(0)->after('included_bus_cost_amount');
            $table->decimal('standard_bus_cost_amount', 15, 2)->default(0)->after('standard_bus_retail_amount');
            $table->boolean('charge_child_fare')->default(true)->after('standard_bus_cost_amount');
        });

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->decimal('standard_bus_retail_amount', 15, 2)->default(0)->after('included_bus_cost_deduction');
            $table->decimal('standard_bus_cost_amount', 15, 2)->default(0)->after('standard_bus_retail_amount');
            $table->boolean('standard_bus_charge_child_fare')->default(true)->after('standard_bus_cost_amount');
            $table->integer('standard_bus_billable_passenger_count')->default(0)->after('standard_bus_charge_child_fare');
        });
    }

    public function down(): void
    {
        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->dropColumn([
                'standard_bus_retail_amount',
                'standard_bus_cost_amount',
                'standard_bus_charge_child_fare',
                'standard_bus_billable_passenger_count',
            ]);
        });

        Schema::table('umrah.visa_vendors', function (Blueprint $table) {
            $table->dropColumn(['standard_bus_retail_amount', 'standard_bus_cost_amount', 'charge_child_fare']);
        });
    }
};
