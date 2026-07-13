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
            $table->decimal('included_bus_cost_amount', 15, 2)->default(50)->after('child_cost_amount');
        });

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->string('transport_mode', 30)->default('standard_bus')->after('transport_required');
            $table->decimal('included_bus_cost_per_passenger', 15, 2)->default(50)->after('transport_mode');
            $table->decimal('included_bus_cost_deduction', 15, 2)->default(0)->after('included_bus_cost_per_passenger');
        });

        DB::statement("ALTER TABLE umrah.visa_groups ADD CONSTRAINT visa_groups_transport_mode_check CHECK (transport_mode IN ('standard_bus', 'specialized'))");

        Schema::table('umrah.passengers', function (Blueprint $table) {
            $table->string('service_type', 30)->default('visa_transport')->after('imported_age');
            $table->decimal('transport_charge_amount', 15, 2)->default(0)->after('service_type');
        });

        DB::statement("ALTER TABLE umrah.passengers ADD CONSTRAINT passengers_service_type_check CHECK (service_type IN ('visa_transport', 'transport_only'))");

        Schema::create('umrah.transport_sectors', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('origin', 150);
            $table->string('destination', 150);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        Schema::create('umrah.transport_packages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('name', 150);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('umrah.transport_package_sectors', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('transport_package_id');
            $table->uuid('transport_sector_id');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('transport_package_id')->references('id')->on('umrah.transport_packages')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('transport_sector_id')->references('id')->on('umrah.transport_sectors')->restrictOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'transport_package_id', 'transport_sector_id'], 'transport_package_sector_unique');
            $table->index(['company_id', 'transport_package_id', 'sort_order'], 'transport_package_sector_order_idx');
        });

        Schema::create('umrah.transport_fares', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('transport_service_id');
            $table->uuid('transport_sector_id')->nullable();
            $table->uuid('transport_package_id')->nullable();
            $table->string('name', 150);
            $table->string('charging_basis', 30)->default('per_vehicle');
            $table->decimal('sale_amount', 15, 2)->default(0);
            $table->decimal('cost_amount', 15, 2)->default(0);
            $table->decimal('hajj_terminal_sale_amount', 15, 2)->default(90);
            $table->decimal('hajj_terminal_cost_amount', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('transport_service_id')->references('id')->on('umrah.transport_services')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('transport_sector_id')->references('id')->on('umrah.transport_sectors')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('transport_package_id')->references('id')->on('umrah.transport_packages')->restrictOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'transport_service_id']);
        });

        DB::statement("ALTER TABLE umrah.transport_fares ADD CONSTRAINT transport_fares_target_check CHECK ((transport_sector_id IS NOT NULL)::integer + (transport_package_id IS NOT NULL)::integer = 1)");
        DB::statement("ALTER TABLE umrah.transport_fares ADD CONSTRAINT transport_fares_basis_check CHECK (charging_basis IN ('per_vehicle', 'per_passenger', 'flat_group'))");

        Schema::create('umrah.group_transport_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('visa_group_id');
            $table->uuid('transport_fare_id')->nullable();
            $table->uuid('transport_service_id')->nullable();
            $table->uuid('transport_sector_id')->nullable();
            $table->uuid('transport_package_id')->nullable();
            $table->uuid('driver_id')->nullable();
            $table->string('description', 255);
            $table->timestamp('scheduled_at')->nullable();
            $table->string('terminal', 30)->default('standard');
            $table->string('charging_basis', 30)->default('per_vehicle');
            $table->integer('quantity')->default(1);
            $table->integer('passenger_count')->default(0);
            $table->decimal('unit_sale_amount', 15, 2)->default(0);
            $table->decimal('unit_cost_amount', 15, 2)->default(0);
            $table->decimal('surcharge_sale_amount', 15, 2)->default(0);
            $table->decimal('surcharge_cost_amount', 15, 2)->default(0);
            $table->decimal('total_sale_amount', 15, 2)->default(0);
            $table->decimal('total_cost_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('visa_group_id')->references('id')->on('umrah.visa_groups')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('transport_fare_id')->references('id')->on('umrah.transport_fares')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('transport_service_id')->references('id')->on('umrah.transport_services')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('transport_sector_id')->references('id')->on('umrah.transport_sectors')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('transport_package_id')->references('id')->on('umrah.transport_packages')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('driver_id')->references('id')->on('umrah.drivers')->nullOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'visa_group_id']);
        });

        DB::statement("ALTER TABLE umrah.group_transport_items ADD CONSTRAINT group_transport_items_terminal_check CHECK (terminal IN ('standard', 'hajj'))");
        DB::statement("ALTER TABLE umrah.group_transport_items ADD CONSTRAINT group_transport_items_basis_check CHECK (charging_basis IN ('per_vehicle', 'per_passenger', 'flat_group'))");

        foreach (['transport_sectors', 'transport_packages', 'transport_package_sectors', 'transport_fares', 'group_transport_items'] as $table) {
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

        $sectors = [
            ['JED-MAK', 'Jeddah Airport to Makkah Hotel', 'Jeddah Airport', 'Makkah Hotel'],
            ['MAK-MED', 'Makkah Hotel to Madinah Hotel', 'Makkah Hotel', 'Madinah Hotel'],
            ['MED-MAK', 'Madinah Hotel to Makkah Hotel', 'Madinah Hotel', 'Makkah Hotel'],
            ['MAK-JED', 'Makkah Hotel to Jeddah Airport', 'Makkah Hotel', 'Jeddah Airport'],
            ['MEDA-MED', 'Madinah Airport to Madinah Hotel', 'Madinah Airport', 'Madinah Hotel'],
            ['MED-MEDA', 'Madinah Hotel to Madinah Airport', 'Madinah Hotel', 'Madinah Airport'],
        ];

        DB::statement("SELECT set_config('app.is_super_admin', 'true', true)");

        foreach ($sectors as $index => [$code, $name, $origin, $destination]) {
            DB::statement(
                'INSERT INTO umrah.transport_sectors (id, company_id, code, name, origin, destination, sort_order, is_active, created_at, updated_at)
                 SELECT public.gen_random_uuid(), id, ?, ?, ?, ?, ?, true, NOW(), NOW() FROM auth.companies
                 ON CONFLICT (company_id, code) DO NOTHING',
                [$code, $name, $origin, $destination, $index + 1]
            );
        }

        DB::statement("SELECT set_config('app.is_super_admin', 'false', true)");
    }

    public function down(): void
    {
        foreach (['group_transport_items', 'transport_fares', 'transport_package_sectors', 'transport_packages', 'transport_sectors'] as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_company_isolation ON umrah.{$table}");
            Schema::dropIfExists("umrah.{$table}");
        }

        DB::statement('ALTER TABLE umrah.passengers DROP CONSTRAINT IF EXISTS passengers_service_type_check');
        Schema::table('umrah.passengers', function (Blueprint $table) {
            $table->dropColumn(['service_type', 'transport_charge_amount']);
        });

        DB::statement('ALTER TABLE umrah.visa_groups DROP CONSTRAINT IF EXISTS visa_groups_transport_mode_check');
        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->dropColumn(['transport_mode', 'included_bus_cost_per_passenger', 'included_bus_cost_deduction']);
        });

        Schema::table('umrah.visa_vendors', function (Blueprint $table) {
            $table->dropColumn('included_bus_cost_amount');
        });
    }
};
