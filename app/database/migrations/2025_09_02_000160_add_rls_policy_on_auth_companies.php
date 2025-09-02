<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Ensure RLS is enabled first (safe if already enabled)
        try { DB::statement('alter table if exists auth.companies enable row level security'); } catch (\Throwable $e) { /* ignore */ }

        // Drop existing policies if re-running
        try { DB::statement('drop policy if exists companies_membership_select on auth.companies'); } catch (\Throwable $e) { /* ignore */ }

        // Policy: allow SELECT if requester is a member of the company, or is a superadmin.
        // Uses GUC app.current_user_id that the app should set per request.
        try {
            DB::unprepared(<<<'SQL'
                create policy companies_membership_select
                on auth.companies
                for select
                using (
                    exists (
                        select 1 from auth.company_user cu
                        where cu.company_id = auth.companies.id
                          and cu.user_id = current_setting('app.current_user_id', true)::uuid
                    )
                    or exists (
                        select 1 from users u
                        where u.id = current_setting('app.current_user_id', true)::uuid
                          and u.system_role = 'superadmin'
                    )
                );
            SQL);
        } catch (\Throwable $e) { /* ignore non-PgSQL */ }
    }

    public function down(): void
    {
        try { DB::statement('drop policy if exists companies_membership_select on auth.companies'); } catch (\Throwable $e) { /* ignore */ }
    }
};

