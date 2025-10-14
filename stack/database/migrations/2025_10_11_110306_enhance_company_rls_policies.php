<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure all company-related tables have proper RLS policies
        
        // 1. Companies table - Enhance existing policies
        // Drop existing policies to recreate them
        DB::statement('DROP POLICY IF EXISTS companies_select_policy ON auth.companies');
        DB::statement('DROP POLICY IF EXISTS companies_update_policy ON auth.companies');
        DB::statement('DROP POLICY IF EXISTS companies_insert_policy ON auth.companies');
        
        // Enhanced SELECT policy - users can see companies they belong to
        DB::statement("
            CREATE POLICY companies_select_policy ON auth.companies
            FOR SELECT
            USING (
                id IN (
                    SELECT company_id
                    FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND is_active = true
                )
                OR
                id IN (
                    SELECT company_id
                    FROM auth.company_invitations
                    WHERE email = (
                        SELECT email FROM auth.users 
                        WHERE id = current_setting('app.current_user_id', true)::uuid
                    )
                    AND status = 'pending'
                    AND expires_at > NOW()
                )
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Enhanced UPDATE policy - company owners and admins can update
        DB::statement("
            CREATE POLICY companies_update_policy ON auth.companies
            FOR UPDATE
            USING (
                id IN (
                    SELECT company_id
                    FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                    AND is_active = true
                )
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // INSERT policy - users can create companies (filtered at application level)
        DB::statement("
            CREATE POLICY companies_insert_policy ON auth.companies
            FOR INSERT
            WITH CHECK (
                current_setting('app.current_user_id', true)::uuid IS NOT NULL
            );
        ");

        // DELETE policy - company owners can delete
        DB::statement("
            CREATE POLICY companies_delete_policy ON auth.companies
            FOR DELETE
            USING (
                id IN (
                    SELECT company_id
                    FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role = 'owner'
                    AND is_active = true
                )
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // 2. Company user table - Add RLS if not exists
        if (Schema::hasTable('auth.company_user')) {
            DB::statement('ALTER TABLE auth.company_user ENABLE ROW LEVEL SECURITY');
            
            // Drop existing policies if they exist
            DB::statement('DROP POLICY IF EXISTS company_user_select_policy ON auth.company_user');
            DB::statement('DROP POLICY IF EXISTS company_user_update_policy ON auth.company_user');
            
            DB::statement("
                CREATE POLICY company_user_select_policy ON auth.company_user
                FOR SELECT
                USING (
                    user_id = current_setting('app.current_user_id', true)::uuid
                    OR
                    company_id IN (
                        SELECT company_id
                        FROM auth.company_user
                        WHERE user_id = current_setting('app.current_user_id', true)::uuid
                        AND is_active = true
                    )
                    OR
                    current_setting('app.is_super_admin', true)::boolean = true
                );
            ");

            DB::statement("
                CREATE POLICY company_user_update_policy ON auth.company_user
                FOR UPDATE
                USING (
                    company_id IN (
                        SELECT company_id
                        FROM auth.company_user
                        WHERE user_id = current_setting('app.current_user_id', true)::uuid
                        AND role IN ('owner', 'admin')
                        AND is_active = true
                    )
                    OR
                    current_setting('app.is_super_admin', true)::boolean = true
                );
            ");
        }

        // 3. Ensure accounting tables have company-scoped RLS
        // These will be created when accounting module tables exist
        $this->ensureAccountingRlsPolicies();
    }

    /**
     * Ensure accounting tables have proper RLS policies
     */
    private function ensureAccountingRlsPolicies(): void
    {
        // Check if fiscal_years table exists and add RLS if needed
        if (Schema::hasTable('acct.fiscal_years')) {
            DB::statement('ALTER TABLE acct.fiscal_years ENABLE ROW LEVEL SECURITY');
            
            // Drop existing policies if they exist
            DB::statement('DROP POLICY IF EXISTS fiscal_years_select_policy ON acct.fiscal_years');
            DB::statement('DROP POLICY IF EXISTS fiscal_years_update_policy ON acct.fiscal_years');
            
            DB::statement("
                CREATE POLICY fiscal_years_select_policy ON acct.fiscal_years
                FOR SELECT
                USING (
                    company_id IN (
                        SELECT company_id
                        FROM auth.company_user
                        WHERE user_id = current_setting('app.current_user_id', true)::uuid
                        AND is_active = true
                    )
                    OR
                    current_setting('app.is_super_admin', true)::boolean = true
                );
            ");

            DB::statement("
                CREATE POLICY fiscal_years_update_policy ON acct.fiscal_years
                FOR UPDATE
                USING (
                    company_id IN (
                        SELECT company_id
                        FROM auth.company_user
                        WHERE user_id = current_setting('app.current_user_id', true)::uuid
                        AND role IN ('owner', 'admin', 'accountant')
                        AND is_active = true
                    )
                    OR
                    current_setting('app.is_super_admin', true)::boolean = true
                );
            ");
        }

        // Check if accounting_periods table exists and add RLS if needed
        if (Schema::hasTable('acct.accounting_periods')) {
            DB::statement('ALTER TABLE acct.accounting_periods ENABLE ROW LEVEL SECURITY');
            
            // Drop existing policy if it exists
            DB::statement('DROP POLICY IF EXISTS accounting_periods_select_policy ON acct.accounting_periods');
            
            DB::statement("
                CREATE POLICY accounting_periods_select_policy ON acct.accounting_periods
                FOR SELECT
                USING (
                    fiscal_year_id IN (
                        SELECT id FROM acct.fiscal_years
                        WHERE company_id IN (
                            SELECT company_id
                            FROM auth.company_user
                            WHERE user_id = current_setting('app.current_user_id', true)::uuid
                            AND is_active = true
                        )
                    )
                    OR
                    current_setting('app.is_super_admin', true)::boolean = true
                );
            ");
        }

        // Chart_of_accounts table doesn't exist yet - will be handled when created
        // Accounts table doesn't exist yet - will be handled when created
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop enhanced company policies
        DB::statement('DROP POLICY IF EXISTS companies_select_policy ON auth.companies');
        DB::statement('DROP POLICY IF EXISTS companies_update_policy ON auth.companies');
        DB::statement('DROP POLICY IF EXISTS companies_insert_policy ON auth.companies');
        DB::statement('DROP POLICY IF EXISTS companies_delete_policy ON auth.companies');

        // Drop company user policies
        DB::statement('DROP POLICY IF EXISTS company_user_select_policy ON auth.company_user');
        DB::statement('DROP POLICY IF EXISTS company_user_update_policy ON auth.company_user');

        // Drop accounting policies
        DB::statement('DROP POLICY IF EXISTS fiscal_years_select_policy ON acct.fiscal_years');
        DB::statement('DROP POLICY IF EXISTS fiscal_years_update_policy ON acct.fiscal_years');
        DB::statement('DROP POLICY IF EXISTS accounting_periods_select_policy ON acct.accounting_periods');

        // Restore basic policies
        DB::statement("
            CREATE POLICY companies_select_policy ON auth.companies
            FOR SELECT
            USING (
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        DB::statement("
            CREATE POLICY companies_update_policy ON auth.companies
            FOR UPDATE
            USING (
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");
    }
};
