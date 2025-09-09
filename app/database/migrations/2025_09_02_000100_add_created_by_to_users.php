<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'created_by_user_id')) {
                $t->uuid('created_by_user_id')->nullable()->index()->after('id');
            }
        });

        // Add the foreign key in a separate call to avoid issues on some drivers
        Schema::table('users', function (Blueprint $t) {
            // Guard against duplicate FK creation across re-runs
            try {
                $t->foreign('created_by_user_id')
                  ->references('id')->on('users')
                  ->nullOnDelete();
            } catch (\Throwable $e) {
                // ignore if already exists or driver doesn't support within this context
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            try { $t->dropForeign(['created_by_user_id']); } catch (\Throwable $e) { /* ignore */ }
            if (Schema::hasColumn('users', 'created_by_user_id')) {
                $t->dropColumn('created_by_user_id');
            }
        });
    }
};

