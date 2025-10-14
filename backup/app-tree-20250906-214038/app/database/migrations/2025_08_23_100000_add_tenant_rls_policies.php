<?php

// database/migrations/2025_08_23_100000_add_tenant_rls_policies.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Ensure the custom GUC exists by just setting it once at DB level (harmless if unused)
        DB::unprepared("
            -- RLS predicate: company_id must equal app.current_company_id
            create or replace function app.company_match(company uuid)
            returns boolean language sql stable as $$
                select company = current_setting('app.current_company_id', true)::uuid
            $$;
        ");

        // Example: enable RLS and add policy on a few core tables
        DB::unprepared("
            alter table if exists companies enable row level security;
            -- Companies visible only if user is a member; keep simple: by company_id match where applicable
            -- Skip policy for 'companies' if you plan a separate membership-based query.

            -- For any tenant-owned tables, attach policy like this:
            -- alter table accounts enable row level security;
            -- drop policy if exists tenant_isolation_accounts on accounts;
            -- create policy tenant_isolation_accounts on accounts
            --     using (app.company_match(company_id));
        ");
    }

    public function down(): void
    {
        DB::unprepared("drop function if exists app.company_match(uuid);");
        // You may want to drop policies manually if you created them.
    }
};
