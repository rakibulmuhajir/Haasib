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
            $table->string('aging_category')->default('current')->after('days_overdue');
            $table->timestamp('last_calculated_at')->nullable()->after('aging_category');
            $table->json('metadata')->nullable()->after('last_calculated_at');
            $table->softDeletes()->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->dropColumn(['aging_category', 'last_calculated_at', 'metadata']);
            $table->dropSoftDeletes();
        });
    }
};
