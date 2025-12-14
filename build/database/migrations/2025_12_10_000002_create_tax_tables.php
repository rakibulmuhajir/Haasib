<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tax Management Tables Migration
 *
 * Creates: jurisdictions, tax_rates, tax_groups, tax_group_components,
 *          company_tax_settings, company_tax_registrations, tax_exemptions
 *
 * NOTE: All tables now use acct.* schema instead of tax.* schema
 *
 * Dependency order:
 * 1. jurisdictions (reference data, self-referential for hierarchy)
 * 2. tax_rates (references jurisdictions, acct.accounts)
 * 3. tax_groups (references jurisdictions)
 * 4. tax_group_components (references tax_groups, tax_rates)
 * 5. company_tax_settings (references jurisdictions, tax_rates)
 * 6. company_tax_registrations (references jurisdictions)
 * 7. tax_exemptions (company-only reference)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Jurisdictions (reference data for tax regions)
        Schema::create('acct.jurisdictions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('parent_id')->nullable();
            $table->char('country_code', 2);
            $table->string('code', 50);
            $table->string('name', 255);
            $table->string('level', 20)->default('country');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['country_code', 'code']);
            $table->index('country_code');
            $table->index('parent_id');
            $table->index('level');
        });

        // Seed with common jurisdictions
        DB::table('acct.jurisdictions')->insert([
            // Saudi Arabia
            ['id' => DB::raw('public.gen_random_uuid()'), 'country_code' => 'SA', 'code' => 'SA', 'name' => 'Saudi Arabia', 'level' => 'country', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => DB::raw('public.gen_random_uuid()'), 'country_code' => 'SA', 'code' => 'SA-Riyadh', 'name' => 'Riyadh Region', 'level' => 'state', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => DB::raw('public.gen_random_uuid()'), 'country_code' => 'SA', 'code' => 'SA-Mecca', 'name' => 'Mecca Region', 'level' => 'state', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => DB::raw('public.gen_random_uuid()'), 'country_code' => 'SA', 'code' => 'SA-Eastern', 'name' => 'Eastern Region', 'level' => 'state', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            // UAE
            ['id' => DB::raw('public.gen_random_uuid()'), 'country_code' => 'AE', 'code' => 'AE', 'name' => 'United Arab Emirates', 'level' => 'country', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            // Pakistan
            ['id' => DB::raw('public.gen_random_uuid()'), 'country_code' => 'PK', 'code' => 'PK', 'name' => 'Pakistan', 'level' => 'country', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            // United States
            ['id' => DB::raw('public.gen_random_uuid()'), 'country_code' => 'US', 'code' => 'US', 'name' => 'United States', 'level' => 'country', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => DB::raw('public.gen_random_uuid()'), 'country_code' => 'US', 'code' => 'US-CA', 'name' => 'California', 'level' => 'state', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => DB::raw('public.gen_random_uuid()'), 'country_code' => 'US', 'code' => 'US-NY', 'name' => 'New York', 'level' => 'state', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            // Europe
            ['id' => DB::raw('public.gen_random_uuid()'), 'country_code' => 'GB', 'code' => 'GB', 'name' => 'United Kingdom', 'level' => 'country', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => DB::raw('public.gen_random_uuid()'), 'country_code' => 'DE', 'code' => 'DE', 'name' => 'Germany', 'level' => 'country', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => DB::raw('public.gen_random_uuid()'), 'country_code' => 'FR', 'code' => 'FR', 'name' => 'France', 'level' => 'country', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Enable RLS for jurisdictions (read-only for tenants)
        DB::statement('ALTER TABLE acct.jurisdictions ENABLE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY jurisdictions_read_all ON acct.jurisdictions FOR SELECT USING (true)");
        DB::statement("CREATE POLICY jurisdictions_deny_writes ON acct.jurisdictions FOR INSERT WITH CHECK (false)");
        DB::statement("CREATE POLICY jurisdictions_deny_updates ON acct.jurisdictions FOR UPDATE USING (false)");
        DB::statement("CREATE POLICY jurisdictions_deny_deletes ON acct.jurisdictions FOR DELETE USING (false)");

        // 2. Tax Rates
        Schema::create('acct.tax_rates', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('jurisdiction_id');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->decimal('rate', 8, 4);
            $table->string('tax_type', 30)->default('sales');
            $table->boolean('is_compound')->default(false);
            $table->integer('compound_priority')->default(0);
            $table->uuid('gl_account_id')->nullable();
            $table->uuid('recoverable_account_id')->nullable();
            $table->date('effective_from')->default(DB::raw('CURRENT_DATE'));
            $table->date('effective_to')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('jurisdiction_id')->references('id')->on('acct.jurisdictions')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('gl_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('recoverable_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'code', 'effective_from'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index('jurisdiction_id');
            $table->index(['company_id', 'tax_type', 'is_active']);
            $table->index(['company_id', 'is_default']);
        });

        DB::statement("ALTER TABLE acct.tax_rates ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY tax_rates_policy ON acct.tax_rates
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        DB::statement("ALTER TABLE acct.tax_rates ADD CONSTRAINT tax_rates_rate_chk CHECK (rate >= 0 AND rate <= 100)");
        DB::statement("ALTER TABLE acct.tax_rates ADD CONSTRAINT tax_rates_tax_type_chk CHECK (tax_type IN ('sales', 'purchase', 'withholding', 'both'))");
        DB::statement("ALTER TABLE acct.tax_rates ADD CONSTRAINT tax_rates_effective_dates_chk CHECK (effective_to IS NULL OR effective_to > effective_from)");
        DB::statement("ALTER TABLE acct.tax_rates ADD CONSTRAINT tax_rates_compound_priority_chk CHECK (compound_priority >= 0)");

        // 3. Tax Groups
        Schema::create('acct.tax_groups', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('jurisdiction_id');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('jurisdiction_id')->references('id')->on('acct.jurisdictions')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'code'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
        });

        DB::statement("ALTER TABLE acct.tax_groups ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY tax_groups_policy ON acct.tax_groups
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // 4. Tax Group Components
        Schema::create('acct.tax_group_components', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('tax_group_id');
            $table->uuid('tax_rate_id');
            $table->smallInteger('priority')->default(1);
            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->foreign('tax_group_id')->references('id')->on('acct.tax_groups')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('tax_rate_id')->references('id')->on('acct.tax_rates')->restrictOnDelete()->cascadeOnUpdate();

            $table->unique(['tax_group_id', 'tax_rate_id']);
            $table->index('tax_group_id');
            $table->index('tax_rate_id');
        });

        DB::statement("ALTER TABLE acct.tax_group_components ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY tax_group_components_policy ON acct.tax_group_components
            FOR ALL USING (
                EXISTS (
                    SELECT 1 FROM acct.tax_groups
                    WHERE tax_groups.id = tax_group_components.tax_group_id
                    AND tax_groups.company_id = current_setting('app.current_company_id', true)::uuid
                ) OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // 5. Company Tax Settings
        Schema::create('acct.company_tax_settings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->boolean('tax_enabled')->default(false);
            $table->uuid('default_jurisdiction_id')->nullable();
            $table->uuid('default_sales_tax_rate_id')->nullable();
            $table->uuid('default_purchase_tax_rate_id')->nullable();
            $table->boolean('price_includes_tax')->default(false);
            $table->string('rounding_mode', 20)->default('half_up');
            $table->smallInteger('rounding_precision')->default(2);
            $table->string('tax_number_label', 50)->default('Tax ID');
            $table->boolean('show_tax_column')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('default_jurisdiction_id')->references('id')->on('acct.jurisdictions')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('default_sales_tax_rate_id')->references('id')->on('acct.tax_rates')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('default_purchase_tax_rate_id')->references('id')->on('acct.tax_rates')->nullOnDelete()->cascadeOnUpdate();

            $table->unique('company_id');
        });

        DB::statement("ALTER TABLE acct.company_tax_settings ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY company_tax_settings_policy ON acct.company_tax_settings
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        DB::statement("ALTER TABLE acct.company_tax_settings ADD CONSTRAINT company_tax_settings_rounding_precision_chk CHECK (rounding_precision >= 0 AND rounding_precision <= 6)");
        DB::statement("ALTER TABLE acct.company_tax_settings ADD CONSTRAINT company_tax_settings_rounding_mode_chk CHECK (rounding_mode IN ('half_up', 'half_down', 'floor', 'ceiling', 'bankers'))");

        // 6. Company Tax Registrations
        Schema::create('acct.company_tax_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('jurisdiction_id');
            $table->string('registration_number', 100);
            $table->string('registration_type', 50)->default('vat');
            $table->string('registered_name', 255)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('jurisdiction_id')->references('id')->on('acct.jurisdictions')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'jurisdiction_id', 'registration_number']);
            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
        });

        DB::statement("ALTER TABLE acct.company_tax_registrations ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY company_tax_registrations_policy ON acct.company_tax_registrations
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        DB::statement("ALTER TABLE acct.company_tax_registrations ADD CONSTRAINT company_tax_registrations_registration_type_chk CHECK (registration_type IN ('vat', 'gst', 'sales_tax', 'withholding', 'other'))");
        DB::statement("ALTER TABLE acct.company_tax_registrations ADD CONSTRAINT company_tax_registrations_effective_dates_chk CHECK (effective_to IS NULL OR effective_to > effective_from)");

        // 7. Tax Exemptions
        Schema::create('acct.tax_exemptions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('exemption_type', 30)->default('full');
            $table->decimal('override_rate', 8, 4)->nullable();
            $table->boolean('requires_certificate')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'code'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
        });

        DB::statement("ALTER TABLE acct.tax_exemptions ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY tax_exemptions_policy ON acct.tax_exemptions
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        DB::statement("ALTER TABLE acct.tax_exemptions ADD CONSTRAINT tax_exemptions_exemption_type_chk CHECK (exemption_type IN ('full', 'partial', 'rate_override'))");
        DB::statement("ALTER TABLE acct.tax_exemptions ADD CONSTRAINT tax_exemptions_override_rate_chk CHECK (override_rate IS NULL OR (override_rate >= 0 AND override_rate <= 100))");
        DB::statement("ALTER TABLE acct.tax_exemptions ADD CONSTRAINT tax_exemptions_override_rate_required_chk CHECK (exemption_type != 'rate_override' OR (exemption_type = 'rate_override' AND override_rate IS NOT NULL))");
    }

    public function down(): void
    {
        // Drop in reverse order
        DB::statement('DROP POLICY IF EXISTS tax_exemptions_policy ON acct.tax_exemptions');
        Schema::dropIfExists('acct.tax_exemptions');

        DB::statement('DROP POLICY IF EXISTS company_tax_registrations_policy ON acct.company_tax_registrations');
        Schema::dropIfExists('acct.company_tax_registrations');

        DB::statement('DROP POLICY IF EXISTS company_tax_settings_policy ON acct.company_tax_settings');
        Schema::dropIfExists('acct.company_tax_settings');

        DB::statement('DROP POLICY IF EXISTS tax_group_components_policy ON acct.tax_group_components');
        Schema::dropIfExists('acct.tax_group_components');

        DB::statement('DROP POLICY IF EXISTS tax_groups_policy ON acct.tax_groups');
        Schema::dropIfExists('acct.tax_groups');

        DB::statement('DROP POLICY IF EXISTS tax_rates_policy ON acct.tax_rates');
        Schema::dropIfExists('acct.tax_rates');

        DB::statement('DROP POLICY IF EXISTS jurisdictions_read_all ON acct.jurisdictions');
        DB::statement('DROP POLICY IF EXISTS jurisdictions_deny_writes ON acct.jurisdictions');
        DB::statement('DROP POLICY IF EXISTS jurisdictions_deny_updates ON acct.jurisdictions');
        DB::statement('DROP POLICY IF EXISTS jurisdictions_deny_deletes ON acct.jurisdictions');
        Schema::dropIfExists('acct.jurisdictions');
    }
};
