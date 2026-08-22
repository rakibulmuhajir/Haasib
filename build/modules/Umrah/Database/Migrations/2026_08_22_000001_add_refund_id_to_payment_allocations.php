<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2b of docs/contracts/refunds.md caught up settle()'s side of the
 * ledger (a credit settlement creates a real GroupPayment) but left
 * approve() with none. approve() posts Dr agent_advances/Cr refunds_payable
 * the moment it runs, before settlement is even chosen -- that draw has to
 * land on the advance row itself right then, or allocatePayment() and every
 * report that reads GroupPayment/PaymentAllocation directly (agentStatement
 * ()'s "Available advances", advances(), RefundService's own ceiling) goes
 * on treating money the ledger already moved into refunds_payable as if it
 * were still sitting there unspent -- letting the same advance be allocated
 * to a group AND handed back as credit.
 *
 * A refund's draw is spent exactly the way an allocation is spent: it takes
 * a bite out of one payment's available base_amount and nothing else needs
 * to change to make every existing reader agree, because none of them
 * filter payment_allocations by visa_group_id -- they all just sum
 * "allocations" for the payment. So this reuses the table rather than
 * adding a parallel one: visa_group_id becomes nullable, refund_id is
 * added, and a payment_allocations row now names exactly one of the two.
 * Settling the refund as credit still creates a fresh GroupPayment
 * (unchanged); settling as cash or cancelling reverses this row the same
 * way reverseAllocation() already reverses any other -- no GL entry of its
 * own to unwind, since approve()'s accept entry already covers it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE umrah.payment_allocations ALTER COLUMN visa_group_id DROP NOT NULL');
        DB::statement('ALTER TABLE umrah.payment_allocations ADD COLUMN refund_id uuid NULL');
        DB::statement('ALTER TABLE umrah.payment_allocations ADD CONSTRAINT payment_allocations_refund_id_foreign FOREIGN KEY (refund_id) REFERENCES umrah.refunds (id) ON DELETE CASCADE ON UPDATE CASCADE');
        DB::statement('ALTER TABLE umrah.payment_allocations ADD CONSTRAINT payment_allocations_target_check CHECK ((visa_group_id IS NOT NULL AND refund_id IS NULL) OR (visa_group_id IS NULL AND refund_id IS NOT NULL))');
        DB::statement('CREATE INDEX payment_allocations_company_refund_index ON umrah.payment_allocations (company_id, refund_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS umrah.payment_allocations_company_refund_index');
        DB::statement('ALTER TABLE umrah.payment_allocations DROP CONSTRAINT IF EXISTS payment_allocations_target_check');
        DB::statement('DELETE FROM umrah.payment_allocations WHERE refund_id IS NOT NULL');
        DB::statement('ALTER TABLE umrah.payment_allocations DROP CONSTRAINT IF EXISTS payment_allocations_refund_id_foreign');
        DB::statement('ALTER TABLE umrah.payment_allocations DROP COLUMN IF EXISTS refund_id');
        DB::statement('ALTER TABLE umrah.payment_allocations ALTER COLUMN visa_group_id SET NOT NULL');
    }
};
