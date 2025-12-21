<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor: Move fuel-specific fields from core accounting tables to separate fuel tables.
 *
 * This keeps the core accounting module industry-agnostic:
 * - acct.customers stays generic
 * - acct.invoices stays generic
 *
 * Fuel-specific data moves to:
 * - fuel.customer_profiles (links to acct.customers)
 * - fuel.sale_metadata (links to acct.invoices)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // Step 1: Create fuel.customer_profiles table
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.customer_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('customer_id')->unique(); // 1:1 with acct.customers

            // Non-exclusive customer type flags
            $table->boolean('is_credit_customer')->default(false);
            $table->boolean('is_amanat_holder')->default(false);
            $table->boolean('is_investor')->default(false);

            // Fuel-specific customer data
            $table->string('relationship', 50)->nullable(); // owner, employee, external
            $table->string('cnic', 20)->nullable(); // Pakistani national ID
            $table->decimal('amanat_balance', 15, 2)->default(0);

            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('customer_id')
                ->references('id')->on('acct.customers')
                ->cascadeOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index(['company_id', 'is_credit_customer']);
            $table->index(['company_id', 'is_amanat_holder']);
            $table->index(['company_id', 'is_investor']);
        });

        // Check constraint for relationship enum
        DB::statement("ALTER TABLE fuel.customer_profiles ADD CONSTRAINT customer_profiles_relationship_check
            CHECK (relationship IS NULL OR relationship IN ('owner', 'employee', 'external'))");

        // RLS policy
        DB::statement('ALTER TABLE fuel.customer_profiles ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY customer_profiles_company_isolation ON fuel.customer_profiles
            FOR ALL
            USING (
                company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // ─────────────────────────────────────────────────────────────────────
        // Step 2: Create fuel.sale_metadata table
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('fuel.sale_metadata', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('invoice_id')->unique(); // 1:1 with acct.invoices

            // Fuel sale specific fields
            $table->string('sale_type', 20); // retail, bulk, credit, amanat, investor, parco_card
            $table->uuid('pump_id')->nullable();
            $table->boolean('attendant_transit')->default(false);
            $table->string('discount_reason', 50)->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('invoice_id')
                ->references('id')->on('acct.invoices')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('pump_id')
                ->references('id')->on('fuel.pumps')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index(['company_id', 'sale_type']);
            $table->index(['company_id', 'attendant_transit']);
        });

        // Check constraint for sale_type enum
        DB::statement("ALTER TABLE fuel.sale_metadata ADD CONSTRAINT sale_metadata_sale_type_check
            CHECK (sale_type IN ('retail', 'bulk', 'credit', 'amanat', 'investor', 'parco_card'))");

        // RLS policy
        DB::statement('ALTER TABLE fuel.sale_metadata ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY sale_metadata_company_isolation ON fuel.sale_metadata
            FOR ALL
            USING (
                company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // ─────────────────────────────────────────────────────────────────────
        // Step 3: Remove fuel-specific columns from acct.invoices
        // ─────────────────────────────────────────────────────────────────────
        DB::statement('ALTER TABLE acct.invoices DROP CONSTRAINT IF EXISTS invoices_pump_id_fk');
        DB::statement('DROP INDEX IF EXISTS acct.invoices_attendant_transit_idx');
        DB::statement('DROP INDEX IF EXISTS acct.invoices_sale_type_idx');
        DB::statement('ALTER TABLE acct.invoices DROP CONSTRAINT IF EXISTS invoices_sale_type_check');

        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->dropColumn(['sale_type', 'pump_id', 'attendant_transit', 'discount_reason']);
        });

        // ─────────────────────────────────────────────────────────────────────
        // Step 4: Remove fuel-specific columns from acct.customers
        // ─────────────────────────────────────────────────────────────────────
        DB::statement('DROP INDEX IF EXISTS acct.customers_investor_idx');
        DB::statement('DROP INDEX IF EXISTS acct.customers_amanat_idx');
        DB::statement('DROP INDEX IF EXISTS acct.customers_credit_idx');
        DB::statement('ALTER TABLE acct.customers DROP CONSTRAINT IF EXISTS customers_relationship_check');

        Schema::table('acct.customers', function (Blueprint $table) {
            $table->dropColumn([
                'is_credit_customer',
                'is_amanat_holder',
                'is_investor',
                'relationship',
                'cnic',
                'amanat_balance',
            ]);
        });
    }

    public function down(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // Restore columns to acct.customers
        // ─────────────────────────────────────────────────────────────────────
        Schema::table('acct.customers', function (Blueprint $table) {
            $table->boolean('is_credit_customer')->default(false)->after('is_active');
            $table->boolean('is_amanat_holder')->default(false)->after('is_credit_customer');
            $table->boolean('is_investor')->default(false)->after('is_amanat_holder');
            $table->string('relationship', 50)->nullable()->after('is_investor');
            $table->string('cnic', 20)->nullable()->after('relationship');
            $table->decimal('amanat_balance', 15, 2)->default(0)->after('cnic');
        });

        DB::statement("ALTER TABLE acct.customers ADD CONSTRAINT customers_relationship_check
            CHECK (relationship IS NULL OR relationship IN ('owner', 'employee', 'external'))");
        DB::statement('CREATE INDEX customers_credit_idx ON acct.customers (company_id) WHERE is_credit_customer = true');
        DB::statement('CREATE INDEX customers_amanat_idx ON acct.customers (company_id) WHERE is_amanat_holder = true');
        DB::statement('CREATE INDEX customers_investor_idx ON acct.customers (company_id) WHERE is_investor = true');

        // ─────────────────────────────────────────────────────────────────────
        // Restore columns to acct.invoices
        // ─────────────────────────────────────────────────────────────────────
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->string('sale_type', 20)->nullable()->after('status');
            $table->uuid('pump_id')->nullable()->after('sale_type');
            $table->boolean('attendant_transit')->default(false)->after('pump_id');
            $table->string('discount_reason', 50)->nullable()->after('attendant_transit');
        });

        DB::statement("ALTER TABLE acct.invoices ADD CONSTRAINT invoices_sale_type_check
            CHECK (sale_type IS NULL OR sale_type IN ('retail', 'bulk', 'credit', 'amanat', 'investor', 'parco_card'))");
        DB::statement('CREATE INDEX invoices_sale_type_idx ON acct.invoices (company_id, sale_type) WHERE sale_type IS NOT NULL');
        DB::statement('CREATE INDEX invoices_attendant_transit_idx ON acct.invoices (company_id) WHERE attendant_transit = true');
        DB::statement('ALTER TABLE acct.invoices ADD CONSTRAINT invoices_pump_id_fk FOREIGN KEY (pump_id)
            REFERENCES fuel.pumps(id) ON DELETE SET NULL ON UPDATE CASCADE');

        // ─────────────────────────────────────────────────────────────────────
        // Drop the new fuel tables
        // ─────────────────────────────────────────────────────────────────────
        Schema::dropIfExists('fuel.sale_metadata');
        Schema::dropIfExists('fuel.customer_profiles');
    }
};
