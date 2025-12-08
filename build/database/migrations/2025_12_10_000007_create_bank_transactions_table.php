<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank.bank_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('bank_account_id');
            $table->uuid('reconciliation_id')->nullable();
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->text('description');
            $table->string('reference_number', 100)->nullable();
            $table->string('transaction_type', 30);
            $table->decimal('amount', 18, 6);
            $table->decimal('balance_after', 15, 2)->nullable();
            $table->string('payee_name', 255)->nullable();
            $table->string('category', 100)->nullable();
            $table->boolean('is_reconciled')->default(false);
            $table->date('reconciled_date')->nullable();
            $table->uuid('reconciled_by_user_id')->nullable();
            $table->uuid('matched_payment_id')->nullable();
            $table->uuid('matched_bill_payment_id')->nullable();
            $table->uuid('gl_transaction_id')->nullable();
            $table->string('source', 30)->default('manual');
            $table->string('external_id', 255)->nullable();
            $table->jsonb('raw_data')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('bank_account_id')->references('id')->on('bank.company_bank_accounts')->cascadeOnDelete()->cascadeOnUpdate();
            // $table->foreign('reconciliation_id')->references('id')->on('bank.bank_reconciliations')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('reconciled_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('matched_payment_id')->references('id')->on('acct.payments')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('matched_bill_payment_id')->references('id')->on('acct.bill_payments')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('gl_transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->index('company_id');
            $table->index('bank_account_id');
            $table->index(['bank_account_id', 'transaction_date']);
            $table->index(['company_id', 'is_reconciled']);
            $table->index('external_id');
        });

        DB::statement("ALTER TABLE bank.bank_transactions ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY bank_transactions_policy ON bank.bank_transactions
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Constraint: Can match to AR payment OR AP bill payment, not both
        DB::statement("
            ALTER TABLE bank.bank_transactions
            ADD CONSTRAINT bank_transactions_payment_match_chk
            CHECK (NOT (matched_payment_id IS NOT NULL AND matched_bill_payment_id IS NOT NULL))
        ");

        // Create trigger to update account balance when transactions change
        DB::statement("
            CREATE TRIGGER bank_transactions_aiud
            AFTER INSERT OR UPDATE OR DELETE ON bank.bank_transactions
            FOR EACH ROW EXECUTE FUNCTION bank.update_account_balance();
        ");

        // Create function to prevent deletion of reconciled transactions
        DB::statement("
            CREATE OR REPLACE FUNCTION bank.prevent_reconciled_deletion()
            RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND OLD.is_reconciled = true THEN
                    RAISE EXCEPTION 'Cannot delete reconciled bank transaction';
                END IF;
                RETURN OLD;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER bank_transactions_bd
            BEFORE DELETE ON bank.bank_transactions
            FOR EACH ROW EXECUTE FUNCTION bank.prevent_reconciled_deletion();
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS bank_transactions_bd ON bank.bank_transactions');
        DB::statement('DROP FUNCTION IF EXISTS bank.prevent_reconciled_deletion()');
        DB::statement('DROP TRIGGER IF EXISTS bank_transactions_aiud ON bank.bank_transactions');
        DB::statement('DROP POLICY IF EXISTS bank_transactions_policy ON bank.bank_transactions');
        Schema::dropIfExists('bank.bank_transactions');
    }
};