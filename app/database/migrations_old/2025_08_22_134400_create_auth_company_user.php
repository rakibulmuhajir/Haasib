<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $exists = false;
        try {
            $row = DB::selectOne("select to_regclass('auth.company_user') as regclass");
            $exists = ! empty($row?->regclass);
        } catch (\Throwable $e) {
            $exists = false;
        }

        if (! $exists) {
            Schema::create('auth.company_user', function (Blueprint $t) {
                $t->uuid('company_id');
                $t->uuid('user_id');
                $t->uuid('invited_by_user_id')->nullable();
                $t->string('role')->default('member');
                $t->timestamps();

                $t->primary(['company_id', 'user_id']);
                $t->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
                $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $t->foreign('invited_by_user_id')->references('id')->on('users')->onDelete('set null');

                $t->index(['company_id', 'user_id']);
                $t->index('invited_by_user_id');
            });

            // Add check constraint for role values
            DB::statement("alter table auth.company_user add constraint auth_company_user_role_chk check (role in ('owner','admin','accountant','viewer','member'))");
        }
    }

    public function down(): void
    {
        try {
            DB::statement('DROP TABLE IF EXISTS auth.company_user');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
