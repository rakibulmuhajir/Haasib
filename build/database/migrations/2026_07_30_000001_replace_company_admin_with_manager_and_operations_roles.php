<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE auth.company_user DROP CONSTRAINT IF EXISTS company_user_role_check');
        DB::statement('ALTER TABLE auth.company_invitations DROP CONSTRAINT IF EXISTS valid_role');
        DB::statement('ALTER TABLE auth.company_invitations DROP CONSTRAINT IF EXISTS company_invitations_role_check');

        DB::table('auth.company_user')->where('role', 'admin')->update(['role' => 'manager']);
        DB::table('auth.company_user')->whereIn('role', ['viewer', 'member'])->update(['role' => 'operations']);
        DB::table('auth.company_invitations')->where('role', 'admin')->update(['role' => 'manager']);
        DB::table('auth.company_invitations')->whereIn('role', ['viewer', 'member'])->update(['role' => 'operations']);

        DB::statement("ALTER TABLE auth.company_user ADD CONSTRAINT company_user_role_check CHECK (role IN ('owner', 'manager', 'accountant', 'operations', 'agent'))");
        DB::statement("ALTER TABLE auth.company_invitations ADD CONSTRAINT company_invitations_role_check CHECK (role IN ('owner', 'manager', 'accountant', 'operations', 'agent'))");
        DB::statement("ALTER TABLE auth.company_user ALTER COLUMN role SET DEFAULT 'operations'");

        foreach (['insert', 'update', 'delete'] as $action) {
            DB::statement("DROP POLICY IF EXISTS company_user_{$action}_policy ON auth.company_user");
        }
        DB::statement("
            CREATE POLICY company_user_insert_policy ON auth.company_user
            FOR INSERT WITH CHECK (
                company_id IN (
                    SELECT company_id FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'manager')
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");
        DB::statement("
            CREATE POLICY company_user_update_policy ON auth.company_user
            FOR UPDATE USING (
                company_id IN (
                    SELECT company_id FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'manager')
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");
        DB::statement("
            CREATE POLICY company_user_delete_policy ON auth.company_user
            FOR DELETE USING (
                company_id IN (
                    SELECT company_id FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'manager')
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE auth.company_user DROP CONSTRAINT IF EXISTS company_user_role_check');
        DB::statement('ALTER TABLE auth.company_invitations DROP CONSTRAINT IF EXISTS company_invitations_role_check');

        DB::table('auth.company_user')->where('role', 'manager')->update(['role' => 'admin']);
        DB::table('auth.company_user')->where('role', 'operations')->update(['role' => 'member']);
        DB::table('auth.company_invitations')->where('role', 'manager')->update(['role' => 'admin']);
        DB::table('auth.company_invitations')->where('role', 'operations')->update(['role' => 'member']);

        DB::statement("ALTER TABLE auth.company_user ADD CONSTRAINT company_user_role_check CHECK (role IN ('owner', 'admin', 'accountant', 'viewer', 'member', 'agent'))");
        DB::statement("ALTER TABLE auth.company_invitations ADD CONSTRAINT company_invitations_role_check CHECK (role IN ('owner', 'admin', 'accountant', 'viewer', 'member', 'agent'))");
        DB::statement("ALTER TABLE auth.company_user ALTER COLUMN role SET DEFAULT 'member'");

        foreach (['insert', 'update', 'delete'] as $action) {
            DB::statement("DROP POLICY IF EXISTS company_user_{$action}_policy ON auth.company_user");
        }
        DB::statement("
            CREATE POLICY company_user_insert_policy ON auth.company_user
            FOR INSERT WITH CHECK (
                company_id IN (
                    SELECT company_id FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");
        DB::statement("
            CREATE POLICY company_user_update_policy ON auth.company_user
            FOR UPDATE USING (
                company_id IN (
                    SELECT company_id FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");
        DB::statement("
            CREATE POLICY company_user_delete_policy ON auth.company_user
            FOR DELETE USING (
                company_id IN (
                    SELECT company_id FROM auth.company_user
                    WHERE user_id = current_setting('app.current_user_id', true)::uuid
                    AND role IN ('owner', 'admin')
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");
    }
};
