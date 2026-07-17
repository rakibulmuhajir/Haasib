<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE auth.company_user DROP CONSTRAINT IF EXISTS company_user_role_check');
        DB::statement("ALTER TABLE auth.company_user ADD CONSTRAINT company_user_role_check CHECK (role IN ('owner', 'admin', 'accountant', 'viewer', 'member', 'agent'))");
        DB::statement(<<<'SQL'
            UPDATE auth.company_user AS membership
            SET role = 'agent', updated_at = CURRENT_TIMESTAMP
            FROM umrah.agents AS agent
            WHERE agent.company_id = membership.company_id
              AND agent.user_id = membership.user_id
              AND agent.user_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement("UPDATE auth.company_user SET role = 'member', updated_at = CURRENT_TIMESTAMP WHERE role = 'agent'");
        DB::statement('ALTER TABLE auth.company_user DROP CONSTRAINT IF EXISTS company_user_role_check');
        DB::statement("ALTER TABLE auth.company_user ADD CONSTRAINT company_user_role_check CHECK (role IN ('owner', 'admin', 'accountant', 'viewer', 'member'))");
    }
};
