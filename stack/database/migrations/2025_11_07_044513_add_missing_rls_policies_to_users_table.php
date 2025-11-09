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
        // Drop existing policies to recreate them with proper INSERT/DELETE support
        DB::statement('DROP POLICY IF EXISTS users_select_policy ON auth.users');
        DB::statement('DROP POLICY IF EXISTS users_update_policy ON auth.users');

        // Create comprehensive RLS policies for users table
        DB::statement("
            CREATE POLICY users_select_policy ON auth.users
            FOR SELECT USING (
                id = (current_setting('app.current_user_id', true))::uuid 
                OR system_role = 'superadmin' 
                OR (current_setting('app.is_super_admin', true))::boolean = true
            )
        ");

        DB::statement("
            CREATE POLICY users_insert_policy ON auth.users
            FOR INSERT WITH CHECK (
                (current_setting('app.is_super_admin', true))::boolean = true
                OR system_role = 'superadmin'
                OR (
                    -- Allow user creation during registration when no user context is set
                    current_setting('app.current_user_id', true) IS NULL
                    AND system_role IN ('company_owner', 'company_admin', 'manager', 'employee', 'viewer')
                )
            )
        ");

        DB::statement("
            CREATE POLICY users_update_policy ON auth.users
            FOR UPDATE USING (
                id = (current_setting('app.current_user_id', true))::uuid 
                OR system_role = 'superadmin' 
                OR (current_setting('app.is_super_admin', true))::boolean = true
            )
        ");

        DB::statement("
            CREATE POLICY users_delete_policy ON auth.users
            FOR DELETE USING (
                system_role = 'superadmin' 
                OR (current_setting('app.is_super_admin', true))::boolean = true
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS users_select_policy ON auth.users');
        DB::statement('DROP POLICY IF EXISTS users_insert_policy ON auth.users');
        DB::statement('DROP POLICY IF EXISTS users_update_policy ON auth.users');
        DB::statement('DROP POLICY IF EXISTS users_delete_policy ON auth.users');

        // Recreate original policies for backwards compatibility
        DB::statement("
            CREATE POLICY users_select_policy ON auth.users
            FOR SELECT USING (
                id = (current_setting('app.current_user_id', true))::uuid 
                OR system_role = 'superadmin' 
                OR (current_setting('app.is_super_admin', true))::boolean = true
            )
        ");

        DB::statement("
            CREATE POLICY users_update_policy ON auth.users
            FOR UPDATE USING (
                id = (current_setting('app.current_user_id', true))::uuid 
                OR system_role = 'superadmin' 
                OR (current_setting('app.is_super_admin', true))::boolean = true
            )
        ");
    }
};
