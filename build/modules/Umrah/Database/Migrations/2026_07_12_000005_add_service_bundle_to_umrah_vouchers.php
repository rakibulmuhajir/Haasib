<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.vouchers', function (Blueprint $table) {
            $table->string('service_bundle', 40)->default('visa_transport_hotel')->after('title');
        });

        DB::statement("ALTER TABLE umrah.vouchers ADD CONSTRAINT vouchers_service_bundle_check
            CHECK (service_bundle IN ('visa_transport', 'visa_transport_hotel', 'transport', 'transport_hotel', 'hotel'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE umrah.vouchers DROP CONSTRAINT IF EXISTS vouchers_service_bundle_check');
        Schema::table('umrah.vouchers', function (Blueprint $table) {
            $table->dropColumn('service_bundle');
        });
    }
};
