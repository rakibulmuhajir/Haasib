<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bring fuel.amanat_transactions in line with docs/contracts/fuel-schema.md,
 * which already documents journal_entry_id — the original migration never
 * created it. Links an amanat row to the GL line (acct.journal_entries) that
 * posted it, e.g. for opening balances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel.amanat_transactions', function (Blueprint $table) {
            $table->uuid('journal_entry_id')->nullable()->after('reference');

            $table->foreign('journal_entry_id')
                ->references('id')->on('acct.journal_entries')
                ->nullOnDelete()->cascadeOnUpdate();

            $table->index('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('fuel.amanat_transactions', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropIndex(['journal_entry_id']);
            $table->dropColumn('journal_entry_id');
        });
    }
};
