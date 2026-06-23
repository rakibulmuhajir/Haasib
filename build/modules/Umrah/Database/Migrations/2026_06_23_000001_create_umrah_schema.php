<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS umrah');

        Schema::create('umrah.agents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('agent_number', 50);
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('city', 100)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_receivable', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'agent_number']);
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('umrah.visa_vendors', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('vendor_number', 50);
            $table->string('name');
            $table->string('vendor_type', 30)->default('government');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('city', 100)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'vendor_number']);
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'vendor_type']);
        });

        DB::statement("ALTER TABLE umrah.visa_vendors ADD CONSTRAINT visa_vendors_type_check
            CHECK (vendor_type IN ('government', 'visa_provider', 'transport_provider', 'hotel', 'other'))");

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

        Schema::create('umrah.visa_groups', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('agent_id');
            $table->uuid('vendor_id')->nullable();
            $table->uuid('vehicle_type_id')->nullable();
            $table->string('group_number', 50);
            $table->string('name');
            $table->string('status', 30)->default('draft');
            $table->date('travel_date')->nullable();
            $table->jsonb('flight_info')->nullable();
            $table->jsonb('hotel_info')->nullable();
            $table->boolean('transport_required')->default(false);
            $table->integer('transport_quantity')->default(0);
            $table->integer('passenger_count')->default(0);
            $table->decimal('visa_sale_amount', 15, 2)->default(0);
            $table->decimal('transport_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('visa_cost_amount', 15, 2)->default(0);
            $table->decimal('total_receivable', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('profit', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('agent_id')->references('id')->on('umrah.agents')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('vendor_id')->references('id')->on('umrah.visa_vendors')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('vehicle_type_id')->references('id')->on('umrah.vehicle_types')->nullOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'group_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'travel_date']);
            $table->index(['company_id', 'agent_id']);
        });

        DB::statement("ALTER TABLE umrah.visa_groups ADD CONSTRAINT visa_groups_status_check
            CHECK (status IN ('draft', 'passports_received', 'submitted', 'visa_approved', 'delivered', 'closed', 'cancelled'))");

        Schema::create('umrah.passengers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('visa_group_id');
            $table->string('full_name');
            $table->string('passport_number', 100)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('visa_status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('visa_group_id')->references('id')->on('umrah.visa_groups')->cascadeOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'visa_group_id']);
            $table->index(['company_id', 'passport_number']);
        });

        DB::statement("ALTER TABLE umrah.passengers ADD CONSTRAINT passengers_visa_status_check
            CHECK (visa_status IN ('pending', 'received', 'submitted', 'approved', 'rejected', 'delivered'))");

        Schema::create('umrah.group_payments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('visa_group_id');
            $table->uuid('agent_id');
            $table->uuid('account_id')->nullable();
            $table->string('payment_number', 50);
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('method', 30)->default('cash');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('visa_group_id')->references('id')->on('umrah.visa_groups')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('agent_id')->references('id')->on('umrah.agents')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'payment_number']);
            $table->index(['company_id', 'payment_date']);
            $table->index(['company_id', 'agent_id']);
        });

        DB::statement("ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_method_check
            CHECK (method IN ('cash', 'bank_transfer', 'card', 'wallet', 'other'))");

        foreach ([
            'agents',
            'visa_vendors',
            'vehicle_types',
            'visa_groups',
            'passengers',
            'group_payments',
        ] as $table) {
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
        foreach ([
            'group_payments',
            'passengers',
            'visa_groups',
            'vehicle_types',
            'visa_vendors',
            'agents',
        ] as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_company_isolation ON umrah.{$table}");
            Schema::dropIfExists("umrah.{$table}");
        }

        DB::statement('DROP SCHEMA IF EXISTS umrah');
    }
};
