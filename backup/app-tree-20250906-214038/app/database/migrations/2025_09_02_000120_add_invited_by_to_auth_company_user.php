<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('auth.company_user', function (Blueprint $t) {
            if (!Schema::hasColumn('auth.company_user', 'invited_by_user_id')) {
                $t->uuid('invited_by_user_id')->nullable()->index()->after('user_id');
            }
        });

        Schema::table('auth.company_user', function (Blueprint $t) {
            try {
                $t->foreign('invited_by_user_id')
                  ->references('id')->on('users')
                  ->nullOnDelete();
            } catch (\Throwable $e) {
                // ignore if FK already exists or not supported in this context
            }
        });
    }

    public function down(): void
    {
        Schema::table('auth.company_user', function (Blueprint $t) {
            try { $t->dropForeign(['invited_by_user_id']); } catch (\Throwable $e) { /* ignore */ }
            if (Schema::hasColumn('auth.company_user', 'invited_by_user_id')) {
                $t->dropColumn('invited_by_user_id');
            }
        });
    }
};

