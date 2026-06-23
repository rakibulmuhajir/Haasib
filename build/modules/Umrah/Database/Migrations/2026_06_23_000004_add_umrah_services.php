<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umrah.visa_services', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('vendor_id')->nullable();
            $table->string('name', 150);
            $table->decimal('retail_amount', 15, 2)->default(0);
            $table->decimal('cost_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('vendor_id')->references('id')->on('umrah.visa_vendors')->nullOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'vendor_id']);
        });

        Schema::create('umrah.transport_services', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('vehicle_type_id')->nullable();
            $table->string('name', 150);
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('number_plate', 50)->nullable();
            $table->string('driver_name', 150)->nullable();
            $table->string('driver_contact', 50)->nullable();
            $table->decimal('default_sale_amount', 15, 2)->default(0);
            $table->decimal('default_cost_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('vehicle_type_id')->references('id')->on('umrah.vehicle_types')->nullOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'vehicle_type_id']);
            $table->index(['company_id', 'number_plate']);
        });

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->uuid('visa_service_id')->nullable()->after('vehicle_type_id');
            $table->uuid('transport_service_id')->nullable()->after('visa_service_id');
            $table->decimal('transport_cost_amount', 15, 2)->default(0)->after('visa_cost_amount');

            $table->foreign('visa_service_id')->references('id')->on('umrah.visa_services')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('transport_service_id')->references('id')->on('umrah.transport_services')->nullOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'visa_service_id']);
            $table->index(['company_id', 'transport_service_id']);
        });

        foreach (['visa_services', 'transport_services'] as $table) {
            DB::statement("ALTER TABLE umrah.{$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE umrah.{$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY {$table}_company_isolation ON umrah.{$table}
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
    }

    public function down(): void
    {
        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->dropForeign(['visa_service_id']);
            $table->dropForeign(['transport_service_id']);
            $table->dropIndex(['company_id', 'visa_service_id']);
            $table->dropIndex(['company_id', 'transport_service_id']);
            $table->dropColumn(['visa_service_id', 'transport_service_id', 'transport_cost_amount']);
        });

        foreach (['transport_services', 'visa_services'] as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_company_isolation ON umrah.{$table}");
            Schema::dropIfExists("umrah.{$table}");
        }
    }
};
