<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            // Drop existing constraint if present to avoid duplicates, then add a clean one
            DB::statement('alter table auth.company_user drop constraint if exists auth_company_user_role_chk');
            DB::statement("alter table auth.company_user add constraint auth_company_user_role_chk check (role in ('owner','admin','accountant','viewer','member'))");
        } catch (\Throwable $e) {
            // ignore on non-PgSQL or if table missing in some envs
        }
    }

    public function down(): void
    {
        try {
            DB::statement('alter table auth.company_user drop constraint if exists auth_company_user_role_chk');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
