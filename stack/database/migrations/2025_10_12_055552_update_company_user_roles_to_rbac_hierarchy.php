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
        // Drop existing check constraint
        DB::statement('ALTER TABLE auth.company_user DROP CONSTRAINT IF EXISTS company_user_role_check');

        // Update existing 'member' roles to 'employee'
        DB::statement("UPDATE auth.company_user SET role = 'employee' WHERE role = 'member'");

        // Update existing 'accountant' roles to 'manager' (since accountant doesn't exist in new hierarchy)
        DB::statement("UPDATE auth.company_user SET role = 'manager' WHERE role = 'accountant'");

        // Add new check constraint with RBAC hierarchy roles
        DB::statement("ALTER TABLE auth.company_user ADD CONSTRAINT company_user_role_check CHECK (role IN ('owner', 'admin', 'manager', 'employee', 'viewer'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop existing check constraint
        DB::statement('ALTER TABLE auth.company_user DROP CONSTRAINT IF EXISTS company_user_role_check');

        // Revert 'employee' roles back to 'member'
        DB::statement("UPDATE auth.company_user SET role = 'member' WHERE role = 'employee'");

        // Revert 'manager' roles back to 'accountant'
        DB::statement("UPDATE auth.company_user SET role = 'accountant' WHERE role = 'manager'");

        // Restore old check constraint
        DB::statement("ALTER TABLE auth.company_user ADD CONSTRAINT company_user_role_check CHECK (role IN ('owner', 'admin', 'accountant', 'viewer', 'member'))");
    }
};
