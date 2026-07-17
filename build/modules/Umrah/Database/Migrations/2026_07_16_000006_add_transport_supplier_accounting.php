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
            $table->boolean('is_company_owned')->default(false)->after('vendor_type');
        });

        Schema::table('umrah.transport_fares', function (Blueprint $table) {
            $table->uuid('transport_vendor_id')->nullable()->after('company_id');
            $table->foreign('transport_vendor_id')->references('id')->on('umrah.visa_vendors')->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'transport_vendor_id']);
        });

        Schema::table('umrah.group_transport_items', function (Blueprint $table) {
            $table->uuid('transport_vendor_id')->nullable()->after('visa_group_id');
            $table->foreign('transport_vendor_id')->references('id')->on('umrah.visa_vendors')->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'transport_vendor_id']);
        });

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->uuid('mandatory_transport_vendor_id')->nullable()->after('vendor_id');
            $table->decimal('mandatory_transport_cost_amount', 15, 2)->default(0)->after('included_bus_cost_deduction');
            $table->foreign('mandatory_transport_vendor_id')->references('id')->on('umrah.visa_vendors')->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'mandatory_transport_vendor_id']);
        });

        DB::statement('ALTER TABLE umrah.group_payments DROP CONSTRAINT IF EXISTS group_payments_payee_check');
        Schema::table('umrah.group_payments', function (Blueprint $table) {
            $table->uuid('transport_vendor_id')->nullable()->after('visa_vendor_id');
            $table->foreign('transport_vendor_id')->references('id')->on('umrah.visa_vendors')->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'transport_vendor_id']);
        });
        DB::statement("ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_payee_check CHECK ((direction = 'received' AND agent_id IS NOT NULL AND visa_vendor_id IS NULL AND transport_vendor_id IS NULL AND hotel_vendor_id IS NULL) OR (direction = 'sent' AND agent_id IS NULL AND num_nonnulls(visa_vendor_id, transport_vendor_id, hotel_vendor_id) = 1))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE umrah.group_payments DROP CONSTRAINT IF EXISTS group_payments_payee_check');
        Schema::table('umrah.group_payments', function (Blueprint $table) {
            $table->dropForeign(['transport_vendor_id']);
            $table->dropIndex(['company_id', 'transport_vendor_id']);
            $table->dropColumn('transport_vendor_id');
        });
        DB::statement("ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_payee_check CHECK ((direction = 'received' AND agent_id IS NOT NULL AND visa_vendor_id IS NULL AND hotel_vendor_id IS NULL) OR (direction = 'sent' AND agent_id IS NULL AND ((visa_vendor_id IS NOT NULL AND hotel_vendor_id IS NULL) OR (visa_vendor_id IS NULL AND hotel_vendor_id IS NOT NULL))))");

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->dropForeign(['mandatory_transport_vendor_id']);
            $table->dropIndex(['company_id', 'mandatory_transport_vendor_id']);
            $table->dropColumn(['mandatory_transport_vendor_id', 'mandatory_transport_cost_amount']);
        });

        Schema::table('umrah.group_transport_items', function (Blueprint $table) {
            $table->dropForeign(['transport_vendor_id']);
            $table->dropIndex(['company_id', 'transport_vendor_id']);
            $table->dropColumn('transport_vendor_id');
        });

        Schema::table('umrah.transport_fares', function (Blueprint $table) {
            $table->dropForeign(['transport_vendor_id']);
            $table->dropIndex(['company_id', 'transport_vendor_id']);
            $table->dropColumn('transport_vendor_id');
        });

        Schema::table('umrah.visa_vendors', function (Blueprint $table) {
            $table->dropColumn('is_company_owned');
        });
    }
};
