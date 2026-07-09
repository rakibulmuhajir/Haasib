<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.visa_vendors', function (Blueprint $table) {
            $table->decimal('adult_retail_amount', 15, 2)->default(0)->after('notes');
            $table->decimal('adult_cost_amount', 15, 2)->default(0)->after('adult_retail_amount');
            $table->decimal('child_retail_amount', 15, 2)->default(0)->after('adult_cost_amount');
            $table->decimal('child_cost_amount', 15, 2)->default(0)->after('child_retail_amount');
            $table->decimal('infant_retail_amount', 15, 2)->default(0)->after('child_cost_amount');
            $table->decimal('infant_cost_amount', 15, 2)->default(0)->after('infant_retail_amount');
        });

        DB::statement('
            UPDATE umrah.visa_vendors vendors
            SET adult_retail_amount = services.retail_amount,
                adult_cost_amount = services.cost_amount,
                child_retail_amount = COALESCE(services.child_retail_amount, services.retail_amount),
                child_cost_amount = COALESCE(services.child_cost_amount, services.cost_amount),
                infant_retail_amount = COALESCE(services.infant_retail_amount, services.retail_amount),
                infant_cost_amount = COALESCE(services.infant_cost_amount, services.cost_amount)
            FROM (
                SELECT DISTINCT ON (vendor_id)
                    vendor_id,
                    retail_amount,
                    cost_amount,
                    child_retail_amount,
                    child_cost_amount,
                    infant_retail_amount,
                    infant_cost_amount
                FROM umrah.visa_services
                WHERE vendor_id IS NOT NULL
                  AND deleted_at IS NULL
                ORDER BY vendor_id, created_at ASC
            ) services
            WHERE vendors.id = services.vendor_id
        ');
    }

    public function down(): void
    {
        Schema::table('umrah.visa_vendors', function (Blueprint $table) {
            $table->dropColumn([
                'adult_retail_amount',
                'adult_cost_amount',
                'child_retail_amount',
                'child_cost_amount',
                'infant_retail_amount',
                'infant_cost_amount',
            ]);
        });
    }
};
