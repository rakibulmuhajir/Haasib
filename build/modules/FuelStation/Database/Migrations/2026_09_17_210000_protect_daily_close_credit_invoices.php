<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE FUNCTION fuel.protect_close_credit_invoice() RETURNS trigger LANGUAGE plpgsql AS $$
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
            IF TG_TABLE_NAME = 'invoices' AND TG_OP = 'UPDATE'
                AND (to_jsonb(NEW) - ARRAY['paid_amount','balance','paid_at','status','updated_at','updated_by_user_id'])
                  = (to_jsonb(OLD) - ARRAY['paid_amount','balance','paid_at','status','updated_at','updated_by_user_id'])
                AND NEW.status NOT IN ('void','cancelled','reversed','draft')
                AND NEW.paid_amount >= 0 AND NEW.balance >= 0
                AND abs(NEW.paid_amount + NEW.balance - NEW.total_amount) < 0.000001 THEN
                CONTINUE;
            END IF;
            RAISE EXCEPTION 'Posted Daily Close credit invoices cannot be changed; record a separate adjustment';
        END IF;
    END LOOP;
    IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
END $$;
CREATE TRIGGER protect_close_credit_invoice BEFORE INSERT OR UPDATE OR DELETE ON acct.invoices
    FOR EACH ROW EXECUTE FUNCTION fuel.protect_close_credit_invoice();
CREATE TRIGGER protect_close_credit_invoice BEFORE INSERT OR UPDATE OR DELETE ON acct.invoice_line_items
    FOR EACH ROW EXECUTE FUNCTION fuel.protect_close_credit_invoice();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS protect_close_credit_invoice ON acct.invoice_line_items');
        DB::statement('DROP TRIGGER IF EXISTS protect_close_credit_invoice ON acct.invoices');
        DB::statement('DROP FUNCTION IF EXISTS fuel.protect_close_credit_invoice()');
    }
};
