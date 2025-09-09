<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        try { DB::statement('alter table if exists auth.companies enable row level security'); } catch (\Throwable $e) { /* ignore */ }

        // Allow invited users (by email) to SELECT companies so they can view invite context
        try { DB::statement('drop policy if exists companies_invited_email_select on auth.companies'); } catch (\Throwable $e) { /* ignore */ }

        try {
            DB::unprepared(<<<'SQL'
                create policy companies_invited_email_select
                on auth.companies
                for select
                using (
                    exists (
                        select 1 from auth.company_invitations i
                        where i.company_id = auth.companies.id
                          and i.status = 'pending'
                          and lower(i.invited_email) = lower(current_setting('app.current_user_email', true))
                    )
                );
            SQL);
        } catch (\Throwable $e) { /* ignore */ }
    }

    public function down(): void
    {
        try { DB::statement('drop policy if exists companies_invited_email_select on auth.companies'); } catch (\Throwable $e) { /* ignore */ }
    }
};

