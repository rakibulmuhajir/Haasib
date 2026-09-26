<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * acct.transactions' (company_id, transaction_number) unique index was declared as
 * ->unique(['company_id','transaction_number'])->whereNull('deleted_at') in
 * 2025_11_29_000000_create_general_ledger_tables.php, but the resulting index in Postgres is
 * a plain (non-partial) unique index -- `\d acct.transactions` / pg_indexes shows no WHERE
 * clause at all. Laravel's fluent ->whereNull() on a unique() index is silently dropped by
 * this schema grammar; it was never a partial index in the database, only in the migration
 * source.
 *
 * This went unnoticed because nothing soft-deleted a transaction and then reused its number
 * -- until DailyCloseReopenService::reopen() (Edit day) started doing exactly that: a
 * reopened close is soft-deleted so every existing "is this date posted" check
 * (whereNull('deleted_at')) keeps working, and re-posting the same date must be able to
 * reuse FDC-YYYYMMDD. Recreates the index as a genuine partial unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The unique index backs a table CONSTRAINT, not a bare index -- Postgres refuses to
        // drop the index directly while the constraint depends on it.
        DB::statement('ALTER TABLE acct.transactions DROP CONSTRAINT IF EXISTS acct_transactions_company_id_transaction_number_unique');
        DB::statement('CREATE UNIQUE INDEX acct_transactions_company_id_transaction_number_unique
            ON acct.transactions (company_id, transaction_number) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS acct.acct_transactions_company_id_transaction_number_unique');
        DB::statement('ALTER TABLE acct.transactions ADD CONSTRAINT acct_transactions_company_id_transaction_number_unique
            UNIQUE (company_id, transaction_number)');
    }
};
