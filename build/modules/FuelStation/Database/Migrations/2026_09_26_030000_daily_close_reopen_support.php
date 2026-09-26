<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Edit day": a posted (and not locked) Daily Close can be fully reopened back into a
 * parked draft in the same form used to create it — see DailyCloseReopenService. Reopening
 * has to undo rows the posted-close protection triggers (2026_09_15_220000, 2026_09_17_210000
 * and its 2026_09_26 fixes) were built to stop anyone from touching. Those triggers exist to
 * stop an ACCIDENTAL edit, not a deliberate, whole-close reopening, so this migration adds a
 * narrow bypass rather than disabling either trigger: DailyCloseReopenService sets
 * `SET LOCAL app.reopening_close_id = '<close transaction id>'` for the one transaction that
 * performs the reopen, and each trigger function only stands down for rows that belong to
 * exactly that close. Nothing else can end up in that setting (it is never exposed to request
 * input), and it is transaction-local, so it never leaks into any other request or connection.
 *
 * fuel.daily_close_revisions keeps one row per reopening (a day can be posted, edited and
 * reposted more than once), the same append-only shape as fuel.daily_close_unlocks and
 * fuel.daily_close_activity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel.daily_close_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->references('id')->on('auth.companies');
            $table->date('business_date');
            $table->string('close_transaction_number', 50);
            $table->uuid('reopened_by_user_id')->nullable();
            $table->text('reason');
            // The close transaction's own metadata (posting_snapshot + form_input) at the
            // moment it was reopened -- the whole story of what existed before removal.
            $table->jsonb('snapshot');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['company_id', 'business_date', 'created_at'], 'daily_close_revisions_lookup');

            $table->foreign('reopened_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE fuel.daily_close_revisions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fuel.daily_close_revisions FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY daily_close_revisions_company ON fuel.daily_close_revisions USING (
            company_id = nullif(current_setting('app.current_company_id', true), '')::uuid
            OR current_setting('app.is_super_admin', true) = 'true')");

        DB::unprepared(<<<'SQL'
CREATE FUNCTION fuel.prevent_revision_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Daily Close revision history is append-only'; END $$;
CREATE TRIGGER prevent_revision_mutation BEFORE UPDATE OR DELETE ON fuel.daily_close_revisions
FOR EACH ROW EXECUTE FUNCTION fuel.prevent_revision_mutation();
SQL);

        // ── protect_close_credit_invoice: bypass for the one close being reopened ──────────
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION fuel.protect_close_credit_invoice() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE row_data jsonb; journal_id uuid; tenant uuid; protected boolean; reopening_id text;
BEGIN
    reopening_id := nullif(current_setting('app.reopening_close_id', true), '');
    FOR row_data IN SELECT value FROM jsonb_array_elements(
        CASE WHEN TG_OP = 'INSERT' THEN jsonb_build_array(to_jsonb(NEW))
             WHEN TG_OP = 'DELETE' THEN jsonb_build_array(to_jsonb(OLD))
             ELSE jsonb_build_array(to_jsonb(OLD), to_jsonb(NEW)) END)
    LOOP
        tenant := (row_data->>'company_id')::uuid;
        IF TG_TABLE_NAME = 'invoices' THEN
            journal_id := (row_data->>'transaction_id')::uuid;
        ELSE
            SELECT transaction_id INTO journal_id FROM acct.invoices
                WHERE id = (row_data->>'invoice_id')::uuid AND company_id = tenant;
        END IF;
        IF reopening_id IS NOT NULL AND journal_id IS NOT NULL AND journal_id::text = reopening_id THEN
            CONTINUE;
        END IF;
        SELECT EXISTS(SELECT 1 FROM acct.transactions WHERE id = journal_id AND company_id = tenant
            AND transaction_type = 'fuel_daily_close' AND metadata ? 'posting_snapshot') INTO protected;
        IF protected THEN
            IF TG_TABLE_NAME = 'invoices' AND TG_OP = 'UPDATE' THEN
                IF (to_jsonb(NEW) - ARRAY['paid_amount','balance','paid_at','status','updated_at','updated_by_user_id','discount_amount','total_amount'])
                     = (to_jsonb(OLD) - ARRAY['paid_amount','balance','paid_at','status','updated_at','updated_by_user_id','discount_amount','total_amount'])
                   AND NEW.status NOT IN ('void','cancelled','reversed','draft')
                   AND NEW.paid_amount >= 0 AND NEW.balance >= 0 AND NEW.discount_amount >= 0
                   AND abs(NEW.paid_amount + NEW.balance - NEW.total_amount) < 0.000001
                   AND NEW.discount_amount >= OLD.discount_amount
                   AND abs((OLD.total_amount - NEW.total_amount) - (NEW.discount_amount - OLD.discount_amount)) < 0.000001
                   AND (NEW.discount_amount = OLD.discount_amount
                        OR NOT EXISTS(SELECT 1 FROM acct.transactions WHERE id = journal_id AND company_id = tenant AND is_locked)) THEN
                    CONTINUE;
                END IF;
            END IF;
            RAISE EXCEPTION 'Posted Daily Close credit invoices cannot be changed; record a separate adjustment';
        END IF;
    END LOOP;
    IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
END $$;
SQL);

        // ── capture_post_close_activity: bypass for journal_entries / tank+nozzle readings /
        //    stock_movements belonging to the one close being reopened ─────────────────────
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION fuel.capture_post_close_activity() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    before_row jsonb; after_row jsonb; source_row jsonb; tenant uuid;
    before_date date; after_date date; parent_row record; actor uuid; previous_company text;
    reopening_id text; reopening_date date;
BEGIN
    reopening_id := nullif(current_setting('app.reopening_close_id', true), '');
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
    IF reopening_id IS NOT NULL THEN
        SELECT transaction_date INTO reopening_date FROM acct.transactions
            WHERE id = reopening_id::uuid AND company_id = tenant;
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
        IF reopening_id IS NOT NULL AND reopening_id IN (before_row->>'transaction_id', after_row->>'transaction_id') THEN
            PERFORM set_config('app.current_company_id', COALESCE(previous_company, ''), true);
            IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
        END IF;
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
        IF reopening_date IS NOT NULL AND reopening_date IN (before_date, after_date) THEN
            PERFORM set_config('app.current_company_id', COALESCE(previous_company, ''), true);
            IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
        END IF;
        IF EXISTS (SELECT 1 FROM acct.transactions c WHERE c.company_id = tenant
            AND c.transaction_type = 'fuel_daily_close' AND (c.metadata ? 'posting_snapshot' OR c.metadata ? 'reading_correction')
            AND c.transaction_date IN (before_date, after_date)) THEN
            RAISE EXCEPTION 'Physical observations on a posted Daily Close are immutable; record a separate adjustment';
        END IF;
    ELSIF TG_TABLE_NAME = 'stock_movements' THEN
        IF reopening_id IS NOT NULL AND reopening_id IN (before_row->>'gl_transaction_id', after_row->>'gl_transaction_id', before_row->>'reference_id', after_row->>'reference_id') THEN
            PERFORM set_config('app.current_company_id', COALESCE(previous_company, ''), true);
            IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
        END IF;
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
        DB::statement('DROP TRIGGER IF EXISTS prevent_revision_mutation ON fuel.daily_close_revisions');
        DB::statement('DROP FUNCTION IF EXISTS fuel.prevent_revision_mutation()');
        Schema::dropIfExists('fuel.daily_close_revisions');

        // Restore the pre-reopen-support definitions (2026_09_26_020000 / 2026_09_17_230000).
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION fuel.protect_close_credit_invoice() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE row_data jsonb; journal_id uuid; tenant uuid; protected boolean;
BEGIN
    FOR row_data IN SELECT value FROM jsonb_array_elements(
        CASE WHEN TG_OP = 'INSERT' THEN jsonb_build_array(to_jsonb(NEW))
             WHEN TG_OP = 'DELETE' THEN jsonb_build_array(to_jsonb(OLD))
             ELSE jsonb_build_array(to_jsonb(OLD), to_jsonb(NEW)) END)
    LOOP
        tenant := (row_data->>'company_id')::uuid;
        IF TG_TABLE_NAME = 'invoices' THEN
            journal_id := (row_data->>'transaction_id')::uuid;
        ELSE
            SELECT transaction_id INTO journal_id FROM acct.invoices
                WHERE id = (row_data->>'invoice_id')::uuid AND company_id = tenant;
        END IF;
        SELECT EXISTS(SELECT 1 FROM acct.transactions WHERE id = journal_id AND company_id = tenant
            AND transaction_type = 'fuel_daily_close' AND metadata ? 'posting_snapshot') INTO protected;
        IF protected THEN
            IF TG_TABLE_NAME = 'invoices' AND TG_OP = 'UPDATE' THEN
                IF (to_jsonb(NEW) - ARRAY['paid_amount','balance','paid_at','status','updated_at','updated_by_user_id','discount_amount','total_amount'])
                     = (to_jsonb(OLD) - ARRAY['paid_amount','balance','paid_at','status','updated_at','updated_by_user_id','discount_amount','total_amount'])
                   AND NEW.status NOT IN ('void','cancelled','reversed','draft')
                   AND NEW.paid_amount >= 0 AND NEW.balance >= 0 AND NEW.discount_amount >= 0
                   AND abs(NEW.paid_amount + NEW.balance - NEW.total_amount) < 0.000001
                   AND NEW.discount_amount >= OLD.discount_amount
                   AND abs((OLD.total_amount - NEW.total_amount) - (NEW.discount_amount - OLD.discount_amount)) < 0.000001
                   AND (NEW.discount_amount = OLD.discount_amount
                        OR NOT EXISTS(SELECT 1 FROM acct.transactions WHERE id = journal_id AND company_id = tenant AND is_locked)) THEN
                    CONTINUE;
                END IF;
            END IF;
            RAISE EXCEPTION 'Posted Daily Close credit invoices cannot be changed; record a separate adjustment';
        END IF;
    END LOOP;
    IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
END $$;
SQL);

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
