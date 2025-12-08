<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank.bank_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('bank_account_id');
            $table->date('statement_date');
            $table->decimal('statement_ending_balance', 15, 2);
            $table->decimal('book_balance', 15, 2);
            $table->decimal('reconciled_balance', 15, 2)->default(0.00);
            $table->decimal('difference', 15, 2)->default(0.00);
            $table->string('status', 20)->default('in_progress');
            $table->timestamp('started_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('completed_at')->nullable();
            $table->uuid('completed_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('bank_account_id')->references('id')->on('bank.company_bank_accounts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('completed_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['bank_account_id', 'statement_date']);
            $table->index('company_id');
            $table->index('bank_account_id');
            $table->index(['company_id', 'status']);
        });

        DB::statement("ALTER TABLE bank.bank_reconciliations ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY bank_reconciliations_policy ON bank.bank_reconciliations
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Create function to validate reconciliation completion
        DB::statement("
            CREATE OR REPLACE FUNCTION bank.validate_reconciliation_completion()
            RETURNS trigger AS $$
            DECLARE
                v_can_complete boolean := true;
                v_has_previous boolean := false;
                v_last_recon_date date;
            BEGIN
                -- Check if difference is zero for completion
                IF NEW.status = 'completed' AND NEW.difference != 0 THEN
                    RAISE EXCEPTION 'Cannot complete reconciliation with non-zero difference: %', NEW.difference;
                END IF;

                -- Check if previous reconciliation exists and is completed
                SELECT MAX(statement_date) INTO v_last_recon_date
                FROM bank.bank_reconciliations
                WHERE bank_account_id = NEW.bank_account_id
                AND status = 'completed'
                AND statement_date < NEW.statement_date;

                IF v_last_recon_date IS NOT NULL THEN
                    v_has_previous := true;
                END IF;

                IF NEW.status = 'completed' AND v_has_previous = false AND NEW.statement_date > CURRENT_DATE - INTERVAL '30 days' THEN
                    -- Allow completion if this is within last 30 days and no previous reconciliation
                    NULL;
                ELSIF NEW.status = 'completed' AND v_has_previous = true AND NEW.statement_date <= v_last_recon_date THEN
                    RAISE EXCEPTION 'Cannot complete reconciliation before previous period. Last reconciliation: %', v_last_recon_date;
                END IF;

                -- Update bank account last reconciled info when completing
                IF NEW.status = 'completed' AND (OLD.status IS NULL OR OLD.status != 'completed') THEN
                    UPDATE bank.company_bank_accounts
                    SET last_reconciled_date = NEW.statement_date,
                        last_reconciled_balance = NEW.statement_ending_balance
                    WHERE id = NEW.bank_account_id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER bank_reconciliations_biu
            BEFORE INSERT OR UPDATE ON bank.bank_reconciliations
            FOR EACH ROW EXECUTE FUNCTION bank.validate_reconciliation_completion();
        ");

        // Create function to auto-calculate difference
        DB::statement("
            CREATE OR REPLACE FUNCTION bank.calculate_reconciliation_difference()
            RETURNS trigger AS $$
            BEGIN
                NEW.difference := NEW.statement_ending_balance - NEW.reconciled_balance;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER bank_reconciliations_biu_calc
            BEFORE INSERT OR UPDATE ON bank.bank_reconciliations
            FOR EACH ROW EXECUTE FUNCTION bank.calculate_reconciliation_difference();
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS bank_reconciliations_biu_calc ON bank.bank_reconciliations');
        DB::statement('DROP FUNCTION IF EXISTS bank.calculate_reconciliation_difference()');
        DB::statement('DROP TRIGGER IF EXISTS bank_reconciliations_biu ON bank.bank_reconciliations');
        DB::statement('DROP FUNCTION IF EXISTS bank.validate_reconciliation_completion()');
        DB::statement('DROP POLICY IF EXISTS bank_reconciliations_policy ON bank.bank_reconciliations');
        Schema::dropIfExists('bank.bank_reconciliations');
    }
};