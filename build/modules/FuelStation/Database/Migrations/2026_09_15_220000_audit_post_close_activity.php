<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $sources = ['acct.transactions', 'acct.journal_entries', 'acct.invoices', 'acct.bills', 'inv.stock_movements', 'fuel.tank_readings', 'fuel.nozzle_readings', 'acct.payments', 'acct.bill_payments', 'acct.invoice_line_items', 'acct.bill_line_items', 'fuel.amanat_transactions', 'auth.partner_transactions', 'pay.salary_advances'];

    public function up(): void
    {
        Schema::create('fuel.daily_close_activity', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->references('id')->on('auth.companies');
            $table->foreignUuid('close_transaction_id')->references('id')->on('acct.transactions');
            $table->string('source_table', 100);
            $table->uuid('source_id');
            $table->string('operation', 10);
            $table->timestampTz('occurred_at');
            $table->uuid('actor_id')->nullable();
            $table->jsonb('before_data')->nullable();
            $table->jsonb('after_data')->nullable();
            $table->index(['company_id', 'close_transaction_id', 'occurred_at', 'id'], 'daily_close_activity_lookup');
        });
        DB::statement('ALTER TABLE fuel.daily_close_activity ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fuel.daily_close_activity FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY daily_close_activity_company ON fuel.daily_close_activity USING (
            company_id = nullif(current_setting('app.current_company_id', true), '')::uuid
            OR current_setting('app.is_super_admin', true) = 'true')");
        DB::unprepared(<<<'SQL'
CREATE FUNCTION fuel.prevent_audit_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Daily Close audit evidence is append-only'; END $$;
CREATE TRIGGER prevent_audit_mutation BEFORE UPDATE OR DELETE ON fuel.daily_close_activity
FOR EACH ROW EXECUTE FUNCTION fuel.prevent_audit_mutation();

-- Close acquires an exclusive advisory lock before reading sources. Row writers use
-- nonblocking shared locks: conflict raises 40001 and the entire command retries.
-- This avoids waiting for a close while holding rows the close needs.
CREATE FUNCTION fuel.capture_post_close_activity() RETURNS trigger LANGUAGE plpgsql AS $$
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
        foreach ($this->sources as $table) {
            DB::statement("CREATE TRIGGER capture_post_close_activity BEFORE INSERT OR UPDATE OR DELETE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION fuel.capture_post_close_activity()");
        }
    }

    public function down(): void
    {
        foreach ($this->sources as $table) {
            DB::statement("DROP TRIGGER IF EXISTS capture_post_close_activity ON {$table}");
        }
        DB::statement('DROP FUNCTION IF EXISTS fuel.capture_post_close_activity()');
        Schema::dropIfExists('fuel.daily_close_activity');
        DB::statement('DROP FUNCTION IF EXISTS fuel.prevent_audit_mutation()');
    }
};
