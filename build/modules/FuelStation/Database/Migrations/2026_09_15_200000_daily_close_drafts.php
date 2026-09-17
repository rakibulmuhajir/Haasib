<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel.daily_close_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->references('id')->on('auth.companies');
            $table->date('business_date');
            $table->jsonb('payload');
            $table->foreignUuid('created_by_user_id')->references('id')->on('auth.users');
            $table->foreignUuid('updated_by_user_id')->references('id')->on('auth.users');
            $table->timestamps();
            $table->unique(['company_id', 'business_date']);
        });
        DB::statement('ALTER TABLE fuel.daily_close_drafts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fuel.daily_close_drafts FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY daily_close_drafts_company ON fuel.daily_close_drafts
            USING (company_id = nullif(current_setting('app.current_company_id', true), '')::uuid
              OR current_setting('app.is_super_admin', true) = 'true')");
        DB::unprepared(<<<'SQL'
CREATE FUNCTION fuel.protect_close_snapshot() RETURNS trigger LANGUAGE plpgsql AS $$
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
CREATE TRIGGER protect_close_snapshot BEFORE UPDATE OR DELETE ON acct.transactions
FOR EACH ROW EXECUTE FUNCTION fuel.protect_close_snapshot();
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS protect_close_snapshot ON acct.transactions');
        DB::statement('DROP FUNCTION IF EXISTS fuel.protect_close_snapshot()');
        Schema::dropIfExists('fuel.daily_close_drafts');
    }
};
