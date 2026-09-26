<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A posted (and since-unlocked) close's credit invoice can now legitimately receive a
 * post-close discount -- see DailyClosePostCloseDiscountService::apply(). That amends the
 * invoice's discount_amount/total_amount together with balance, which the original
 * protect_close_credit_invoice() trigger (2026_09_17_210000) did not allow: any change
 * outside paid_amount/balance/paid_at/status/updated_at/updated_by_user_id was rejected.
 * This widens the exception to let discount_amount/total_amount move with balance, and only
 * as a discount: the discount grows, the total falls by exactly that much, and only while the
 * close is unlocked - enforced here in the database, not left to the service. The service also
 * checks the invoice belongs to this close and the discount does not exceed the balance.
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
            IF TG_TABLE_NAME = 'invoices' AND TG_OP = 'UPDATE'
                AND (to_jsonb(NEW) - ARRAY['paid_amount','balance','paid_at','status','updated_at','updated_by_user_id','discount_amount','total_amount'])
                  = (to_jsonb(OLD) - ARRAY['paid_amount','balance','paid_at','status','updated_at','updated_by_user_id','discount_amount','total_amount'])
                AND NEW.status NOT IN ('void','cancelled','reversed','draft')
                AND NEW.paid_amount >= 0 AND NEW.balance >= 0 AND NEW.discount_amount >= 0
                AND abs(NEW.paid_amount + NEW.balance - NEW.total_amount) < 0.000001
                -- A discount only grows, the total falls by exactly what it grew, and only
                -- while the close is unlocked. Anything else stays refused here in the database.
                AND NEW.discount_amount >= OLD.discount_amount
                AND abs((OLD.total_amount - NEW.total_amount) - (NEW.discount_amount - OLD.discount_amount)) < 0.000001
                AND (NEW.discount_amount = OLD.discount_amount
                     OR NOT EXISTS(SELECT 1 FROM acct.transactions WHERE id = journal_id AND company_id = tenant AND is_locked)) THEN
                CONTINUE;
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
SQL);
    }
};
