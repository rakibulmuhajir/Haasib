<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auth.company_invitations', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $t->uuid('company_id');
            $t->string('invited_email');
            $t->string('role')->default('member');
            $t->uuid('invited_by_user_id')->nullable();
            $t->string('token', 64)->unique();
            $t->string('status')->default('pending'); // pending, accepted, revoked, expired
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->uuid('accepted_by_user_id')->nullable();
            $t->timestamps();

            $t->index(['company_id']);
            $t->index(['invited_email']);

            $t->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $t->foreign('invited_by_user_id')->references('id')->on('users')->nullOnDelete();
            $t->foreign('accepted_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        // Constrain role and status values (Postgres); ignore errors on other drivers
        try {
            DB::statement("alter table auth.company_invitations add constraint company_invitations_role_chk check (role in ('owner','admin','accountant','viewer','member'))");
        } catch (\Throwable $e) { /* ignore */ }
        try {
            DB::statement("alter table auth.company_invitations add constraint company_invitations_status_chk check (status in ('pending','accepted','revoked','expired'))");
        } catch (\Throwable $e) { /* ignore */ }

        // Prevent duplicate active invites per email per company (Postgres partial unique index)
        try {
            DB::statement("create unique index if not exists company_invitations_unique_active on auth.company_invitations (company_id, invited_email) where status = 'pending'");
        } catch (\Throwable $e) { /* ignore */ }
    }

    public function down(): void
    {
        Schema::dropIfExists('auth.company_invitations');
    }
};

