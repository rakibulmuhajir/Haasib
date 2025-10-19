<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create audit function for reporting tables
        $this->createAuditFunction();

        // Create audit triggers for all reporting tables
        $this->createAuditTriggers();

        // Create materialized views for reporting
        $this->createMaterializedViews();

        // Create refresh functions for materialized views
        $this->createRefreshFunctions();

        // Create additional indexes for performance
        $this->createPerformanceIndexes();

        // Create view for transaction drilldown
        $this->createDrilldownView();
    }

    /**
     * Create audit function for reporting tables
     */
    private function createAuditFunction(): void
    {
        DB::statement('
            CREATE OR REPLACE FUNCTION rpt.audit_reporting_function()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = \'INSERT\' THEN
                    PERFORM audit_log(
                        \'reporting\',
                        TG_TABLE_NAME,
                        \'INSERT\',
                        row_to_json(NEW),
                        NULL,
                        current_setting(\'app.current_user_id\', true)::uuid,
                        current_setting(\'app.current_company_id\', true)::uuid
                    );
                    RETURN NEW;
                ELSIF TG_OP = \'UPDATE\' THEN
                    PERFORM audit_log(
                        \'reporting\',
                        TG_TABLE_NAME,
                        \'UPDATE\',
                        row_to_json(NEW),
                        row_to_json(OLD),
                        current_setting(\'app.current_user_id\', true)::uuid,
                        current_setting(\'app.current_company_id\', true)::uuid
                    );
                    RETURN NEW;
                ELSIF TG_OP = \'DELETE\' THEN
                    PERFORM audit_log(
                        \'reporting\',
                        TG_TABLE_NAME,
                        \'DELETE\',
                        NULL,
                        row_to_json(OLD),
                        current_setting(\'app.current_user_id\', true)::uuid,
                        current_setting(\'app.current_company_id\', true)::uuid
                    );
                    RETURN OLD;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER
        ');
    }

    /**
     * Create audit triggers for reporting tables
     */
    private function createAuditTriggers(): void
    {
        $tables = [
            'rpt.report_templates',
            'rpt.reports',
            'rpt.financial_statements',
            'rpt.financial_statement_lines',
            'rpt.kpi_definitions',
            'rpt.kpi_snapshots',
            'rpt.dashboard_layouts',
            'rpt.report_schedules',
            'rpt.report_deliveries',
        ];

        foreach ($tables as $table) {
            $tableName = str_replace('rpt.', '', $table);
            $triggerName = "audit_{$tableName}_trigger";

            DB::statement("
                CREATE TRIGGER {$triggerName}
                AFTER INSERT OR UPDATE OR DELETE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION rpt.audit_reporting_function()
            ");
        }
    }

    /**
     * Create materialized views for reporting
     */
    private function createMaterializedViews(): void
    {
        // Trial balance current view
        DB::statement('
            CREATE MATERIALIZED VIEW rpt.mv_trial_balance_current AS
            SELECT 
                jl.company_id,
                a.account_code,
                a.account_name,
                a.account_type,
                COALESCE(SUM(CASE WHEN jl.amount >= 0 THEN jl.amount ELSE 0 END), 0) as debit_balance,
                COALESCE(SUM(CASE WHEN jl.amount < 0 THEN ABS(jl.amount) ELSE 0 END), 0) as credit_balance,
                CASE 
                    WHEN a.account_type IN (\'Asset\', \'Expense\') THEN 
                        COALESCE(SUM(CASE WHEN jl.amount >= 0 THEN jl.amount ELSE 0 END), 0) - 
                        COALESCE(SUM(CASE WHEN jl.amount < 0 THEN ABS(jl.amount) ELSE 0 END), 0)
                    ELSE 
                        COALESCE(SUM(CASE WHEN jl.amount < 0 THEN ABS(jl.amount) ELSE 0 END), 0) - 
                        COALESCE(SUM(CASE WHEN jl.amount >= 0 THEN jl.amount ELSE 0 END), 0)
                END as balance,
                c.currency_code as currency,
                CURRENT_TIMESTAMP as refreshed_at
            FROM ledger.journal_lines jl
            JOIN ledger.journal_entries je ON jl.journal_entry_id = je.id
            JOIN acct.accounts a ON jl.account_id = a.id
            JOIN auth.companies c ON jl.company_id = c.id
            WHERE jl.company_id = current_setting(\'app.current_company_id\', true)::uuid
            GROUP BY jl.company_id, a.account_code, a.account_name, a.account_type, c.currency_code
        ');

        // Income statement monthly view
        DB::statement('
            CREATE MATERIALIZED VIEW rpt.mv_income_statement_monthly AS
            SELECT 
                jl.company_id,
                DATE_TRUNC(\'month\', je.entry_date) as month,
                a.account_type,
                a.account_category,
                COALESCE(SUM(jl.amount), 0) as amount,
                c.currency_code as currency,
                CURRENT_TIMESTAMP as refreshed_at
            FROM ledger.journal_lines jl
            JOIN ledger.journal_entries je ON jl.journal_entry_id = je.id
            JOIN acct.accounts a ON jl.account_id = a.id
            JOIN auth.companies c ON jl.company_id = c.id
            WHERE jl.company_id = current_setting(\'app.current_company_id\', true)::uuid
            AND a.account_type IN (\'Revenue\', \'Expense\')
            GROUP BY jl.company_id, DATE_TRUNC(\'month\', je.entry_date), a.account_type, a.account_category, c.currency_code
        ');

        // Cash flow daily view
        DB::statement('
            CREATE MATERIALIZED VIEW rpt.mv_cash_flow_daily AS
            SELECT 
                jl.company_id,
                je.entry_date as cash_flow_date,
                CASE 
                    WHEN a.account_category = \'Cash\' THEN \'operating\'
                    WHEN a.account_category = \'Investment\' THEN \'investing\'
                    WHEN a.account_category = \'Financing\' THEN \'financing\'
                    ELSE \'opering\'
                END as cash_flow_type,
                a.account_category,
                COALESCE(SUM(jl.amount), 0) as amount,
                c.currency_code as currency,
                CURRENT_TIMESTAMP as refreshed_at
            FROM ledger.journal_lines jl
            JOIN ledger.journal_entries je ON jl.journal_entry_id = je.id
            JOIN acct.accounts a ON jl.account_id = a.id
            JOIN auth.companies c ON jl.company_id = c.id
            WHERE jl.company_id = current_setting(\'app.current_company_id\', true)::uuid
            AND a.account_category IN (\'Cash\', \'Investment\', \'Financing\')
            GROUP BY jl.company_id, je.entry_date, a.account_category, c.currency_code
        ');
    }

    /**
     * Create refresh functions for materialized views
     */
    private function createRefreshFunctions(): void
    {
        // Refresh all materialized views function
        DB::statement('
            CREATE OR REPLACE FUNCTION rpt.refresh_reporting_materialized_views(p_company_id UUID)
            RETURNS void AS $$
            BEGIN
                -- Set company context for RLS
                PERFORM set_config(\'app.current_company_id\', p_company_id::text, true);
                
                -- Refresh trial balance
                REFRESH MATERIALIZED VIEW CONCURRENTLY rpt.mv_trial_balance_current;
                
                -- Refresh income statement
                REFRESH MATERIALIZED VIEW CONCURRENTLY rpt.mv_income_statement_monthly;
                
                -- Refresh cash flow
                REFRESH MATERIALIZED VIEW CONCURRENTLY rpt.mv_cash_flow_daily;
                
                -- Reset company context
                PERFORM set_config(\'app.current_company_id\', \'\', true);
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER
        ');

        // Refresh single view function
        DB::statement('
            CREATE OR REPLACE FUNCTION rpt.refresh_single_view(p_view_name TEXT, p_company_id UUID)
            RETURNS void AS $$
            BEGIN
                -- Set company context for RLS
                PERFORM set_config(\'app.current_company_id\', p_company_id::text, true);
                
                CASE p_view_name
                    WHEN \'trial_balance\' THEN
                        REFRESH MATERIALIZED VIEW CONCURRENTLY rpt.mv_trial_balance_current;
                    WHEN \'income_statement\' THEN
                        REFRESH MATERIALIZED VIEW CONCURRENTLY rpt.mv_income_statement_monthly;
                    WHEN \'cash_flow\' THEN
                        REFRESH MATERIALIZED VIEW CONCURRENTLY rpt.mv_cash_flow_daily;
                END CASE;
                
                -- Reset company context
                PERFORM set_config(\'app.current_company_id\', \'\', true);
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER
        ');
    }

    /**
     * Create performance indexes
     */
    private function createPerformanceIndexes(): void
    {
        // Indexes for reports table
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_reports_company_status_created ON rpt.reports (company_id, status, created_at)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_reports_template_type ON rpt.reports (template_id, report_type)');

        // Indexes for kpi_snapshots
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_kpi_snapshots_company_kpi_period ON rpt.kpi_snapshots (company_id, kpi_id, captured_at)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_kpi_snapshots_kpi_granularity ON rpt.kpi_snapshots (kpi_id, granularity, captured_at)');

        // Indexes for financial_statement_lines
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_fin_stmt_lines_statement_section ON rpt.financial_statement_lines (statement_id, section, display_order)');

        // Indexes for materialized views
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_mv_trial_balance_company_account ON rpt.mv_trial_balance_current (company_id, account_code)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_mv_income_statement_company_month ON rpt.mv_income_statement_monthly (company_id, month)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_mv_cash_flow_company_date ON rpt.mv_cash_flow_daily (company_id, cash_flow_date)');
    }

    /**
     * Create drilldown view for transactions
     */
    private function createDrilldownView(): void
    {
        DB::statement('
            CREATE OR REPLACE VIEW rpt.v_transaction_drilldown AS
            SELECT 
                jl.id as journal_line_id,
                jl.amount,
                jl.description,
                jl.company_id,
                je.entry_date,
                je.entry_number,
                je.description as entry_description,
                a.account_code,
                a.account_name,
                a.account_type,
                a.account_category,
                c.counterparty_name,
                c.counterparty_type,
                dr.reference_number,
                dr.reference_type,
                cu.name as currency_name,
                cu.symbol as currency_symbol,
                CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM auth.company_user cu_join 
                        WHERE cu_join.company_id = jl.company_id 
                        AND cu_join.user_id = current_setting(\'app.current_user_id\', true)::uuid
                    ) THEN true
                    ELSE false
                END as has_access
            FROM ledger.journal_lines jl
            JOIN ledger.journal_entries je ON jl.journal_entry_id = je.id
            JOIN acct.accounts a ON jl.account_id = a.id
            LEFT JOIN acct.counterparties c ON jl.counterparty_id = c.id
            LEFT JOIN ledger.document_references dr ON jl.document_reference_id = dr.id
            LEFT JOIN public.currencies cu ON jl.currency_code = cu.code
            WHERE jl.company_id = current_setting(\'app.current_company_id\', true)::uuid
        ');

        // Grant permissions
        DB::statement('GRANT SELECT ON rpt.v_transaction_drilldown TO authenticated');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop drilldown view
        DB::statement('DROP VIEW IF EXISTS rpt.v_transaction_drilldown');

        // Drop performance indexes
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_reports_company_status_created');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_reports_template_type');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_kpi_snapshots_company_kpi_period');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_kpi_snapshots_kpi_granularity');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_fin_stmt_lines_statement_section');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_mv_trial_balance_company_account');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_mv_income_statement_company_month');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS idx_mv_cash_flow_company_date');

        // Drop refresh functions
        DB::statement('DROP FUNCTION IF EXISTS rpt.refresh_single_view(TEXT, UUID)');
        DB::statement('DROP FUNCTION IF EXISTS rpt.refresh_reporting_materialized_views(UUID)');

        // Drop materialized views
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS rpt.mv_cash_flow_daily');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS rpt.mv_income_statement_monthly');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS rpt.mv_trial_balance_current');

        // Drop audit triggers
        $tables = [
            'rpt.report_templates',
            'rpt.reports',
            'rpt.financial_statements',
            'rpt.financial_statement_lines',
            'rpt.kpi_definitions',
            'rpt.kpi_snapshots',
            'rpt.dashboard_layouts',
            'rpt.report_schedules',
            'rpt.report_deliveries',
        ];

        foreach ($tables as $table) {
            $tableName = str_replace('rpt.', '', $table);
            $triggerName = "audit_{$tableName}_trigger";
            DB::statement("DROP TRIGGER IF EXISTS {$triggerName} ON {$table}");
        }

        // Drop audit function
        DB::statement('DROP FUNCTION IF EXISTS rpt.audit_reporting_function()');
    }
};
