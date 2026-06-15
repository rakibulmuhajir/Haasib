<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('acct.vendors', 'vendor_type')) {
                $table->string('vendor_type', 30)->default('general')->after('phone');
            }
        });

        DB::statement("UPDATE acct.vendors SET vendor_type = 'general' WHERE vendor_type IS NULL OR vendor_type = ''");
        DB::statement("ALTER TABLE acct.vendors DROP CONSTRAINT IF EXISTS vendors_vendor_type_check");
        DB::statement("ALTER TABLE acct.vendors ADD CONSTRAINT vendors_vendor_type_check
            CHECK (vendor_type IN ('general', 'fuel_refinery', 'fuel_distributor', 'fuel_station', 'lubricant_supplier', 'contractor', 'utility', 'service_provider'))");
        DB::statement("CREATE INDEX IF NOT EXISTS vendors_company_type_index ON acct.vendors (company_id, vendor_type) WHERE deleted_at IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS acct.vendors_company_type_index');
        DB::statement('ALTER TABLE acct.vendors DROP CONSTRAINT IF EXISTS vendors_vendor_type_check');

        Schema::table('acct.vendors', function (Blueprint $table) {
            if (Schema::hasColumn('acct.vendors', 'vendor_type')) {
                $table->dropColumn('vendor_type');
            }
        });
    }
};
