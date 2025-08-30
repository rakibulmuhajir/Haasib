<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('idempotency_keys')) {
            Schema::create('idempotency_keys', function (Blueprint $t) {
                $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                $t->uuid('user_id')->index();
                $t->uuid('company_id')->nullable()->index();
                $t->string('action', 100)->index();
                $t->string('key', 128)->index();
                $t->jsonb('request')->nullable();
                $t->jsonb('response')->nullable();
                $t->timestamps();
            });
            try {
                DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS idemp_user_co_action_key_unique ON idempotency_keys (user_id, company_id, action, key)");
            } catch (\Throwable $e) { /* ignore on unsupported drivers */ }
        } else {
            Schema::table('idempotency_keys', function (Blueprint $t) {
                if (!Schema::hasColumn('idempotency_keys', 'request'))   { $t->jsonb('request')->nullable(); }
                if (!Schema::hasColumn('idempotency_keys', 'response'))  { $t->jsonb('response')->nullable(); }
            });
            try {
                DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS idemp_user_co_action_key_unique ON idempotency_keys (user_id, company_id, action, key)");
            } catch (\Throwable $e) { /* ignore on unsupported drivers */ }
        }
    }

    public function down(): void {
        if (Schema::hasTable('idempotency_keys')) {
            Schema::drop('idempotency_keys');
        }
    }
};

