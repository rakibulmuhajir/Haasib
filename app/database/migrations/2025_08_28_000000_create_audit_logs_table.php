<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS audit');
        Schema::dropIfExists('audit.audit_logs');
        Schema::create('audit.audit_logs', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $t->uuid('user_id');
            $t->uuid('company_id')->nullable();
            $t->string('action');
            $t->jsonb('params')->nullable();
            $t->string('idempotency_key')->unique();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit.audit_logs');
    }
};
