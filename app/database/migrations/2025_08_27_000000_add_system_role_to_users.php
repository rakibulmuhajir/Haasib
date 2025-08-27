<?php
// database/migrations/2025_08_27_000000_add_system_role_to_users.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->string('system_role')->nullable()->index();
        });
        // Hard guard so random strings don’t sneak in
        DB::statement("alter table users add constraint users_system_role_chk check (system_role is null or system_role in ('superadmin'))");
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['system_role']);
            $t->dropColumn('system_role');
        });
        DB::statement("alter table users drop constraint if exists users_system_role_chk");
    }
};
