<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2b of docs/contracts/refunds.md -- group refund de-allocation.
 *
 * A group refund can reverse more of a payment's allocation than the refund
 * itself needs, in which case the leftover is re-allocated back onto the
 * same group (step 4 of the de-allocation sequence). reverseAllocation()
 * does not delete the reversed row, it stamps reversed_at on it -- so a
 * second live allocation for the same (group_payment_id, visa_group_id)
 * pair collides with the plain unique constraint added in
 * 2026_07_13_000004_make_payments_independent_and_add_allocations.php,
 * which predates reversal existing at all (reversed_at was only added
 * later, in 2026_07_16_000005_add_umrah_correction_workflows.php, without
 * updating this constraint).
 *
 * This was not something the task asked for directly -- it surfaced when a
 * test for that exact re-allocation step failed with a unique-violation
 * against production's actual schema, not a test-only fixture problem.
 * Fixed the same way voucher_passengers_active_assignment_unique already
 * solves the identical shape of problem: swap the table-wide unique
 * constraint for a partial unique index that only counts live rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Laravel's Schema::create('umrah.payment_allocations', ...) name-mangles
        // the schema-qualified table into the constraint name, so the real name
        // carries the umrah_ prefix, not a bare payment_allocations_ prefix.
        DB::statement('ALTER TABLE umrah.payment_allocations DROP CONSTRAINT IF EXISTS umrah_payment_allocations_group_payment_id_visa_group_id_unique');
        DB::statement('CREATE UNIQUE INDEX payment_allocations_active_pair_unique ON umrah.payment_allocations (group_payment_id, visa_group_id) WHERE reversed_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS umrah.payment_allocations_active_pair_unique');
        DB::statement('ALTER TABLE umrah.payment_allocations ADD CONSTRAINT umrah_payment_allocations_group_payment_id_visa_group_id_unique UNIQUE (group_payment_id, visa_group_id)');
    }
};
