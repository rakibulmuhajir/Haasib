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
            $table->boolean('is_default')->default(false)->after('is_company_owned');
            $table->boolean('provides_mandatory_transport')->default(false)->after('is_default');
            $table->uuid('mandatory_transport_vendor_id')->nullable()->after('provides_mandatory_transport');
            $table->foreign('mandatory_transport_vendor_id')->references('id')->on('umrah.visa_vendors')->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'mandatory_transport_vendor_id']);
        });

        DB::statement("UPDATE umrah.visa_vendors AS vendors
            SET is_default = true, updated_at = now()
            FROM (
                SELECT DISTINCT ON (company_id) id
                FROM umrah.visa_vendors
                WHERE vendor_type != 'transport_provider'
                  AND is_active = true
                  AND deleted_at IS NULL
                ORDER BY company_id, created_at, id
            ) AS defaults
            WHERE vendors.id = defaults.id");

        DB::statement('CREATE UNIQUE INDEX visa_vendors_one_default_per_company
            ON umrah.visa_vendors (company_id)
            WHERE is_default = true AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS umrah.visa_vendors_one_default_per_company');
        Schema::table('umrah.visa_vendors', function (Blueprint $table) {
            $table->dropForeign(['mandatory_transport_vendor_id']);
            $table->dropIndex(['company_id', 'mandatory_transport_vendor_id']);
            $table->dropColumn(['is_default', 'provides_mandatory_transport', 'mandatory_transport_vendor_id']);
        });
    }
};
