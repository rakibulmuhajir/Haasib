<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Let one payment be put against the same group more than once.
 *
 * The pair was unique from the schema's first draft, when a group's charge
 * was settled when it was built and one allocation could always cover it.
 * A charge can now be adjusted afterwards, and then an agent with credit
 * sitting with us had no way to apply it: the group they owed more on was
 * the one group their payment was barred from, in the picker and in the
 * database both.
 *
 * Nothing here was protecting the money. What can be allocated is bounded
 * by the credit left on the payment and by what the group still owes, both
 * checked under a lock in allocatePayment(). The index becomes a plain one,
 * since it is also how allocations are looked up.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS umrah.payment_allocations_active_pair_unique');
        DB::statement('CREATE INDEX IF NOT EXISTS payment_allocations_payment_group_index ON umrah.payment_allocations (group_payment_id, visa_group_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS umrah.payment_allocations_payment_group_index');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS payment_allocations_active_pair_unique ON umrah.payment_allocations (group_payment_id, visa_group_id) WHERE reversed_at IS NULL');
    }
};
