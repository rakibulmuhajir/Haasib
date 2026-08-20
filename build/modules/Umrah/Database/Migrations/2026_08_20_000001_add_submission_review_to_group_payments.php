<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE umrah.group_payments DROP CONSTRAINT IF EXISTS group_payments_status_check');
        DB::statement("ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_status_check CHECK (status IN ('posted', 'reversed', 'submitted', 'rejected'))");

        DB::statement('ALTER TABLE umrah.group_payments ALTER COLUMN base_amount DROP NOT NULL');

        DB::statement('ALTER TABLE umrah.group_payments DROP CONSTRAINT IF EXISTS group_payments_exchange_rate_check');
        DB::statement("ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_exchange_rate_check CHECK (
            (status IN ('submitted', 'rejected') AND exchange_rate IS NULL AND base_amount IS NULL)
            OR (currency = base_currency AND exchange_rate IS NULL AND base_amount = round(amount, 2))
            OR (currency <> base_currency AND exchange_rate > 0 AND base_amount = round(amount * exchange_rate, 2))
        )");

        DB::statement('ALTER TABLE umrah.group_payments ADD COLUMN IF NOT EXISTS submitted_by_user_id uuid NULL');
        DB::statement('ALTER TABLE umrah.group_payments ADD COLUMN IF NOT EXISTS submitted_at timestamp NULL');
        DB::statement('ALTER TABLE umrah.group_payments ADD COLUMN IF NOT EXISTS reviewed_by_user_id uuid NULL');
        DB::statement('ALTER TABLE umrah.group_payments ADD COLUMN IF NOT EXISTS reviewed_at timestamp NULL');
        DB::statement('ALTER TABLE umrah.group_payments ADD COLUMN IF NOT EXISTS review_remarks text NULL');
    }

    /**
     * Refuses rather than rewrites.
     *
     * A submitted or rejected payment is a live business record -- an agent's
     * claim to have collected money, and (for a rejection) an accountant's
     * reasoned decision not to book it. Rolling this migration back would
     * have to either delete those rows or force them into a status the old
     * constraint accepts, silently destroying the submission/review trail.
     * A schema rollback is not a licence to edit the books; if real payments
     * are sitting in 'submitted' or 'rejected', a human decides what happens
     * to them first.
     */
    public function down(): void
    {
        $pending = DB::table('umrah.group_payments')->whereIn('status', ['submitted', 'rejected'])->count();

        if ($pending > 0) {
            throw new \RuntimeException(
                "Cannot roll back: {$pending} group payment(s) are 'submitted' or 'rejected'. "
                .'Resolve them (approve, reject, or otherwise reassign) deliberately before removing this workflow.'
            );
        }

        DB::statement('ALTER TABLE umrah.group_payments DROP COLUMN IF EXISTS review_remarks');
        DB::statement('ALTER TABLE umrah.group_payments DROP COLUMN IF EXISTS reviewed_at');
        DB::statement('ALTER TABLE umrah.group_payments DROP COLUMN IF EXISTS reviewed_by_user_id');
        DB::statement('ALTER TABLE umrah.group_payments DROP COLUMN IF EXISTS submitted_at');
        DB::statement('ALTER TABLE umrah.group_payments DROP COLUMN IF EXISTS submitted_by_user_id');

        DB::statement('ALTER TABLE umrah.group_payments DROP CONSTRAINT IF EXISTS group_payments_exchange_rate_check');
        DB::statement("ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_exchange_rate_check CHECK (
            (currency = base_currency AND exchange_rate IS NULL AND base_amount = round(amount, 2))
            OR (currency <> base_currency AND exchange_rate > 0 AND base_amount = round(amount * exchange_rate, 2))
        )");

        DB::statement('ALTER TABLE umrah.group_payments ALTER COLUMN base_amount SET NOT NULL');

        DB::statement('ALTER TABLE umrah.group_payments DROP CONSTRAINT IF EXISTS group_payments_status_check');
        DB::statement("ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_status_check CHECK (status IN ('posted', 'reversed'))");
    }
};
