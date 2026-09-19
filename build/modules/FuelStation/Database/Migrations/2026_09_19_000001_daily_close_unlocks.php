<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reopening a locked Daily Close left no trace. Transaction::unlock() nulled locked_at,
 * locked_by_user_id and lock_reason, so afterwards the row was indistinguishable from a
 * close that had never been locked, and fuel.capture_post_close_activity deliberately
 * returns early for fuel_daily_close rows, so the post-close audit did not catch it either.
 *
 * A history table rather than columns on acct.transactions: a day can be locked and
 * reopened repeatedly, and the auditor's question is the sequence, not the last event.
 * Append-only for the same reason fuel.daily_close_activity is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel.daily_close_unlocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->references('id')->on('auth.companies');
            $table->foreignUuid('close_transaction_id')->references('id')->on('acct.transactions');
            $table->timestamp('unlocked_at');
            $table->uuid('unlocked_by_user_id')->nullable();
            $table->text('reason');
            // The lock this reopening replaced, captured before Transaction::unlock() clears it.
            $table->timestamp('previously_locked_at')->nullable();
            $table->uuid('previously_locked_by_user_id')->nullable();
            $table->string('previous_lock_reason', 50)->nullable();
            $table->index(['company_id', 'close_transaction_id', 'unlocked_at'], 'daily_close_unlocks_lookup');

            $table->foreign('unlocked_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('previously_locked_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE fuel.daily_close_unlocks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fuel.daily_close_unlocks FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY daily_close_unlocks_company ON fuel.daily_close_unlocks USING (
            company_id = nullif(current_setting('app.current_company_id', true), '')::uuid
            OR current_setting('app.is_super_admin', true) = 'true')");

        DB::unprepared(<<<'SQL'
CREATE FUNCTION fuel.prevent_unlock_trail_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Daily Close unlock history is append-only'; END $$;
CREATE TRIGGER prevent_unlock_trail_mutation BEFORE UPDATE OR DELETE ON fuel.daily_close_unlocks
FOR EACH ROW EXECUTE FUNCTION fuel.prevent_unlock_trail_mutation();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS prevent_unlock_trail_mutation ON fuel.daily_close_unlocks');
        DB::statement('DROP FUNCTION IF EXISTS fuel.prevent_unlock_trail_mutation()');
        Schema::dropIfExists('fuel.daily_close_unlocks');
    }
};
