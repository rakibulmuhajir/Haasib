<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2b of docs/contracts/refunds.md -- settlement.
 *
 * Phase 1 only ever reached `paid` by direct manipulation in tests; no route
 * posted to it. This migration retires that placeholder state and replaces
 * it with the two real outcomes a settlement can have: the company paid the
 * money back (`refunded`), or the company kept it as credit the party can
 * still spend (`credited`). Existing `paid` rows are backfilled to
 * `refunded` -- Phase 1 never created any through the app, so in practice
 * this backfill only protects a hand-seeded or test-manipulated row from
 * being orphaned by the constraint swap, but it is not skipped on that
 * account: an UPDATE that matches zero rows is still correct, and this file
 * runs against every environment that has ever run this migration set, not
 * just the one this feature was built against.
 *
 * `settled_payment_id` and `transaction_id` already exist on this table
 * (Phase 1) and are reused as-is: `transaction_id` stays the accept-time GL
 * transaction, and `settled_payment_id` is intentionally left unpopulated by
 * settlement -- see RefundService::settle() for why a GroupPayment row
 * cannot represent a refund's cash movement under the existing
 * group_payments_payee_check constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE umrah.refunds SET status = 'refunded' WHERE status = 'paid'");

        DB::statement('ALTER TABLE umrah.refunds DROP CONSTRAINT umrah_refunds_status_check');
        DB::statement("ALTER TABLE umrah.refunds ADD CONSTRAINT umrah_refunds_status_check CHECK (status IN ('requested', 'accepted', 'refunded', 'credited', 'rejected', 'cancelled'))");

        Schema::table('umrah.refunds', function (Blueprint $table) {
            $table->string('settlement_method', 10)->nullable()->after('cancellation_reason');
            $table->timestamp('settled_at')->nullable()->after('settlement_method');
            $table->uuid('settled_by_user_id')->nullable()->after('settled_at');
            $table->uuid('cancellation_transaction_id')->nullable()->after('settled_by_user_id');

            $table->foreign('settled_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('cancellation_transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
        });

        DB::statement("ALTER TABLE umrah.refunds ADD CONSTRAINT umrah_refunds_settlement_method_check CHECK (settlement_method IS NULL OR settlement_method IN ('cash', 'credit'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE umrah.refunds DROP CONSTRAINT IF EXISTS umrah_refunds_settlement_method_check');

        Schema::table('umrah.refunds', function (Blueprint $table) {
            $table->dropForeign(['settled_by_user_id']);
            $table->dropForeign(['cancellation_transaction_id']);
            $table->dropColumn(['settlement_method', 'settled_at', 'settled_by_user_id', 'cancellation_transaction_id']);
        });

        DB::statement("UPDATE umrah.refunds SET status = 'paid' WHERE status IN ('refunded', 'credited')");

        DB::statement('ALTER TABLE umrah.refunds DROP CONSTRAINT umrah_refunds_status_check');
        DB::statement("ALTER TABLE umrah.refunds ADD CONSTRAINT umrah_refunds_status_check CHECK (status IN ('requested', 'accepted', 'paid', 'rejected', 'cancelled'))");
    }
};
