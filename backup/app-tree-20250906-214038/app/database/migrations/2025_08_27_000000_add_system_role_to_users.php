<?php
// database/migrations/2025_08_27_000000_add_system_role_to_users.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'system_role')) {
                $t->string('system_role')->nullable()->index();
            }
        });
        // Note: skip adding a DB-level CHECK constraint here to keep this idempotent
        // across environments without relying on introspection of constraint existence.
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'system_role')) {
                // Drop index if it exists (Laravel handles by column name array safely across drivers)
                try { $t->dropIndex(['system_role']); } catch (\Throwable $e) { /* ignore */ }
                $t->dropColumn('system_role');
            }
        });
        // If a constraint was added manually in some envs, try to drop it safely
        try { DB::statement("alter table users drop constraint if exists users_system_role_chk"); } catch (\Throwable $e) { /* ignore */ }
    }
};
