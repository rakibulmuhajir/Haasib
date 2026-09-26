<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 2026_09_26_010000 read NEW.status / NEW.total_amount in one condition shared by invoices and
 * their lines. PL/pgSQL resolves every field in that expression, so a change to a posted close's
 * invoice LINE failed with "record new has no field status" instead of the intended refusal. The
 * protection held, but by accident. Invoice-only checks now sit inside an invoices-only branch;
 * lines of a posted close's invoice are always refused with the proper message.
 */
return new class extends Migration
{
    public function up(): void
    {
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
                -- Payments move paid_amount/balance/status; a post-close discount also moves
                -- discount_amount/total_amount, only upward for the discount, total down by exactly
                -- as much, and only while the close is unlocked. Nothing else may change.
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
    }

    public function down(): void
    {
        // Intentionally empty: the previous definition had the field-resolution bug this fixes.
    }
};
