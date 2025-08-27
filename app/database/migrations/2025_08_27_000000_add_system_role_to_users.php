<?php
// database/migrations/2025_08_27_000000_add_system_role_to_users.php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->string('system_role')->nullable()->index();
        });
        // Optional hardening: limit allowed values at DB level
        DB::statement("alter table users add constraint users_system_role_chk check (system_role is null or system_role in ('superadmin'))");
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignIdIfExists('system_role'); // safe no-op if none
            $t->dropColumn('system_role');
        });
    }
};
