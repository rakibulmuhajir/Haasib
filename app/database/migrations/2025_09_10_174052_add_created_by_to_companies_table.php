<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('auth.companies', function (Blueprint $table) {
            if (!Schema::hasColumn('auth.companies', 'created_by_user_id')) {
                $table->uuid('created_by_user_id')->nullable()->index()->after('id');
            }
        });

        // Add the foreign key in a separate call to avoid issues on some drivers
        Schema::table('auth.companies', function (Blueprint $table) {
            // Guard against duplicate FK creation across re-runs
            try {
                $table->foreign('created_by_user_id')
                  ->references('id')->on('users')
                  ->nullOnDelete();
            } catch (\Throwable $e) {
                // ignore if already exists or driver doesn't support within this context
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth.companies', function (Blueprint $table) {
            try { $table->dropForeign(['created_by_user_id']); } catch (\Throwable $e) { /* ignore */ }
            if (Schema::hasColumn('auth.companies', 'created_by_user_id')) {
                $table->dropColumn('created_by_user_id');
            }
        });
    }
};
