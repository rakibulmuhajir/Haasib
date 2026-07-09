<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.visa_services', function (Blueprint $table) {
            $table->decimal('child_retail_amount', 15, 2)->default(0)->after('cost_amount');
            $table->decimal('child_cost_amount', 15, 2)->default(0)->after('child_retail_amount');
            $table->decimal('infant_retail_amount', 15, 2)->default(0)->after('child_cost_amount');
            $table->decimal('infant_cost_amount', 15, 2)->default(0)->after('infant_retail_amount');
        });

        DB::statement('
            UPDATE umrah.visa_services
            SET child_retail_amount = retail_amount,
                child_cost_amount = cost_amount,
                infant_retail_amount = retail_amount,
                infant_cost_amount = cost_amount
        ');
    }

    public function down(): void
    {
        Schema::table('umrah.visa_services', function (Blueprint $table) {
            $table->dropColumn([
                'child_retail_amount',
                'child_cost_amount',
                'infant_retail_amount',
                'infant_cost_amount',
            ]);
        });
    }
};
