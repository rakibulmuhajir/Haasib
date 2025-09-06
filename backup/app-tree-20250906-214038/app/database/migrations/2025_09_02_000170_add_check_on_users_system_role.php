<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Ensure only allowed system roles (or null) are stored
        try { DB::statement('alter table users drop constraint if exists users_system_role_chk'); } catch (\Throwable $e) { /* ignore */ }
        try { DB::statement("alter table users add constraint users_system_role_chk check (system_role in ('superadmin') or system_role is null)"); } catch (\Throwable $e) { /* ignore */ }
    }

    public function down(): void
    {
        try { DB::statement('alter table users drop constraint if exists users_system_role_chk'); } catch (\Throwable $e) { /* ignore */ }
    }
};

