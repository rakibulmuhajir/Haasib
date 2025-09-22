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
        Schema::table('accounts_receivable', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts_receivable', 'aging_category')) {
                $table->string('aging_category')->default('current')->after('days_overdue');
            }
            if (! Schema::hasColumn('accounts_receivable', 'last_calculated_at')) {
                $table->timestamp('last_calculated_at')->nullable()->after('aging_category');
            }
            if (! Schema::hasColumn('accounts_receivable', 'metadata')) {
                $table->json('metadata')->nullable()->after('last_calculated_at');
            }
            if (! Schema::hasColumn('accounts_receivable', 'deleted_at')) {
                $table->softDeletes()->after('metadata');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            if (Schema::hasColumn('accounts_receivable', 'aging_category')) {
                $table->dropColumn('aging_category');
            }
            if (Schema::hasColumn('accounts_receivable', 'last_calculated_at')) {
                $table->dropColumn('last_calculated_at');
            }
            if (Schema::hasColumn('accounts_receivable', 'metadata')) {
                $table->dropColumn('metadata');
            }
            if (Schema::hasColumn('accounts_receivable', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
