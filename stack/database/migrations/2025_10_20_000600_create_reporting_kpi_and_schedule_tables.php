<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create kpi_definitions table
        Schema::create('rpt.kpi_definitions', function (Blueprint $table) {
            $table->uuid('kpi_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('company_id')->nullable();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->jsonb('formula');
            $table->enum('visual_type', ['stat', 'trend', 'chart', 'gauge']);
            $table->enum('value_format', ['currency', 'percentage', 'days', 'number']);
            $table->jsonb('thresholds')->nullable();
            $table->enum('default_granularity', ['daily', 'weekly', 'monthly']);
            $table->boolean('allow_drilldown')->default(true);
            $table->boolean('is_global')->default(false);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['company_id']);
            $table->unique(['company_id', 'code']);
            $table->index(['is_global']);
            $table->index(['visual_type']);
            $table->unique(['code'])->where('is_global', true);
        });

        // Create kpi_snapshots table
        Schema::create('rpt.kpi_snapshots', function (Blueprint $table) {
            $table->bigIncrements('snapshot_id');
            $table->uuid('company_id');
            $table->uuid('kpi_id');
            $table->timestamp('captured_at');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('granularity', ['intraday', 'daily', 'monthly']);
            $table->decimal('value', 20, 4);
            $table->char('currency', 3)->nullable();
            $table->decimal('comparison_value', 20, 4)->nullable();
            $table->decimal('variance_percent', 10, 4)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['company_id']);
            $table->index(['kpi_id']);
            $table->index(['captured_at']);
            $table->index(['period_start', 'period_end']);
            $table->index(['granularity']);
            $table->unique(['kpi_id', 'period_start', 'period_end', 'granularity']);
        });

        // Create dashboard_layouts table
        Schema::create('rpt.dashboard_layouts', function (Blueprint $table) {
            $table->uuid('layout_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('owner_id')->nullable();
            $table->string('name', 150);
            $table->boolean('is_default')->default(false);
            $table->enum('visibility', ['private', 'company', 'role']);
            $table->jsonb('applies_to_roles')->nullable();
            $table->jsonb('cards');
            $table->jsonb('filters')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['company_id']);
            $table->index(['owner_id']);
            $table->index(['is_default']);
            $table->index(['visibility']);
            $table->unique(['company_id', 'visibility'], 'dashboard_layouts_company_visibility_unique');
        });

        // Create report_schedules table
        Schema::create('rpt.report_schedules', function (Blueprint $table) {
            $table->uuid('schedule_id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('company_id');
            $table->bigInteger('template_id');
            $table->string('name', 255);
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom']);
            $table->string('custom_cron', 100)->nullable();
            $table->timestamp('next_run_at');
            $table->timestamp('last_run_at')->nullable();
            $table->string('timezone', 50)->default('UTC');
            $table->jsonb('parameters')->nullable();
            $table->jsonb('delivery_channels');
            $table->enum('status', ['active', 'paused', 'archived'])->default('active');
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['company_id']);
            $table->index(['template_id']);
            $table->index(['frequency']);
            $table->index(['next_run_at']);
            $table->index(['status']);
            $table->index(['last_run_at']);
        });

        // Create report_deliveries table
        Schema::create('rpt.report_deliveries', function (Blueprint $table) {
            $table->bigIncrements('delivery_id');
            $table->uuid('company_id');
            $table->uuid('schedule_id')->nullable();
            $table->bigInteger('report_id');
            $table->enum('channel', ['email', 'sftp', 'webhook', 'in_app']);
            $table->jsonb('target');
            $table->enum('status', ['pending', 'sent', 'failed', 'retried'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['company_id']);
            $table->index(['schedule_id']);
            $table->index(['report_id']);
            $table->index(['channel']);
            $table->index(['status']);
            $table->index(['sent_at']);
        });

        // Enable RLS on all tables
        DB::statement('ALTER TABLE rpt.kpi_definitions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rpt.kpi_snapshots ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rpt.dashboard_layouts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rpt.report_schedules ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rpt.report_deliveries ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        $this->createRlsPolicies();

        // Create constraints
        $this->createConstraints();
    }

    /**
     * Create Row Level Security policies
     */
    private function createRlsPolicies(): void
    {
        // kpi_definitions RLS
        DB::statement("
            CREATE POLICY kpi_definitions_company_policy
            ON rpt.kpi_definitions
            FOR ALL
            USING (
                is_global = true OR 
                company_id = current_setting('app.current_company_id')::uuid
            )
            WITH CHECK (
                is_global = true OR 
                company_id = current_setting('app.current_company_id')::uuid
            )
        ");

        // kpi_snapshots RLS
        DB::statement("
            CREATE POLICY kpi_snapshots_company_policy
            ON rpt.kpi_snapshots
            FOR ALL
            USING (company_id = current_setting('app.current_company_id')::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id')::uuid)
        ");

        // dashboard_layouts RLS
        DB::statement("
            CREATE POLICY dashboard_layouts_company_policy
            ON rpt.dashboard_layouts
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id')::uuid
            )
            WITH CHECK (company_id = current_setting('app.current_company_id')::uuid)
        ");

        // report_schedules RLS
        DB::statement("
            CREATE POLICY report_schedules_company_policy
            ON rpt.report_schedules
            FOR ALL
            USING (company_id = current_setting('app.current_company_id')::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id')::uuid)
        ");

        // report_deliveries RLS
        DB::statement("
            CREATE POLICY report_deliveries_company_policy
            ON rpt.report_deliveries
            FOR ALL
            USING (company_id = current_setting('app.current_company_id')::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id')::uuid)
        ");
    }

    /**
     * Create table constraints
     */
    private function createConstraints(): void
    {
        // kpi_definitions constraints
        DB::statement('
            ALTER TABLE rpt.kpi_definitions
            ADD CONSTRAINT kpi_definitions_currency_check
            CHECK (
                value_format != \'currency\' OR 
                (formula::jsonb ? \'currency\')
            )
        ');

        // kpi_snapshots constraints
        DB::statement('
            ALTER TABLE rpt.kpi_snapshots
            ADD CONSTRAINT kpi_snapshots_currency_check
            CHECK (
                (value_format != \'currency\' OR currency IS NOT NULL)
            )
        ');

        // dashboard_layouts constraints
        DB::statement('
            ALTER TABLE rpt.dashboard_layouts
            ADD CONSTRAINT dashboard_layouts_default_per_role
            CHECK (
                NOT is_default OR 
                (visibility = \'private\' AND owner_id IS NOT NULL) OR
                (visibility = \'company\') OR
                (visibility = \'role\' AND applies_to_roles IS NOT NULL AND jsonb_array_length(applies_to_roles) > 0)
            )
        ');

        // report_schedules constraints
        DB::statement('
            ALTER TABLE rpt.report_schedules
            ADD CONSTRAINT report_schedules_custom_cron_check
            CHECK (
                frequency != \'custom\' OR custom_cron IS NOT NULL
            )
        ');

        DB::statement('
            ALTER TABLE rpt.report_schedules
            ADD CONSTRAINT report_schedules_next_run_future
            CHECK (next_run_at > CURRENT_TIMESTAMP)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop constraints
        DB::statement('ALTER TABLE rpt.kpi_definitions DROP CONSTRAINT IF EXISTS kpi_definitions_currency_check');
        DB::statement('ALTER TABLE rpt.kpi_snapshots DROP CONSTRAINT IF EXISTS kpi_snapshots_currency_check');
        DB::statement('ALTER TABLE rpt.dashboard_layouts DROP CONSTRAINT IF EXISTS dashboard_layouts_default_per_role');
        DB::statement('ALTER TABLE rpt.report_schedules DROP CONSTRAINT IF EXISTS report_schedules_custom_cron_check');
        DB::statement('ALTER TABLE rpt.report_schedules DROP CONSTRAINT IF EXISTS report_schedules_next_run_future');

        // Drop RLS policies
        DB::statement('DROP POLICY IF EXISTS kpi_definitions_company_policy ON rpt.kpi_definitions');
        DB::statement('DROP POLICY IF EXISTS kpi_snapshots_company_policy ON rpt.kpi_snapshots');
        DB::statement('DROP POLICY IF EXISTS dashboard_layouts_company_policy ON rpt.dashboard_layouts');
        DB::statement('DROP POLICY IF EXISTS report_schedules_company_policy ON rpt.report_schedules');
        DB::statement('DROP POLICY IF EXISTS report_deliveries_company_policy ON rpt.report_deliveries');

        // Disable RLS
        DB::statement('ALTER TABLE rpt.kpi_definitions DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rpt.kpi_snapshots DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rpt.dashboard_layouts DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rpt.report_schedules DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rpt.report_deliveries DISABLE ROW LEVEL SECURITY');

        // Drop tables
        Schema::dropIfExists('rpt.report_deliveries');
        Schema::dropIfExists('rpt.report_schedules');
        Schema::dropIfExists('rpt.dashboard_layouts');
        Schema::dropIfExists('rpt.kpi_snapshots');
        Schema::dropIfExists('rpt.kpi_definitions');
    }
};
