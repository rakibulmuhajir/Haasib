<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // This migration fixes the critical RLS issue where policies are not being enforced

        echo "Fixing RLS policies for multi-tenant isolation...\n";

        // Drop existing policies
        DB::statement('DROP POLICY IF EXISTS journal_entries_company_policy ON acct.journal_entries');
        DB::statement('DROP POLICY IF EXISTS journal_lines_company_policy ON acct.journal_lines');

        // Create comprehensive RLS policies

        // Journal Entries RLS Policy
        DB::statement("
            CREATE POLICY journal_entries_company_policy ON acct.journal_entries
            FOR ALL TO PUBLIC
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Journal Lines RLS Policy
        DB::statement("
            CREATE POLICY journal_lines_company_policy ON acct.journal_lines
            FOR ALL TO PUBLIC
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Enable RLS on all accounting tables
        DB::statement('ALTER TABLE acct.journal_entries ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.journal_lines ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.chart_of_accounts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.customers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.invoices ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.payments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.payment_allocations ENABLE ROW LEVEL SECURITY');

        // Force RLS enforcement for all tables
        DB::statement('ALTER TABLE acct.journal_entries FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.journal_lines FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.chart_of_accounts FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.customers FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.invoices FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.payments FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.payment_allocations FORCE ROW LEVEL SECURITY');

        echo "RLS policies have been recreated and enforced\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        echo "Reverting RLS policy changes...\n";

        // Drop all policies
        DB::statement('DROP POLICY IF EXISTS journal_entries_company_policy ON acct.journal_entries');
        DB::statement('DROP POLICY IF EXISTS journal_lines_company_policy ON acct.journal_lines');

        // Disable force RLS but keep RLS enabled
        DB::statement('ALTER TABLE acct.journal_entries NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.journal_lines NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.chart_of_accounts NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.customers NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.invoices NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.payments NO FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.payment_allocations NO FORCE ROW LEVEL SECURITY');

        echo "RLS policies have been reverted\n";
    }
};
