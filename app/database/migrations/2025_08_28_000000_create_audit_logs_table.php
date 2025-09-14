<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS audit');

        if (! Schema::hasTable('audit.audit_logs')) {
            Schema::create('audit.audit_logs', function (Blueprint $t) {
                $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                $t->uuid('user_id');
                $t->uuid('company_id')->nullable();
                $t->string('action', 100)->index();
                $t->jsonb('params')->nullable();
                $t->text('raw')->nullable();
                $t->jsonb('result')->nullable();
                $t->string('idempotency_key')->nullable();
                $t->timestamps();
            });
            // Ensure idempotency key uniqueness without breaking re-runs
            try {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS audit_logs_idempotency_key_unique ON audit.audit_logs (idempotency_key)');
            } catch (\Throwable $e) { /* ignore if driver unsupported */
            }
        } else {
            Schema::table('audit.audit_logs', function (Blueprint $t) {
                if (! Schema::hasColumn('audit.audit_logs', 'raw')) {
                    $t->text('raw')->nullable();
                }
                if (! Schema::hasColumn('audit.audit_logs', 'result')) {
                    $t->jsonb('result')->nullable();
                }
                if (! Schema::hasColumn('audit.audit_logs', 'idempotency_key')) {
                    $t->string('idempotency_key')->nullable();
                }
            });
            try {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS audit_logs_idempotency_key_unique ON audit.audit_logs (idempotency_key)');
            } catch (\Throwable $e) { /* ignore if driver unsupported */
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit.audit_logs');
    }
};
