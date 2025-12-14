<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Banking Tables Migration
 *
 * Creates: banks, company_bank_accounts, bank_reconciliations, bank_transactions, bank_rules
 *
 * NOTE: All tables now use acct.* schema instead of bank.* schema
 *
 * Dependency order:
 * 1. banks (reference data, no company FK)
 * 2. company_bank_accounts (references banks, acct.accounts)
 * 3. bank_reconciliations (references company_bank_accounts)
 * 4. bank_transactions (references company_bank_accounts, bank_reconciliations)
 * 5. bank_rules (references company_bank_accounts)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Banks (reference data)
        Schema::create('acct.banks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->string('name', 255);
            $table->string('swift_code', 11)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('website', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('swift_code');
            $table->index('country_code');
        });

        // Seed with common banks
        DB::table('acct.banks')->insert([
            [
                'name' => 'National Commercial Bank',
                'swift_code' => 'NCBKSAJE',
                'country_code' => 'SA',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Riyad Bank',
                'swift_code' => 'RIBLSARI',
                'country_code' => 'SA',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Saudi British Bank (SABB)',
                'swift_code' => 'SABBSARI',
                'country_code' => 'SA',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Alinma Bank',
                'swift_code' => 'ALMASARI',
                'country_code' => 'SA',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // 2. Company Bank Accounts
        Schema::create('acct.company_bank_accounts', function (Blueprint $table) {
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
            $table->foreign('bank_id')->references('id')->on('acct.banks')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('gl_account_id')->references('id')->on('acct.accounts')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'account_number'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'is_primary']);
        });

        DB::statement("ALTER TABLE acct.company_bank_accounts ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY company_bank_accounts_policy ON acct.company_bank_accounts
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Function to ensure only one primary account per company
        DB::statement("
            CREATE OR REPLACE FUNCTION acct.check_single_primary_account()
            RETURNS trigger AS \$\$
            DECLARE
                v_count integer;
            BEGIN
                IF NEW.is_primary = true THEN
                    UPDATE acct.company_bank_accounts
                    SET is_primary = false
                    WHERE company_id = NEW.company_id
                    AND id != NEW.id
                    AND is_primary = true
                    AND deleted_at IS NULL;
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER company_bank_accounts_biu_primary
            BEFORE INSERT OR UPDATE ON acct.company_bank_accounts
            FOR EACH ROW
            EXECUTE FUNCTION acct.check_single_primary_account();
        ");

        // Function to update bank account balance
        DB::statement("
            CREATE OR REPLACE FUNCTION acct.update_account_balance()
            RETURNS trigger AS \$\$
            DECLARE
                v_new_balance numeric(15,2);
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    SELECT COALESCE(SUM(amount), 0) + opening_balance
                    INTO v_new_balance
                    FROM acct.company_bank_accounts a
                    LEFT JOIN acct.bank_transactions t ON t.bank_account_id = a.id AND t.deleted_at IS NULL
                    WHERE a.id = OLD.bank_account_id
                    GROUP BY a.opening_balance;

                    UPDATE acct.company_bank_accounts
                    SET current_balance = COALESCE(v_new_balance, opening_balance)
                    WHERE id = OLD.bank_account_id;
                ELSE
                    SELECT COALESCE(SUM(amount), 0) + opening_balance
                    INTO v_new_balance
                    FROM acct.company_bank_accounts a
                    LEFT JOIN acct.bank_transactions t ON t.bank_account_id = NEW.bank_account_id AND t.deleted_at IS NULL
                    WHERE a.id = NEW.bank_account_id
                    GROUP BY a.opening_balance;

                    UPDATE acct.company_bank_accounts
                    SET current_balance = COALESCE(v_new_balance, opening_balance)
                    WHERE id = NEW.bank_account_id;
                END IF;
                RETURN COALESCE(NEW, OLD);
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // 3. Bank Reconciliations
        Schema::create('acct.bank_reconciliations', function (Blueprint $table) {
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
            $table->foreign('bank_account_id')->references('id')->on('acct.company_bank_accounts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('completed_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['bank_account_id', 'statement_date']);
            $table->index('company_id');
            $table->index('bank_account_id');
            $table->index(['company_id', 'status']);
        });

        DB::statement("ALTER TABLE acct.bank_reconciliations ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY bank_reconciliations_policy ON acct.bank_reconciliations
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Function to validate reconciliation completion
        DB::statement("
            CREATE OR REPLACE FUNCTION acct.validate_reconciliation_completion()
            RETURNS trigger AS \$\$
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
                FROM acct.bank_reconciliations
                WHERE bank_account_id = NEW.bank_account_id
                AND status = 'completed'
                AND statement_date < NEW.statement_date;

                IF v_last_recon_date IS NOT NULL THEN
                    v_has_previous := true;
                END IF;

                IF NEW.status = 'completed' AND v_has_previous = false AND NEW.statement_date > CURRENT_DATE - INTERVAL '30 days' THEN
                    NULL;
                ELSIF NEW.status = 'completed' AND v_has_previous = true AND NEW.statement_date <= v_last_recon_date THEN
                    RAISE EXCEPTION 'Cannot complete reconciliation before previous period. Last reconciliation: %', v_last_recon_date;
                END IF;

                -- Update bank account last reconciled info when completing
                IF NEW.status = 'completed' AND (OLD.status IS NULL OR OLD.status != 'completed') THEN
                    UPDATE acct.company_bank_accounts
                    SET last_reconciled_date = NEW.statement_date,
                        last_reconciled_balance = NEW.statement_ending_balance
                    WHERE id = NEW.bank_account_id;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER bank_reconciliations_biu
            BEFORE INSERT OR UPDATE ON acct.bank_reconciliations
            FOR EACH ROW EXECUTE FUNCTION acct.validate_reconciliation_completion();
        ");

        // Function to auto-calculate difference
        DB::statement("
            CREATE OR REPLACE FUNCTION acct.calculate_reconciliation_difference()
            RETURNS trigger AS \$\$
            BEGIN
                NEW.difference := NEW.statement_ending_balance - NEW.reconciled_balance;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER bank_reconciliations_biu_calc
            BEFORE INSERT OR UPDATE ON acct.bank_reconciliations
            FOR EACH ROW EXECUTE FUNCTION acct.calculate_reconciliation_difference();
        ");

        // 4. Bank Transactions
        Schema::create('acct.bank_transactions', function (Blueprint $table) {
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
            $table->foreign('bank_account_id')->references('id')->on('acct.company_bank_accounts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('reconciliation_id')->references('id')->on('acct.bank_reconciliations')->nullOnDelete()->cascadeOnUpdate();
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

        DB::statement("ALTER TABLE acct.bank_transactions ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY bank_transactions_policy ON acct.bank_transactions
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Constraint: Can match to AR payment OR AP bill payment, not both
        DB::statement("
            ALTER TABLE acct.bank_transactions
            ADD CONSTRAINT bank_transactions_payment_match_chk
            CHECK (NOT (matched_payment_id IS NOT NULL AND matched_bill_payment_id IS NOT NULL))
        ");

        // Trigger to update account balance when transactions change
        DB::statement("
            CREATE TRIGGER bank_transactions_aiud
            AFTER INSERT OR UPDATE OR DELETE ON acct.bank_transactions
            FOR EACH ROW EXECUTE FUNCTION acct.update_account_balance();
        ");

        // Function to prevent deletion of reconciled transactions
        DB::statement("
            CREATE OR REPLACE FUNCTION acct.prevent_reconciled_deletion()
            RETURNS trigger AS \$\$
            BEGIN
                IF TG_OP = 'DELETE' AND OLD.is_reconciled = true THEN
                    RAISE EXCEPTION 'Cannot delete reconciled bank transaction';
                END IF;
                RETURN OLD;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER bank_transactions_bd
            BEFORE DELETE ON acct.bank_transactions
            FOR EACH ROW EXECUTE FUNCTION acct.prevent_reconciled_deletion();
        ");

        // 5. Bank Rules
        Schema::create('acct.bank_rules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('bank_account_id')->nullable();
            $table->string('name', 255);
            $table->integer('priority')->default(0);
            $table->jsonb('conditions');
            $table->jsonb('actions');
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('bank_account_id')->references('id')->on('acct.company_bank_accounts')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->index('company_id');
            $table->index(['company_id', 'is_active', 'priority']);
        });

        DB::statement("ALTER TABLE acct.bank_rules ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY bank_rules_policy ON acct.bank_rules
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        DB::statement("
            ALTER TABLE acct.bank_rules
            ADD CONSTRAINT bank_rules_conditions_valid_chk
            CHECK (jsonb_typeof(conditions) = 'object' AND conditions IS NOT NULL)
        ");

        DB::statement("
            ALTER TABLE acct.bank_rules
            ADD CONSTRAINT bank_rules_actions_valid_chk
            CHECK (jsonb_typeof(actions) = 'object' AND actions IS NOT NULL)
        ");
    }

    public function down(): void
    {
        // Drop in reverse order
        DB::statement('DROP POLICY IF EXISTS bank_rules_policy ON acct.bank_rules');
        Schema::dropIfExists('acct.bank_rules');

        DB::statement('DROP TRIGGER IF EXISTS bank_transactions_bd ON acct.bank_transactions');
        DB::statement('DROP FUNCTION IF EXISTS acct.prevent_reconciled_deletion()');
        DB::statement('DROP TRIGGER IF EXISTS bank_transactions_aiud ON acct.bank_transactions');
        DB::statement('DROP POLICY IF EXISTS bank_transactions_policy ON acct.bank_transactions');
        Schema::dropIfExists('acct.bank_transactions');

        DB::statement('DROP TRIGGER IF EXISTS bank_reconciliations_biu_calc ON acct.bank_reconciliations');
        DB::statement('DROP FUNCTION IF EXISTS acct.calculate_reconciliation_difference()');
        DB::statement('DROP TRIGGER IF EXISTS bank_reconciliations_biu ON acct.bank_reconciliations');
        DB::statement('DROP FUNCTION IF EXISTS acct.validate_reconciliation_completion()');
        DB::statement('DROP POLICY IF EXISTS bank_reconciliations_policy ON acct.bank_reconciliations');
        Schema::dropIfExists('acct.bank_reconciliations');

        DB::statement('DROP TRIGGER IF EXISTS company_bank_accounts_biu_primary ON acct.company_bank_accounts');
        DB::statement('DROP FUNCTION IF EXISTS acct.check_single_primary_account()');
        DB::statement('DROP FUNCTION IF EXISTS acct.update_account_balance()');
        DB::statement('DROP POLICY IF EXISTS company_bank_accounts_policy ON acct.company_bank_accounts');
        Schema::dropIfExists('acct.company_bank_accounts');

        Schema::dropIfExists('acct.banks');
    }
};
