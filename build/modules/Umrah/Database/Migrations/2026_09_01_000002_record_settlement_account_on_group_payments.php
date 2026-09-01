<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Say which account a payment settled through, for payments already posted.
 *
 * Choosing an account is optional, so almost every payment carries none and
 * a fallback picks one at posting time. The group screen could only report
 * "no account selected" about money that had plainly moved. Posting now
 * records what the fallback chose; this fills in the ones posted before it
 * did, by reading the cash or bank line off the payment's own journal entry.
 *
 * Only rows with no account are touched, so an account somebody chose is
 * never overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        // The recorded account is a statement of fact about a posted
        // payment, not a setting -- there is nothing to undo.
    }
};
