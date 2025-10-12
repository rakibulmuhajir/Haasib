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

        // Add back accountant role - insert some records for testing if needed
        // For existing managers, we can optionally promote some to accountants based on business logic

        // Add new check constraint with full RBAC hierarchy including accountant
        DB::statement("ALTER TABLE auth.company_user ADD CONSTRAINT company_user_role_check CHECK (role IN ('owner', 'admin', 'manager', 'accountant', 'employee', 'viewer'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop existing check constraint
        DB::statement('ALTER TABLE auth.company_user DROP CONSTRAINT IF EXISTS company_user_role_check');

        // Restore previous check constraint without accountant
        DB::statement("ALTER TABLE auth.company_user ADD CONSTRAINT company_user_role_check CHECK (role IN ('owner', 'admin', 'manager', 'employee', 'viewer'))");
    }
};
