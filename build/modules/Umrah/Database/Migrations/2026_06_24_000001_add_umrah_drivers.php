<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umrah.drivers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('name', 150);
            $table->string('phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'phone']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::table('umrah.transport_services', function (Blueprint $table) {
            $table->uuid('driver_id')->nullable()->after('company_id');
            $table->foreign('driver_id')->references('id')->on('umrah.drivers')->nullOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'driver_id']);
        });

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->uuid('driver_id')->nullable()->after('transport_service_id');
            $table->foreign('driver_id')->references('id')->on('umrah.drivers')->nullOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'driver_id']);
        });

        DB::statement('ALTER TABLE umrah.drivers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.drivers FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY drivers_company_isolation ON umrah.drivers
            FOR ALL
            USING (
                company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true
            )
            WITH CHECK (
                company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true
            )
        ");
    }

    public function down(): void
    {
        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
        });

        DB::statement('DROP INDEX IF EXISTS umrah.umrah_visa_groups_company_id_driver_id_index');

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->dropColumn('driver_id');
        });

        Schema::table('umrah.transport_services', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
        });

        DB::statement('DROP INDEX IF EXISTS umrah.umrah_transport_services_company_id_driver_id_index');

        Schema::table('umrah.transport_services', function (Blueprint $table) {
            $table->dropColumn('driver_id');
        });

        DB::statement('DROP POLICY IF EXISTS drivers_company_isolation ON umrah.drivers');
        Schema::dropIfExists('umrah.drivers');
    }
};
