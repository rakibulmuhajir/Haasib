<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrections: the one way to change something already posted. The screen looks like editing a
 * record; underneath, the original stays in the books and the change is posted as its own entry
 * (a correcting journal, a credit note, a moved allocation), with this row saying what changed,
 * from what to what, why, and who did it. A correction is never edited or deleted -- a wrong one
 * is put right by another correction.
 *
 * Posted Daily Close credit invoices are guarded by fuel.protect_close_credit_invoice(): only a
 * payment or a discount may touch them. A correction may also change which customer one belongs
 * to -- only inside the correction's own database transaction (app.correction_id, set locally),
 * and nothing else about the invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.corrections', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('correction_number', 30);
            $table->string('entity_type', 40);   // invoice, payment, ...
            $table->uuid('entity_id');
            $table->string('action', 40);        // change_customer, split, ...
            $table->text('reason');
            $table->jsonb('changes');            // before / after, and every record it created or moved
            $table->uuid('transaction_id')->nullable();   // the correcting journal, when one was posted
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->unique(['company_id', 'correction_number']);
            $table->index(['company_id', 'entity_type', 'entity_id']);
        });

        DB::statement('ALTER TABLE acct.corrections ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY corrections_policy ON acct.corrections
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION acct.correction_is_final() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'A correction is a permanent record; put a wrong one right with another correction';
END $$;
CREATE TRIGGER corrections_final BEFORE UPDATE OR DELETE ON acct.corrections
    FOR EACH ROW EXECUTE FUNCTION acct.correction_is_final();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION fuel.protect_close_credit_invoice() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE row_data jsonb; journal_id uuid; tenant uuid; protected boolean; reopening_id text; correcting boolean;
BEGIN
    reopening_id := nullif(current_setting('app.reopening_close_id', true), '');
    correcting := nullif(current_setting('app.correction_id', true), '') IS NOT NULL;
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
                -- A correction may move the invoice to another customer; nothing else changes.
                IF correcting
                   AND (to_jsonb(NEW) - ARRAY['customer_id','paid_amount','balance','paid_at','status','updated_at','updated_by_user_id'])
                     = (to_jsonb(OLD) - ARRAY['customer_id','paid_amount','balance','paid_at','status','updated_at','updated_by_user_id'])
                   AND NEW.status NOT IN ('void','cancelled','reversed','draft')
                   AND NEW.paid_amount >= 0 AND NEW.balance >= 0
                   AND abs(NEW.paid_amount + NEW.balance - NEW.total_amount) < 0.000001 THEN
                    CONTINUE;
                END IF;
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
        DB::unprepared('DROP TRIGGER IF EXISTS corrections_final ON acct.corrections');
        Schema::dropIfExists('acct.corrections');
        DB::unprepared('DROP FUNCTION IF EXISTS acct.correction_is_final()');
        // protect_close_credit_invoice keeps the correction branch: without app.correction_id set
        // it behaves exactly as before.
    }
};
