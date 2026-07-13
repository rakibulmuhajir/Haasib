<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.group_payments', function (Blueprint $table) {
            $table->string('direction', 20)->default('received')->after('agent_id');
            $table->uuid('visa_vendor_id')->nullable()->after('direction');
            $table->uuid('hotel_vendor_id')->nullable()->after('visa_vendor_id');
            $table->foreign('visa_vendor_id')->references('id')->on('umrah.visa_vendors')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('hotel_vendor_id')->references('id')->on('umrah.hotel_vendors')->nullOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'direction', 'payment_date']);
        });

        DB::statement("ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_direction_check CHECK (direction IN ('received', 'sent'))");
        DB::statement("ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_payee_check CHECK ((direction = 'received' AND visa_vendor_id IS NULL AND hotel_vendor_id IS NULL) OR (direction = 'sent' AND ((visa_vendor_id IS NOT NULL AND hotel_vendor_id IS NULL) OR (visa_vendor_id IS NULL AND hotel_vendor_id IS NOT NULL))))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE umrah.group_payments DROP CONSTRAINT IF EXISTS group_payments_direction_check');
        DB::statement('ALTER TABLE umrah.group_payments DROP CONSTRAINT IF EXISTS group_payments_payee_check');
        Schema::table('umrah.group_payments', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'direction', 'payment_date']);
            $table->dropForeign(['visa_vendor_id']);
            $table->dropForeign(['hotel_vendor_id']);
            $table->dropColumn(['direction', 'visa_vendor_id', 'hotel_vendor_id']);
        });
    }
};
