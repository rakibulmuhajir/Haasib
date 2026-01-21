<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add revaluation tracking fields to fuel.rate_changes.
 * When OGRA rates change, existing inventory is revalued and a GL entry is posted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel.rate_changes', function (Blueprint $table) {
            // Revaluation amount = (new_purchase_rate - old_avg_cost) * stock_quantity
            $table->decimal('revaluation_amount', 15, 2)->nullable()->after('margin_impact');

            // Previous avg_cost before revaluation (for audit trail)
            $table->decimal('previous_avg_cost', 10, 4)->nullable()->after('revaluation_amount');

            // GL transaction reference for the revaluation entry
            $table->uuid('journal_entry_id')->nullable()->after('previous_avg_cost');

            // Foreign key to transactions table
            $table->foreign('journal_entry_id')
                ->references('id')->on('acct.transactions')
                ->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('fuel.rate_changes', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn(['revaluation_amount', 'previous_avg_cost', 'journal_entry_id']);
        });
    }
};
