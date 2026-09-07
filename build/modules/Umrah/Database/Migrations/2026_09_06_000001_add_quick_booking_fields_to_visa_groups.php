<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->boolean('includes_hotel')->default(false);
            $table->uuid('idempotency_key')->nullable();
            $table->unique(['company_id', 'idempotency_key'], 'visa_groups_company_idempotency_unique');
        });

        DB::statement('ALTER TABLE umrah.visa_groups DROP CONSTRAINT IF EXISTS visa_groups_sells_something_check');
        DB::statement("ALTER TABLE umrah.visa_groups ADD CONSTRAINT visa_groups_sells_something_check
            CHECK (includes_visa OR includes_hotel OR transport_mode <> 'none')");
        DB::statement('ALTER TABLE umrah.passengers DROP CONSTRAINT IF EXISTS passengers_service_type_check');
        DB::statement("ALTER TABLE umrah.passengers ADD CONSTRAINT passengers_service_type_check
            CHECK (service_type IN ('visa_transport', 'transport_only', 'hotel_only'))");
    }

    public function down(): void
    {
        $hotelOnly = DB::table('umrah.visa_groups')
            ->where('includes_hotel', true)
            ->where('includes_visa', false)
            ->where('transport_mode', 'none')
            ->count();
        $hotelPassengers = DB::table('umrah.passengers')->where('service_type', 'hotel_only')->count();

        if ($hotelOnly > 0 || $hotelPassengers > 0) {
            throw new RuntimeException(
                "Cannot roll back Quick Booking fields: {$hotelOnly} hotel-only booking(s) and {$hotelPassengers} hotel-only passenger(s) depend on them."
            );
        }

        DB::statement('ALTER TABLE umrah.passengers DROP CONSTRAINT IF EXISTS passengers_service_type_check');
        DB::statement("ALTER TABLE umrah.passengers ADD CONSTRAINT passengers_service_type_check
            CHECK (service_type IN ('visa_transport', 'transport_only'))");
        DB::statement('ALTER TABLE umrah.visa_groups DROP CONSTRAINT IF EXISTS visa_groups_sells_something_check');
        DB::statement("ALTER TABLE umrah.visa_groups ADD CONSTRAINT visa_groups_sells_something_check
            CHECK (includes_visa OR transport_mode <> 'none')");

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->dropUnique('visa_groups_company_idempotency_unique');
            $table->dropColumn(['includes_hotel', 'idempotency_key']);
        });
    }
};
