<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every Daily Close is now a snapshot close: the legacy reversal+correction amendment
 * path (for a close posted without a posting_snapshot) has been removed from the
 * application (see Transaction::isAmendable(), DailyCloseAmendmentService's removal,
 * and docs/contracts/fuel-schema.md). The original audit trigger migration
 * (2026_09_15_220000_audit_post_close_activity) whitelisted 'fuel_daily_close_reversal'
 * transactions alongside 'fuel_daily_close' so the audit trigger would skip auditing
 * them like the close itself; that migration is left untouched (it has already run on
 * production), and this migration re-creates fuel.capture_post_close_activity() with
 * that whitelist entry dropped, so production converges to the current behaviour.
 * Nothing in the application creates a 'fuel_daily_close_reversal' transaction anymore,
 * so this is a no-op in practice — it only removes now-dead permission for a type that
 * can no longer be produced.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION fuel.capture_post_close_activity() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    before_row jsonb; after_row jsonb; source_row jsonb; tenant uuid;
    before_date date; after_date date; parent_row record; actor uuid; previous_company text;
BEGIN
    IF TG_OP <> 'INSERT' THEN before_row := to_jsonb(OLD); END IF;
    IF TG_OP <> 'DELETE' THEN after_row := to_jsonb(NEW); END IF;
    IF TG_OP = 'UPDATE' AND before_row->>'company_id' IS DISTINCT FROM after_row->>'company_id' THEN
        RAISE EXCEPTION 'Accounting and physical source records cannot move between companies';
    END IF;
    source_row := COALESCE(after_row, before_row);
    tenant := (source_row->>'company_id')::uuid;
    previous_company := current_setting('app.current_company_id', true);
    IF nullif(previous_company, '') IS NOT NULL AND previous_company::uuid <> tenant THEN
        RAISE EXCEPTION 'Source company does not match the current company context' USING ERRCODE = '42501';
    END IF;
    -- Audit under the source's tenant using invoker privileges, then restore context before
    -- PostgreSQL evaluates the source row's own RLS policy. No SECURITY DEFINER or bypass.
    PERFORM set_config('app.current_company_id', tenant::text, true);
    IF TG_TABLE_NAME = 'transactions' THEN
        IF source_row->>'transaction_type' = 'fuel_daily_close' THEN
            PERFORM set_config('app.current_company_id', COALESCE(previous_company, ''), true);
            IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
        END IF;
        before_date := (before_row->>'transaction_date')::date;
        after_date := (after_row->>'transaction_date')::date;
        IF source_row->>'reversal_of_id' IS NOT NULL THEN
            SELECT transaction_date INTO after_date FROM acct.transactions
            WHERE id = (source_row->>'reversal_of_id')::uuid AND company_id = tenant;
        END IF;
    ELSIF TG_TABLE_NAME = 'journal_entries' THEN
        IF EXISTS (SELECT 1 FROM acct.transactions c WHERE c.company_id = tenant AND (c.metadata ? 'posting_snapshot' OR c.metadata ? 'reading_correction')
            AND c.id IN ((before_row->>'transaction_id')::uuid, (after_row->>'transaction_id')::uuid)) THEN
            RAISE EXCEPTION 'Posted Daily Close journal lines are immutable';
        END IF;
        SELECT * INTO parent_row FROM acct.transactions WHERE id = (source_row->>'transaction_id')::uuid AND company_id = tenant;
        IF parent_row.transaction_type = 'fuel_daily_close' AND parent_row.metadata ? 'posting_snapshot' THEN
            RAISE EXCEPTION 'Posted Daily Close journal lines are immutable';
        END IF;
        SELECT COALESCE(original.transaction_date, t.transaction_date) INTO before_date
            FROM acct.transactions t LEFT JOIN acct.transactions original ON original.id = t.reversal_of_id AND original.company_id = tenant
            WHERE t.id = (before_row->>'transaction_id')::uuid AND t.company_id = tenant;
        SELECT COALESCE(original.transaction_date, t.transaction_date) INTO after_date
            FROM acct.transactions t LEFT JOIN acct.transactions original ON original.id = t.reversal_of_id AND original.company_id = tenant
            WHERE t.id = (after_row->>'transaction_id')::uuid AND t.company_id = tenant;
    ELSIF TG_TABLE_NAME IN ('invoice_line_items','bill_line_items') THEN
        IF TG_TABLE_NAME = 'invoice_line_items' THEN
            SELECT invoice_date INTO before_date FROM acct.invoices WHERE id = (before_row->>'invoice_id')::uuid AND company_id = tenant;
            SELECT invoice_date INTO after_date FROM acct.invoices WHERE id = (after_row->>'invoice_id')::uuid AND company_id = tenant;
        ELSE
            SELECT bill_date INTO before_date FROM acct.bills WHERE id = (before_row->>'bill_id')::uuid AND company_id = tenant;
            SELECT bill_date INTO after_date FROM acct.bills WHERE id = (after_row->>'bill_id')::uuid AND company_id = tenant;
        END IF;
    ELSIF TG_TABLE_NAME = 'amanat_transactions' THEN
        SELECT t.transaction_date INTO before_date FROM acct.journal_entries je JOIN acct.transactions t ON t.id = je.transaction_id
            WHERE je.id = (before_row->>'journal_entry_id')::uuid AND t.company_id = tenant;
        SELECT t.transaction_date INTO after_date FROM acct.journal_entries je JOIN acct.transactions t ON t.id = je.transaction_id
            WHERE je.id = (after_row->>'journal_entry_id')::uuid AND t.company_id = tenant;
    ELSIF TG_TABLE_NAME IN ('tank_readings', 'nozzle_readings') THEN
        before_date := (before_row->>'reading_date')::date;
        after_date := (after_row->>'reading_date')::date;
        IF EXISTS (SELECT 1 FROM acct.transactions c WHERE c.company_id = tenant
            AND c.transaction_type = 'fuel_daily_close' AND (c.metadata ? 'posting_snapshot' OR c.metadata ? 'reading_correction')
            AND c.transaction_date IN (before_date, after_date)) THEN
            RAISE EXCEPTION 'Physical observations on a posted Daily Close are immutable; record a separate adjustment';
        END IF;
    ELSIF TG_TABLE_NAME = 'stock_movements' THEN
        IF (before_row->>'reference_type' = 'fuel.reading_correction' OR after_row->>'reference_type' = 'fuel.reading_correction') THEN
            IF EXISTS (SELECT 1 FROM fuel.daily_close_reading_corrections c
                WHERE c.company_id = tenant AND c.id IN ((before_row->>'reference_id')::uuid, (after_row->>'reference_id')::uuid)) THEN
                RAISE EXCEPTION 'Reading correction stock is immutable; record another correction';
            END IF;
        END IF;
        before_date := (before_row->>'movement_date')::date;
        after_date := (after_row->>'movement_date')::date;
        IF EXISTS (SELECT 1 FROM acct.transactions c WHERE c.company_id = tenant
            AND (c.metadata ? 'posting_snapshot' OR c.metadata ? 'reading_correction') AND c.id IN (
                (before_row->>'gl_transaction_id')::uuid, (after_row->>'gl_transaction_id')::uuid,
                (before_row->>'reference_id')::uuid, (after_row->>'reference_id')::uuid)) THEN
            RAISE EXCEPTION 'Posted Daily Close stock movements are immutable; record a separate adjustment';
        END IF;
        IF source_row->>'reference_type' = 'fuel.daily_close' THEN
            PERFORM set_config('app.current_company_id', COALESCE(previous_company, ''), true);
            IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
        END IF;
    ELSE
        before_date := COALESCE((before_row->>'invoice_date')::date, (before_row->>'bill_date')::date, (before_row->>'payment_date')::date, (before_row->>'advance_date')::date, (before_row->>'transaction_date')::date);
        after_date := COALESCE((after_row->>'invoice_date')::date, (after_row->>'bill_date')::date, (after_row->>'payment_date')::date, (after_row->>'advance_date')::date, (after_row->>'transaction_date')::date);
    END IF;
    -- Acquiring a row-trigger lock must never block: a source row may already be locked.
    IF before_date IS NOT NULL THEN
        IF NOT pg_try_advisory_xact_lock_shared(hashtext(tenant::text), hashtext(before_date::text)) THEN
            RAISE EXCEPTION 'Daily Close is being finalized; retry this transaction' USING ERRCODE = '40001';
        END IF;
    END IF;
    IF after_date IS NOT NULL AND after_date IS DISTINCT FROM before_date THEN
        IF NOT pg_try_advisory_xact_lock_shared(hashtext(tenant::text), hashtext(after_date::text)) THEN
            RAISE EXCEPTION 'Daily Close is being finalized; retry this transaction' USING ERRCODE = '40001';
        END IF;
    END IF;
    IF before_row IS NOT DISTINCT FROM after_row THEN
        PERFORM set_config('app.current_company_id', COALESCE(previous_company, ''), true);
            IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
    END IF;
    actor := COALESCE(nullif(current_setting('app.current_user_id', true), '')::uuid,
        (after_row->>'updated_by_user_id')::uuid,
        CASE WHEN TG_OP = 'INSERT' THEN (after_row->>'created_by_user_id')::uuid ELSE NULL END);
    INSERT INTO fuel.daily_close_activity
      (id, company_id, close_transaction_id, source_table, source_id, operation, occurred_at, actor_id, before_data, after_data)
    SELECT gen_random_uuid(), tenant, c.id, TG_TABLE_SCHEMA||'.'||TG_TABLE_NAME,
      (source_row->>'id')::uuid, TG_OP, clock_timestamp(), actor, before_row, after_row
    FROM acct.transactions c
    WHERE c.company_id = tenant AND c.transaction_type = 'fuel_daily_close'
      AND (c.metadata ? 'posting_snapshot' OR c.metadata ? 'reading_correction')
      AND (c.transaction_date = before_date OR c.transaction_date = after_date);
    PERFORM set_config('app.current_company_id', COALESCE(previous_company, ''), true);
            IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
END $$;
SQL);
    }

    public function down(): void
    {
        // Restore the original whitelist (matches 2026_09_15_220000_audit_post_close_activity).
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION fuel.capture_post_close_activity() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    before_row jsonb; after_row jsonb; source_row jsonb; tenant uuid;
    before_date date; after_date date; parent_row record; actor uuid; previous_company text;
BEGIN
    IF TG_OP <> 'INSERT' THEN before_row := to_jsonb(OLD); END IF;
    IF TG_OP <> 'DELETE' THEN after_row := to_jsonb(NEW); END IF;
    IF TG_OP = 'UPDATE' AND before_row->>'company_id' IS DISTINCT FROM after_row->>'company_id' THEN
        RAISE EXCEPTION 'Accounting and physical source records cannot move between companies';
    END IF;
    source_row := COALESCE(after_row, before_row);
    tenant := (source_row->>'company_id')::uuid;
    previous_company := current_setting('app.current_company_id', true);
    IF nullif(previous_company, '') IS NOT NULL AND previous_company::uuid <> tenant THEN
        RAISE EXCEPTION 'Source company does not match the current company context' USING ERRCODE = '42501';
    END IF;
    PERFORM set_config('app.current_company_id', tenant::text, true);
    IF TG_TABLE_NAME = 'transactions' THEN
        IF source_row->>'transaction_type' IN ('fuel_daily_close','fuel_daily_close_reversal') THEN
            PERFORM set_config('app.current_company_id', COALESCE(previous_company, ''), true);
            IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
        END IF;
        before_date := (before_row->>'transaction_date')::date;
        after_date := (after_row->>'transaction_date')::date;
        IF source_row->>'reversal_of_id' IS NOT NULL THEN
            SELECT transaction_date INTO after_date FROM acct.transactions
            WHERE id = (source_row->>'reversal_of_id')::uuid AND company_id = tenant;
        END IF;
    ELSIF TG_TABLE_NAME = 'journal_entries' THEN
        IF EXISTS (SELECT 1 FROM acct.transactions c WHERE c.company_id = tenant AND (c.metadata ? 'posting_snapshot' OR c.metadata ? 'reading_correction')
            AND c.id IN ((before_row->>'transaction_id')::uuid, (after_row->>'transaction_id')::uuid)) THEN
            RAISE EXCEPTION 'Posted Daily Close journal lines are immutable';
        END IF;
        SELECT * INTO parent_row FROM acct.transactions WHERE id = (source_row->>'transaction_id')::uuid AND company_id = tenant;
        IF parent_row.transaction_type = 'fuel_daily_close' AND parent_row.metadata ? 'posting_snapshot' THEN
            RAISE EXCEPTION 'Posted Daily Close journal lines are immutable';
        END IF;
        SELECT COALESCE(original.transaction_date, t.transaction_date) INTO before_date
            FROM acct.transactions t LEFT JOIN acct.transactions original ON original.id = t.reversal_of_id AND original.company_id = tenant
            WHERE t.id = (before_row->>'transaction_id')::uuid AND t.company_id = tenant;
        SELECT COALESCE(original.transaction_date, t.transaction_date) INTO after_date
            FROM acct.transactions t LEFT JOIN acct.transactions original ON original.id = t.reversal_of_id AND original.company_id = tenant
            WHERE t.id = (after_row->>'transaction_id')::uuid AND t.company_id = tenant;
    ELSIF TG_TABLE_NAME IN ('invoice_line_items','bill_line_items') THEN
        IF TG_TABLE_NAME = 'invoice_line_items' THEN
            SELECT invoice_date INTO before_date FROM acct.invoices WHERE id = (before_row->>'invoice_id')::uuid AND company_id = tenant;
            SELECT invoice_date INTO after_date FROM acct.invoices WHERE id = (after_row->>'invoice_id')::uuid AND company_id = tenant;
        ELSE
            SELECT bill_date INTO before_date FROM acct.bills WHERE id = (before_row->>'bill_id')::uuid AND company_id = tenant;
            SELECT bill_date INTO after_date FROM acct.bills WHERE id = (after_row->>'bill_id')::uuid AND company_id = tenant;
        END IF;
    ELSIF TG_TABLE_NAME = 'amanat_transactions' THEN
        SELECT t.transaction_date INTO before_date FROM acct.journal_entries je JOIN acct.transactions t ON t.id = je.transaction_id
            WHERE je.id = (before_row->>'journal_entry_id')::uuid AND t.company_id = tenant;
        SELECT t.transaction_date INTO after_date FROM acct.journal_entries je JOIN acct.transactions t ON t.id = je.transaction_id
            WHERE je.id = (after_row->>'journal_entry_id')::uuid AND t.company_id = tenant;
    ELSIF TG_TABLE_NAME IN ('tank_readings', 'nozzle_readings') THEN
        before_date := (before_row->>'reading_date')::date;
        after_date := (after_row->>'reading_date')::date;
        IF EXISTS (SELECT 1 FROM acct.transactions c WHERE c.company_id = tenant
            AND c.transaction_type = 'fuel_daily_close' AND (c.metadata ? 'posting_snapshot' OR c.metadata ? 'reading_correction')
            AND c.transaction_date IN (before_date, after_date)) THEN
            RAISE EXCEPTION 'Physical observations on a posted Daily Close are immutable; record a separate adjustment';
        END IF;
    ELSIF TG_TABLE_NAME = 'stock_movements' THEN
        IF (before_row->>'reference_type' = 'fuel.reading_correction' OR after_row->>'reference_type' = 'fuel.reading_correction') THEN
            IF EXISTS (SELECT 1 FROM fuel.daily_close_reading_corrections c
                WHERE c.company_id = tenant AND c.id IN ((before_row->>'reference_id')::uuid, (after_row->>'reference_id')::uuid)) THEN
                RAISE EXCEPTION 'Reading correction stock is immutable; record another correction';
            END IF;
        END IF;
        before_date := (before_row->>'movement_date')::date;
        after_date := (after_row->>'movement_date')::date;
        IF EXISTS (SELECT 1 FROM acct.transactions c WHERE c.company_id = tenant
            AND (c.metadata ? 'posting_snapshot' OR c.metadata ? 'reading_correction') AND c.id IN (
                (before_row->>'gl_transaction_id')::uuid, (after_row->>'gl_transaction_id')::uuid,
                (before_row->>'reference_id')::uuid, (after_row->>'reference_id')::uuid)) THEN
            RAISE EXCEPTION 'Posted Daily Close stock movements are immutable; record a separate adjustment';
        END IF;
        IF source_row->>'reference_type' = 'fuel.daily_close' THEN
            PERFORM set_config('app.current_company_id', COALESCE(previous_company, ''), true);
            IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
        END IF;
    ELSE
        before_date := COALESCE((before_row->>'invoice_date')::date, (before_row->>'bill_date')::date, (before_row->>'payment_date')::date, (before_row->>'advance_date')::date, (before_row->>'transaction_date')::date);
        after_date := COALESCE((after_row->>'invoice_date')::date, (after_row->>'bill_date')::date, (after_row->>'payment_date')::date, (after_row->>'advance_date')::date, (after_row->>'transaction_date')::date);
    END IF;
    IF before_date IS NOT NULL THEN
        IF NOT pg_try_advisory_xact_lock_shared(hashtext(tenant::text), hashtext(before_date::text)) THEN
            RAISE EXCEPTION 'Daily Close is being finalized; retry this transaction' USING ERRCODE = '40001';
        END IF;
    END IF;
    IF after_date IS NOT NULL AND after_date IS DISTINCT FROM before_date THEN
        IF NOT pg_try_advisory_xact_lock_shared(hashtext(tenant::text), hashtext(after_date::text)) THEN
            RAISE EXCEPTION 'Daily Close is being finalized; retry this transaction' USING ERRCODE = '40001';
        END IF;
    END IF;
    IF before_row IS NOT DISTINCT FROM after_row THEN
        PERFORM set_config('app.current_company_id', COALESCE(previous_company, ''), true);
            IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
    END IF;
    actor := COALESCE(nullif(current_setting('app.current_user_id', true), '')::uuid,
        (after_row->>'updated_by_user_id')::uuid,
        CASE WHEN TG_OP = 'INSERT' THEN (after_row->>'created_by_user_id')::uuid ELSE NULL END);
    INSERT INTO fuel.daily_close_activity
      (id, company_id, close_transaction_id, source_table, source_id, operation, occurred_at, actor_id, before_data, after_data)
    SELECT gen_random_uuid(), tenant, c.id, TG_TABLE_SCHEMA||'.'||TG_TABLE_NAME,
      (source_row->>'id')::uuid, TG_OP, clock_timestamp(), actor, before_row, after_row
    FROM acct.transactions c
    WHERE c.company_id = tenant AND c.transaction_type = 'fuel_daily_close'
      AND (c.metadata ? 'posting_snapshot' OR c.metadata ? 'reading_correction')
      AND (c.transaction_date = before_date OR c.transaction_date = after_date);
    PERFORM set_config('app.current_company_id', COALESCE(previous_company, ''), true);
            IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
END $$;
SQL);
    }
};
