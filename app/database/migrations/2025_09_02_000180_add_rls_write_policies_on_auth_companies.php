<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        try { DB::statement('alter table if exists auth.companies enable row level security'); } catch (\Throwable $e) { /* ignore */ }

        // Drop existing write policies to avoid duplicates
        foreach (['companies_membership_update','companies_membership_delete','companies_authenticated_insert'] as $p) {
            try { DB::statement("drop policy if exists $p on auth.companies"); } catch (\Throwable $e) { /* ignore */ }
        }

        // UPDATE allowed for owner/admin members or superadmin
        try {
            DB::unprepared(<<<'SQL'
                create policy companies_membership_update
                on auth.companies
                for update
                using (
                    exists (
                        select 1 from auth.company_user cu
                        where cu.company_id = auth.companies.id
                          and cu.user_id = current_setting('app.current_user_id', true)::uuid
                          and cu.role in ('owner','admin')
                    )
                    or exists (
                        select 1 from users u
                        where u.id = current_setting('app.current_user_id', true)::uuid
                          and u.system_role = 'superadmin'
                    )
                );
            SQL);
        } catch (\Throwable $e) { /* ignore */ }

        // DELETE allowed for owner members or superadmin (stricter than update)
        try {
            DB::unprepared(<<<'SQL'
                create policy companies_membership_delete
                on auth.companies
                for delete
                using (
                    exists (
                        select 1 from auth.company_user cu
                        where cu.company_id = auth.companies.id
                          and cu.user_id = current_setting('app.current_user_id', true)::uuid
                          and cu.role in ('owner')
                    )
                    or exists (
                        select 1 from users u
                        where u.id = current_setting('app.current_user_id', true)::uuid
                          and u.system_role = 'superadmin'
                    )
                );
            SQL);
        } catch (\Throwable $e) { /* ignore */ }

        // INSERT allowed for any authenticated user; app will attach membership in same tx
        try {
            DB::unprepared(<<<'SQL'
                create policy companies_authenticated_insert
                on auth.companies
                for insert
                with check (
                    current_setting('app.current_user_id', true) is not null
                );
            SQL);
        } catch (\Throwable $e) { /* ignore */ }
    }

    public function down(): void
    {
        foreach (['companies_membership_update','companies_membership_delete','companies_authenticated_insert'] as $p) {
            try { DB::statement("drop policy if exists $p on auth.companies"); } catch (\Throwable $e) { /* ignore */ }
        }
    }
};

