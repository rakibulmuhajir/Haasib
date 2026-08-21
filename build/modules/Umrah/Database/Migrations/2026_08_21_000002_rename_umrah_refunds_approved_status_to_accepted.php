<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The product owner named the states in the language the business uses: a
 * refund is requested, then the company accepts it, then it is refunded.
 * "Approved" implied authorising work, the way a voucher is approved --
 * accepting a refund is agreeing to owe something back. Only the stored
 * *state* changes here; the action that gets a refund there stays
 * `approve()` / `umrah.refund.approve`, exactly as docs/contracts/refunds.md
 * and the task that shipped this migration require.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE umrah.refunds SET status = \'accepted\' WHERE status = \'approved\'');

        DB::statement('ALTER TABLE umrah.refunds DROP CONSTRAINT umrah_refunds_status_check');
        DB::statement("ALTER TABLE umrah.refunds ADD CONSTRAINT umrah_refunds_status_check CHECK (status IN ('requested', 'accepted', 'paid', 'rejected', 'cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE umrah.refunds DROP CONSTRAINT umrah_refunds_status_check');
        DB::statement("ALTER TABLE umrah.refunds ADD CONSTRAINT umrah_refunds_status_check CHECK (status IN ('requested', 'approved', 'paid', 'rejected', 'cancelled'))");

        DB::statement('UPDATE umrah.refunds SET status = \'approved\' WHERE status = \'accepted\'');
    }
};
