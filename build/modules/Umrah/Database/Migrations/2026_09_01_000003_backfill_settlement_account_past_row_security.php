<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The same backfill as 2026_09_01_000002, this time able to see the rows.
 *
 * umrah.group_payments has row security FORCED, which applies to the table's
 * owner as well -- and a migration runs with no company in context, so the
 * update matched nothing and reported success. It changed not one row on
 * production. Every data migration over these tables has to announce itself
 * the way the application does; the earlier one simply forgot.
 *
 * Left as a second migration rather than an edit to the first, which has
 * already run and would never run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SELECT set_config('app.is_super_admin', 'true', true)");

        DB::statement(<<<'SQL'
            UPDATE umrah.group_payments AS p
            SET account_id = settled.account_id
            FROM (
                SELECT DISTINCT ON (j.transaction_id) j.transaction_id, j.account_id
                FROM acct.journal_entries AS j
                JOIN acct.accounts AS a ON a.id = j.account_id
                WHERE a.subtype IN ('cash', 'bank')
                ORDER BY j.transaction_id, a.code
            ) AS settled
            WHERE settled.transaction_id = p.transaction_id
              AND p.account_id IS NULL
              AND p.transaction_id IS NOT NULL
        SQL);

        DB::statement("SELECT set_config('app.is_super_admin', 'false', true)");
    }

    public function down(): void
    {
        // The recorded account is a statement of fact about a posted
        // payment, not a setting -- there is nothing to undo.
    }
};
