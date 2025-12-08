<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank.company_bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('bank_id')->nullable();
            $table->uuid('gl_account_id')->nullable();
            $table->string('account_name', 255);
            $table->string('account_number', 100);
            $table->string('account_type', 30)->default('checking');
            $table->char('currency', 3);
            $table->string('iban', 34)->nullable();
            $table->string('swift_code', 11)->nullable();
            $table->string('routing_number', 50)->nullable();
            $table->string('branch_name', 255)->nullable();
            $table->text('branch_address')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0.00);
            $table->date('opening_balance_date')->nullable();
            $table->decimal('current_balance', 15, 2)->default(0.00);
            $table->decimal('last_reconciled_balance', 15, 2)->nullable();
            $table->date('last_reconciled_date')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('bank_id')->references('id')->on('bank.banks')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('gl_account_id')->references('id')->on('acct.accounts')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'account_number'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'is_primary']);
        });

        DB::statement("ALTER TABLE bank.company_bank_accounts ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY company_bank_accounts_policy ON bank.company_bank_accounts
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Create function to ensure only one primary account per company
        DB::statement("
            CREATE OR REPLACE FUNCTION bank.check_single_primary_account()
            RETURNS trigger AS $$
            DECLARE
                v_count integer;
            BEGIN
                IF NEW.is_primary = true THEN
                    UPDATE bank.company_bank_accounts
                    SET is_primary = false
                    WHERE company_id = NEW.company_id
                    AND id != NEW.id
                    AND is_primary = true
                    AND deleted_at IS NULL;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER company_bank_accounts_biu_primary
            BEFORE INSERT OR UPDATE ON bank.company_bank_accounts
            FOR EACH ROW
            EXECUTE FUNCTION bank.check_single_primary_account();
        ");

        // Create function to update bank account balance
        DB::statement("
            CREATE OR REPLACE FUNCTION bank.update_account_balance()
            RETURNS trigger AS $$
            DECLARE
                v_new_balance numeric(15,2);
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    SELECT COALESCE(SUM(amount), 0) + opening_balance
                    INTO v_new_balance
                    FROM bank.company_bank_accounts a
                    LEFT JOIN bank.bank_transactions t ON t.bank_account_id = a.id AND t.deleted_at IS NULL
                    WHERE a.id = OLD.bank_account_id
                    GROUP BY a.opening_balance;

                    UPDATE bank.company_bank_accounts
                    SET current_balance = COALESCE(v_new_balance, opening_balance)
                    WHERE id = OLD.bank_account_id;
                ELSE
                    SELECT COALESCE(SUM(amount), 0) + opening_balance
                    INTO v_new_balance
                    FROM bank.company_bank_accounts a
                    LEFT JOIN bank.bank_transactions t ON t.bank_account_id = NEW.bank_account_id AND t.deleted_at IS NULL
                    WHERE a.id = NEW.bank_account_id
                    GROUP BY a.opening_balance;

                    UPDATE bank.company_bank_accounts
                    SET current_balance = COALESCE(v_new_balance, opening_balance)
                    WHERE id = NEW.bank_account_id;
                END IF;
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS company_bank_accounts_biu_primary ON bank.company_bank_accounts');
        DB::statement('DROP FUNCTION IF EXISTS bank.check_single_primary_account()');
        DB::statement('DROP FUNCTION IF EXISTS bank.update_account_balance()');
        DB::statement('DROP POLICY IF EXISTS company_bank_accounts_policy ON bank.company_bank_accounts');
        Schema::dropIfExists('bank.company_bank_accounts');
    }
};