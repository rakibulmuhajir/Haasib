<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
-- Lock order (do not invert):
--   (1) advisory lock keyed on 'opening:'||company_id -- EXCLUSIVE for
--       OpeningBalance\SaveAction and OpeningBalance\LockAction (taken as the very
--       first statement of their transaction), SHARED for ordinary writers via this
--       trigger.
--   (2) document row locks (invoices/bills/company etc.).
-- Never take a document row lock first and then wait on this advisory lock in the
-- same transaction.
CREATE OR REPLACE FUNCTION acct.protect_locked_opening() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    row_data jsonb; opening jsonb; document_id text; kind text; protected boolean := false;
    journal_id uuid; journal_row record;
BEGIN
    FOR row_data IN SELECT value FROM jsonb_array_elements(CASE
        WHEN TG_OP = 'INSERT' THEN jsonb_build_array(to_jsonb(NEW))
        WHEN TG_OP = 'DELETE' THEN jsonb_build_array(to_jsonb(OLD))
        ELSE jsonb_build_array(to_jsonb(OLD), to_jsonb(NEW)) END)
    LOOP
    -- Never wait after PostgreSQL has acquired a source-row lock. Retry the outer unit of work.
    IF NOT pg_try_advisory_xact_lock_shared(hashtext('opening:'||(row_data->>'company_id'))) THEN
        RAISE EXCEPTION 'Opening balances are being finalized; retry this transaction' USING ERRCODE = '40001';
    END IF;
    SELECT settings->'opening_balances' INTO opening FROM auth.companies
      WHERE id = (row_data->>'company_id')::uuid;
    IF nullif(opening->>'locked_at', '') IS NULL THEN
        CONTINUE;
    END IF;
    IF TG_TABLE_NAME IN ('invoices', 'bills') THEN
        kind := CASE WHEN TG_TABLE_NAME = 'invoices' THEN 'invoice_ids' ELSE 'bill_ids' END;
        document_id := row_data->>'id';
        protected := COALESCE((opening->kind) ? document_id, false) OR COALESCE((opening->('retired_'||kind)) ? document_id, false);
        IF protected AND TG_OP = 'UPDATE'
          AND (to_jsonb(NEW) - ARRAY['paid_amount','balance','paid_at','status','updated_at','updated_by_user_id'])
            = (to_jsonb(OLD) - ARRAY['paid_amount','balance','paid_at','status','updated_at','updated_by_user_id'])
          AND NEW.status NOT IN ('void','cancelled','reversed','draft')
          AND NEW.paid_amount >= 0 AND NEW.balance >= 0
          AND abs(NEW.paid_amount + NEW.balance - NEW.total_amount) < 0.000001 THEN
            RETURN NEW;
        END IF;
    ELSIF TG_TABLE_NAME IN ('invoice_line_items', 'bill_line_items') THEN
        kind := CASE WHEN TG_TABLE_NAME = 'invoice_line_items' THEN 'invoice_ids' ELSE 'bill_ids' END;
        document_id := row_data->>CASE WHEN TG_TABLE_NAME = 'invoice_line_items' THEN 'invoice_id' ELSE 'bill_id' END;
        protected := COALESCE((opening->kind) ? document_id, false) OR COALESCE((opening->('retired_'||kind)) ? document_id, false);
    ELSE
        journal_id := CASE WHEN TG_TABLE_NAME = 'transactions'
            THEN COALESCE((row_data->>'reversal_of_id')::uuid, (row_data->>'id')::uuid)
            ELSE (row_data->>'transaction_id')::uuid END;
        IF row_data ? 'journal_entry_id' THEN
            SELECT transaction_id INTO journal_id FROM acct.journal_entries WHERE id = (row_data->>'journal_entry_id')::uuid AND company_id = (row_data->>'company_id')::uuid;
        END IF;
        SELECT * INTO journal_row FROM acct.transactions WHERE id = journal_id AND company_id = (row_data->>'company_id')::uuid;
        protected := COALESCE(opening->>'journal_id' = journal_id::text, false)
          OR COALESCE((opening->'retired_journal_ids') ? journal_id::text, false);
        IF journal_row.reference_type = 'acct.invoices' THEN
            protected := protected OR COALESCE((opening->'invoice_ids') ? journal_row.reference_id::text, false);
        ELSIF journal_row.reference_type = 'acct.bills' THEN
            protected := protected OR COALESCE((opening->'bill_ids') ? journal_row.reference_id::text, false);
        END IF;
    END IF;
    IF protected AND TG_TABLE_NAME = 'salary_advances' AND TG_OP = 'UPDATE'
      AND (to_jsonb(NEW) - ARRAY['amount_recovered','amount_outstanding','status','updated_at'])
        = (to_jsonb(OLD) - ARRAY['amount_recovered','amount_outstanding','status','updated_at'])
      AND (to_jsonb(NEW)->>'status') IN ('pending','partially_recovered','fully_recovered')
      AND (to_jsonb(NEW)->>'amount_recovered')::numeric >= 0 AND (to_jsonb(NEW)->>'amount_outstanding')::numeric >= 0
      AND abs((to_jsonb(NEW)->>'amount_recovered')::numeric + (to_jsonb(NEW)->>'amount_outstanding')::numeric - (to_jsonb(NEW)->>'amount')::numeric) < 0.01 THEN
        RETURN NEW;
    END IF;
    IF protected THEN RAISE EXCEPTION 'Locked opening-balance records cannot be changed or reversed'; END IF;
    END LOOP;
    IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
END $$;
SQL);
        foreach (['acct.invoices', 'acct.bills', 'acct.invoice_line_items', 'acct.bill_line_items', 'acct.transactions', 'acct.journal_entries', 'fuel.amanat_transactions', 'auth.partner_transactions', 'pay.salary_advances'] as $table) {
            DB::statement("CREATE TRIGGER protect_locked_opening BEFORE INSERT OR UPDATE OR DELETE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION acct.protect_locked_opening()");
        }
    }

    public function down(): void
    {
        foreach (['acct.invoices', 'acct.bills', 'acct.invoice_line_items', 'acct.bill_line_items', 'acct.transactions', 'acct.journal_entries', 'fuel.amanat_transactions', 'auth.partner_transactions', 'pay.salary_advances'] as $table) {
            DB::statement("DROP TRIGGER IF EXISTS protect_locked_opening ON {$table}");
        }
        DB::statement('DROP FUNCTION IF EXISTS acct.protect_locked_opening()');
    }
};
