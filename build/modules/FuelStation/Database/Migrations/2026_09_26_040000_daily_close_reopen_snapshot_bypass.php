<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A third trigger stood between "Edit day" and a posted close: fuel.protect_close_snapshot()
 * (2026_09_15_200000_daily_close_drafts.php) refuses to soft-delete or otherwise touch
 * acct.transactions once it holds a posting_snapshot -- discovered only once
 * DailyCloseReopenService actually tried to remove one in a test. Same narrow bypass as the
 * other two (see 2026_09_26_030000_daily_close_reopen_support.php): scoped to the one close
 * id in `app.reopening_close_id` for the one transaction reopening it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION fuel.protect_close_snapshot() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE reopening_id text;
BEGIN
    reopening_id := nullif(current_setting('app.reopening_close_id', true), '');
    IF reopening_id IS NOT NULL AND reopening_id = OLD.id::text THEN
        IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
        RETURN NEW;
    END IF;
    IF (OLD.transaction_type = 'fuel_daily_close' AND OLD.metadata ? 'posting_snapshot') OR OLD.metadata ? 'reading_correction' THEN
        IF TG_OP = 'DELETE' THEN
            RAISE EXCEPTION 'A posted Daily Close cannot be deleted';
        END IF;
        IF NEW.status NOT IN ('posted','locked')
           OR NEW.reversal_of_id IS DISTINCT FROM OLD.reversal_of_id
           OR NEW.reversed_by_id IS DISTINCT FROM OLD.reversed_by_id
           OR NEW.currency IS DISTINCT FROM OLD.currency
           OR NEW.base_currency IS DISTINCT FROM OLD.base_currency
           OR NEW.exchange_rate IS DISTINCT FROM OLD.exchange_rate
           OR NEW.posting_date IS DISTINCT FROM OLD.posting_date
           OR NEW.transaction_number IS DISTINCT FROM OLD.transaction_number
           OR NEW.transaction_type IS DISTINCT FROM OLD.transaction_type
           OR NEW.total_debit IS DISTINCT FROM OLD.total_debit
           OR NEW.total_credit IS DISTINCT FROM OLD.total_credit
           OR NEW.created_at IS DISTINCT FROM OLD.created_at
           OR NEW.created_by_user_id IS DISTINCT FROM OLD.created_by_user_id
           OR NEW.metadata IS DISTINCT FROM OLD.metadata
           OR NEW.transaction_date IS DISTINCT FROM OLD.transaction_date
           OR NEW.company_id IS DISTINCT FROM OLD.company_id
           OR NEW.posted_at IS DISTINCT FROM OLD.posted_at
           OR NEW.posted_by_user_id IS DISTINCT FROM OLD.posted_by_user_id
           OR NEW.deleted_at IS DISTINCT FROM OLD.deleted_at THEN
            RAISE EXCEPTION 'The posted Daily Close snapshot is immutable';
        END IF;
    END IF;
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END $$;
SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION fuel.protect_close_snapshot() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF (OLD.transaction_type = 'fuel_daily_close' AND OLD.metadata ? 'posting_snapshot') OR OLD.metadata ? 'reading_correction' THEN
        IF TG_OP = 'DELETE' THEN
            RAISE EXCEPTION 'A posted Daily Close cannot be deleted';
        END IF;
        IF NEW.status NOT IN ('posted','locked')
           OR NEW.reversal_of_id IS DISTINCT FROM OLD.reversal_of_id
           OR NEW.reversed_by_id IS DISTINCT FROM OLD.reversed_by_id
           OR NEW.currency IS DISTINCT FROM OLD.currency
           OR NEW.base_currency IS DISTINCT FROM OLD.base_currency
           OR NEW.exchange_rate IS DISTINCT FROM OLD.exchange_rate
           OR NEW.posting_date IS DISTINCT FROM OLD.posting_date
           OR NEW.transaction_number IS DISTINCT FROM OLD.transaction_number
           OR NEW.transaction_type IS DISTINCT FROM OLD.transaction_type
           OR NEW.total_debit IS DISTINCT FROM OLD.total_debit
           OR NEW.total_credit IS DISTINCT FROM OLD.total_credit
           OR NEW.created_at IS DISTINCT FROM OLD.created_at
           OR NEW.created_by_user_id IS DISTINCT FROM OLD.created_by_user_id
           OR NEW.metadata IS DISTINCT FROM OLD.metadata
           OR NEW.transaction_date IS DISTINCT FROM OLD.transaction_date
           OR NEW.company_id IS DISTINCT FROM OLD.company_id
           OR NEW.posted_at IS DISTINCT FROM OLD.posted_at
           OR NEW.posted_by_user_id IS DISTINCT FROM OLD.posted_by_user_id
           OR NEW.deleted_at IS DISTINCT FROM OLD.deleted_at THEN
            RAISE EXCEPTION 'The posted Daily Close snapshot is immutable';
        END IF;
    END IF;
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END $$;
SQL);
    }
};
