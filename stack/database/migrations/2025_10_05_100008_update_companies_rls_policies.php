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
        // Drop existing RLS policies for companies table
        DB::statement('DROP POLICY IF EXISTS companies_select_policy ON auth.companies');
        DB::statement('DROP POLICY IF EXISTS companies_update_policy ON auth.companies');

        // Create updated RLS policies for companies table that reference company_user
        // Policy: Users can see companies they belong to, superadmins can see all
        DB::statement("
            CREATE POLICY companies_select_policy ON auth.companies
            FOR SELECT
            USING (
                id IN (
                    SELECT company_id
                    FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                )
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Policy: Users can update companies they own or are admins of, superadmins can update all
        DB::statement("
            CREATE POLICY companies_update_policy ON auth.companies
            FOR UPDATE
            USING (
                id IN (
                    SELECT company_id
                    FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                )
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop updated RLS policies
        DB::statement('DROP POLICY IF EXISTS companies_select_policy ON auth.companies');
        DB::statement('DROP POLICY IF EXISTS companies_update_policy ON auth.companies');

        // Recreate basic RLS policies
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
