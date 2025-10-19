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
        // Create report_templates table
        Schema::create('rpt.report_templates', function (Blueprint $table) {
            $table->bigIncrements('template_id');
            $table->uuid('company_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('report_type', ['income_statement', 'balance_sheet', 'cash_flow', 'trial_balance', 'kpi_dashboard', 'custom']);
            $table->enum('category', ['financial', 'operational', 'analytical']);
            $table->jsonb('configuration');
            $table->jsonb('filters')->nullable();
            $table->jsonb('parameters')->nullable();
            $table->boolean('is_system_template')->default(false);
            $table->boolean('is_public')->default(false);
            $table->integer('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['company_id']);
            $table->unique(['company_id', 'name', 'report_type']);
            $table->index(['report_type']);
            $table->index(['is_public']);
            $table->index(['sort_order']);
        });

        // Create reports table
        Schema::create('rpt.reports', function (Blueprint $table) {
            $table->bigIncrements('report_id');
            $table->uuid('company_id');
            $table->bigInteger('template_id')->nullable();
            $table->enum('report_type', ['income_statement', 'balance_sheet', 'cash_flow', 'trial_balance', 'kpi_dashboard', 'custom']);
            $table->string('name', 255);
            $table->jsonb('parameters')->nullable();
            $table->jsonb('filters')->nullable();
            $table->date('date_range_start')->nullable();
            $table->date('date_range_end')->nullable();
            $table->enum('status', ['queued', 'running', 'generated', 'failed', 'expired'])->default('queued');
            $table->jsonb('payload')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            // Indexes
            $table->index(['company_id']);
            $table->index(['template_id']);
            $table->index(['report_type']);
            $table->index(['status']);
            $table->index(['created_at']);
            $table->index(['expires_at']);
        });

        // Create financial_statements table
        Schema::create('rpt.financial_statements', function (Blueprint $table) {
            $table->bigIncrements('statement_id');
            $table->uuid('company_id');
            $table->uuid('fiscal_year_id')->nullable();
            $table->uuid('period_id')->nullable();
            $table->enum('statement_type', ['balance_sheet', 'income_statement', 'cash_flow', 'equity']);
            $table->string('name', 255);
            $table->date('statement_date');
            $table->date('date_range_start')->nullable();
            $table->date('date_range_end')->nullable();
            $table->jsonb('data');
            $table->jsonb('totals')->nullable();
            $table->jsonb('comparative_data')->nullable();
            $table->char('currency', 3);
            $table->jsonb('exchange_rate_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'finalized', 'published'])->default('draft');
            $table->integer('version')->default(1);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('finalized_by')->nullable();
            $table->timestamps();
            $table->timestamp('finalized_at')->nullable();

            // Indexes
            $table->index(['company_id']);
            $table->index(['fiscal_year_id']);
            $table->index(['period_id']);
            $table->index(['statement_type']);
            $table->index(['statement_date']);
            $table->index(['status']);
            $table->unique(['company_id', 'statement_type', 'period_id', 'version']);
        });

        // Create financial_statement_lines table
        Schema::create('rpt.financial_statement_lines', function (Blueprint $table) {
            $table->bigIncrements('line_id');
            $table->bigInteger('statement_id');
            $table->uuid('company_id');
            $table->string('section', 100);
            $table->integer('display_order');
            $table->string('account_code', 50)->nullable();
            $table->string('label', 255);
            $table->decimal('amount', 20, 4);
            $table->char('currency', 3);
            $table->decimal('comparative_amount', 20, 4)->nullable();
            $table->decimal('variance_amount', 20, 4)->nullable();
            $table->decimal('variance_percent', 10, 4)->nullable();
            $table->string('source_reference_type', 100)->nullable();
            $table->uuid('source_reference_id')->nullable();
            $table->jsonb('drilldown_context')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['statement_id']);
            $table->index(['company_id']);
            $table->index(['section', 'display_order']);
            $table->index(['account_code']);
            $table->index(['source_reference_type', 'source_reference_id']);
        });

        // Enable RLS on all tables
        DB::statement('ALTER TABLE rpt.report_templates ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rpt.reports ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rpt.financial_statements ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rpt.financial_statement_lines ENABLE ROW LEVEL SECURITY');

        // Create RLS policies
        $this->createRlsPolicies();
    }

    /**
     * Create Row Level Security policies
     */
    private function createRlsPolicies(): void
    {
        // report_templates RLS
        DB::statement("
            CREATE POLICY report_templates_company_policy
            ON rpt.report_templates
            FOR ALL
            USING (company_id = current_setting('app.current_company_id')::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id')::uuid)
        ");

        // reports RLS
        DB::statement("
            CREATE POLICY reports_company_policy
            ON rpt.reports
            FOR ALL
            USING (company_id = current_setting('app.current_company_id')::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id')::uuid)
        ");

        // financial_statements RLS
        DB::statement("
            CREATE POLICY financial_statements_company_policy
            ON rpt.financial_statements
            FOR ALL
            USING (company_id = current_setting('app.current_company_id')::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id')::uuid)
        ");

        // financial_statement_lines RLS
        DB::statement("
            CREATE POLICY financial_statement_lines_company_policy
            ON rpt.financial_statement_lines
            FOR ALL
            USING (company_id = current_setting('app.current_company_id')::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id')::uuid)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop RLS policies
        DB::statement('DROP POLICY IF EXISTS report_templates_company_policy ON rpt.report_templates');
        DB::statement('DROP POLICY IF EXISTS reports_company_policy ON rpt.reports');
        DB::statement('DROP POLICY IF EXISTS financial_statements_company_policy ON rpt.financial_statements');
        DB::statement('DROP POLICY IF EXISTS financial_statement_lines_company_policy ON rpt.financial_statement_lines');

        // Disable RLS
        DB::statement('ALTER TABLE rpt.report_templates DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rpt.reports DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rpt.financial_statements DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE rpt.financial_statement_lines DISABLE ROW LEVEL SECURITY');

        // Drop tables
        Schema::dropIfExists('rpt.financial_statement_lines');
        Schema::dropIfExists('rpt.financial_statements');
        Schema::dropIfExists('rpt.reports');
        Schema::dropIfExists('rpt.report_templates');
    }
};
