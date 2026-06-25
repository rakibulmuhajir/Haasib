<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.transport_services', function (Blueprint $table) {
            $table->string('vehicle_type', 100)->nullable()->after('name');
            $table->integer('pax_capacity')->nullable()->after('vehicle_type');
        });

        DB::statement("
            UPDATE umrah.transport_services ts
            SET
                vehicle_type = vt.name,
                pax_capacity = vt.seats
            FROM umrah.vehicle_types vt
            WHERE ts.vehicle_type_id = vt.id
        ");

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->integer('transport_pax_capacity')->nullable()->after('transport_quantity');
        });

        DB::statement("
            UPDATE umrah.visa_groups vg
            SET transport_pax_capacity = ts.pax_capacity
            FROM umrah.transport_services ts
            WHERE vg.transport_service_id = ts.id
        ");

        Schema::table('umrah.transport_services', function (Blueprint $table) {
            $table->dropForeign(['vehicle_type_id']);
        });

        DB::statement('DROP INDEX IF EXISTS umrah.umrah_transport_services_company_id_vehicle_type_id_index');

        Schema::table('umrah.transport_services', function (Blueprint $table) {
            $table->dropColumn('vehicle_type_id');
        });

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->dropForeign(['vehicle_type_id']);
            $table->dropColumn('vehicle_type_id');
        });

        DB::statement('DROP POLICY IF EXISTS vehicle_types_company_isolation ON umrah.vehicle_types');
        Schema::dropIfExists('umrah.vehicle_types');
    }

    public function down(): void
    {
        Schema::create('umrah.vehicle_types', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('name', 100);
            $table->integer('seats')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_active']);
        });

        DB::statement('ALTER TABLE umrah.vehicle_types ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.vehicle_types FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY vehicle_types_company_isolation ON umrah.vehicle_types
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

        Schema::table('umrah.transport_services', function (Blueprint $table) {
            $table->uuid('vehicle_type_id')->nullable()->after('company_id');
            $table->foreign('vehicle_type_id')->references('id')->on('umrah.vehicle_types')->nullOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'vehicle_type_id']);
        });

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->uuid('vehicle_type_id')->nullable()->after('vendor_id');
            $table->foreign('vehicle_type_id')->references('id')->on('umrah.vehicle_types')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->dropColumn('transport_pax_capacity');
        });

        Schema::table('umrah.transport_services', function (Blueprint $table) {
            $table->dropColumn(['vehicle_type', 'pax_capacity']);
        });
    }
};
