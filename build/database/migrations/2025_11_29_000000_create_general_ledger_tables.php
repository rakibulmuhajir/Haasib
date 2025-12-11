<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * General Ledger Tables Migration
 *
 * Creates: accounts, fiscal_years, accounting_periods, transactions, journal_entries
 *
 * Dependency order:
 * 1. accounts (base table, self-referential parent_id)
 * 2. fiscal_years (references accounts for retained_earnings)
 * 3. accounting_periods (references fiscal_years)
 * 4. transactions (references accounting_periods, fiscal_years)
 * 5. journal_entries (references transactions, accounts)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        // 0. Global account templates (shared dropdown source)
        Schema::create('acct.account_templates', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->string('code', 50);
            $table->string('name', 255);
            $table->string('type', 30);
            $table->string('subtype', 50);
            $table->string('normal_balance', 6);
            $table->boolean('is_contra')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique('code');
            $table->index(['type', 'subtype']);
            $table->index('is_active');
        });

        DB::statement("
            ALTER TABLE acct.account_templates
            ADD CONSTRAINT account_templates_type_chk
            CHECK (type IN ('asset','liability','equity','revenue','expense','cogs','other_income','other_expense'))
        ");

        DB::statement("
            ALTER TABLE acct.account_templates
            ADD CONSTRAINT account_templates_normal_balance_chk
            CHECK (normal_balance IN ('debit','credit'))
        ");

        // Note: No type→normal_balance constraint. Contra accounts (is_contra=true) have
        // opposite normal_balance to their type. Validation is handled at application level.

        // 1. Chart of Accounts
        Schema::create('acct.accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('parent_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->string('type', 30);
            $table->string('subtype', 50);
            $table->string('normal_balance', 6);
            $table->char('currency', 3)->nullable();
            $table->boolean('is_contra')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->text('description')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('currency')->references('code')->on('public.currencies');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'code'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index(['company_id', 'type', 'subtype']);
            $table->index(['company_id', 'is_active']);
        });

        // Note: No type→normal_balance constraint on accounts. Contra accounts (is_contra=true)
        // have opposite normal_balance to their type. Validation is handled at application level.

        DB::statement("
            ALTER TABLE acct.accounts
            ADD CONSTRAINT accounts_normal_balance_chk
            CHECK (normal_balance IN ('debit','credit'))
        ");

        DB::statement("
            ALTER TABLE acct.accounts
            ADD CONSTRAINT accounts_currency_allowed_chk
            CHECK (
                currency IS NULL
                OR subtype IN ('bank','cash','accounts_receivable','accounts_payable','credit_card','other_current_asset','other_asset','other_current_liability','other_liability')
            )
        ");

        DB::statement("
            ALTER TABLE acct.accounts
            ADD CONSTRAINT accounts_parent_fk
            FOREIGN KEY (parent_id) REFERENCES acct.accounts(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ");

        DB::statement("ALTER TABLE acct.accounts ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY accounts_policy ON acct.accounts
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
            )
        ");

        // 2. Fiscal Years
        Schema::create('acct.fiscal_years', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->string('status', 20)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->uuid('closed_by_user_id')->nullable();
            $table->uuid('retained_earnings_account_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('closed_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('retained_earnings_account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'start_date']);
            $table->index('company_id');
            $table->index(['company_id', 'is_current']);
        });

        DB::statement("
            ALTER TABLE acct.fiscal_years
            ADD CONSTRAINT fiscal_years_date_chk
            CHECK (end_date > start_date)
        ");

        DB::statement("ALTER TABLE acct.fiscal_years ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY fiscal_years_policy ON acct.fiscal_years
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
            )
        ");

        // 3. Accounting Periods
        Schema::create('acct.accounting_periods', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('fiscal_year_id');
            $table->string('name', 100);
            $table->integer('period_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('period_type', 20)->default('monthly');
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_adjustment')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->uuid('closed_by_user_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('fiscal_year_id')->references('id')->on('acct.fiscal_years')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('closed_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['fiscal_year_id', 'period_number']);
            $table->unique(['company_id', 'start_date']);
            $table->index('company_id');
            $table->index('fiscal_year_id');
            $table->index(['company_id', 'is_closed']);
        });

        DB::statement("
            ALTER TABLE acct.accounting_periods
            ADD CONSTRAINT accounting_periods_date_chk
            CHECK (end_date > start_date)
        ");

        DB::statement("ALTER TABLE acct.accounting_periods ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY accounting_periods_policy ON acct.accounting_periods
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
            )
        ");

        // 4. Transactions
        Schema::create('acct.transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('transaction_number', 50);
            $table->string('transaction_type', 30);
            $table->string('reference_type', 100)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->date('transaction_date');
            $table->date('posting_date')->default(DB::raw('CURRENT_DATE'));
            $table->uuid('fiscal_year_id');
            $table->uuid('period_id');
            $table->text('description')->nullable();
            $table->char('currency', 3);
            $table->char('base_currency', 3);
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->decimal('total_debit', 15, 2)->default(0.00);
            $table->decimal('total_credit', 15, 2)->default(0.00);
            $table->string('status', 20)->default('draft');
            $table->uuid('reversal_of_id')->nullable();
            $table->uuid('reversed_by_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->uuid('posted_by_user_id')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->uuid('voided_by_user_id')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('fiscal_year_id')->references('id')->on('acct.fiscal_years')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('period_id')->references('id')->on('acct.accounting_periods')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('currency')->references('code')->on('public.currencies');
            $table->foreign('base_currency')->references('code')->on('public.currencies');
            $table->foreign('posted_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('voided_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'transaction_number'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index(['company_id', 'transaction_date']);
            $table->index(['company_id', 'status']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('fiscal_year_id');
            $table->index('period_id');
        });

        // Note: We do NOT add a CHECK constraint for transaction balance here because:
        // 1. PostgreSQL does not support DEFERRABLE CHECK constraints
        // 2. The trigger updates totals after each journal entry, causing intermediate unbalanced states
        // 3. Validation is done in GlPostingService before transaction commit instead
        // The trigger automatically maintains total_debit/total_credit for reporting purposes.

        // Self-referential foreign keys for reversal tracking
        Schema::table('acct.transactions', function (Blueprint $table) {
            $table->foreign('reversal_of_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('reversed_by_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
        });

        DB::statement("ALTER TABLE acct.transactions ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY transactions_policy ON acct.transactions
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
            )
        ");

        // Function to check period is open before posting
        DB::statement("
            CREATE OR REPLACE FUNCTION acct.check_period_open()
            RETURNS trigger AS \$\$
            DECLARE v_closed boolean;
            BEGIN
              SELECT is_closed INTO v_closed FROM acct.accounting_periods WHERE id = NEW.period_id;
              IF v_closed THEN
                RAISE EXCEPTION 'Cannot post to closed period %', NEW.period_id;
              END IF;
              RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER transactions_biu_period
            BEFORE INSERT OR UPDATE ON acct.transactions
            FOR EACH ROW
            EXECUTE FUNCTION acct.check_period_open();
        ");

        // 5. Journal Entries
        Schema::create('acct.journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('transaction_id');
            $table->uuid('account_id');
            $table->integer('line_number');
            $table->text('description')->nullable();
            $table->decimal('debit_amount', 15, 2)->default(0.00);
            $table->decimal('credit_amount', 15, 2)->default(0.00);
            $table->decimal('currency_debit', 18, 6)->nullable();
            $table->decimal('currency_credit', 18, 6)->nullable();
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('dimension_1', 100)->nullable();
            $table->string('dimension_2', 100)->nullable();
            $table->string('dimension_3', 100)->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('transaction_id')->references('id')->on('acct.transactions')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('account_id')->references('id')->on('acct.accounts')->restrictOnDelete()->cascadeOnUpdate();

            $table->unique(['transaction_id', 'line_number']);
            $table->index('company_id');
            $table->index('transaction_id');
            $table->index('account_id');
            $table->index(['company_id', 'account_id']);
        });

        DB::statement("
            ALTER TABLE acct.journal_entries
            ADD CONSTRAINT journal_entries_debit_credit_chk
            CHECK (
                (debit_amount > 0 AND credit_amount = 0)
                OR (credit_amount > 0 AND debit_amount = 0)
            )
        ");

        DB::statement("ALTER TABLE acct.journal_entries ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY journal_entries_policy ON acct.journal_entries
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
            )
        ");

        // Function to recompute transaction totals when journal entries change
        DB::statement("
            CREATE OR REPLACE FUNCTION acct.recompute_transaction_totals()
            RETURNS trigger AS \$\$
            BEGIN
              UPDATE acct.transactions t
              SET total_debit = COALESCE(s.sum_debit, 0),
                  total_credit = COALESCE(s.sum_credit, 0)
              FROM (
                SELECT transaction_id,
                       SUM(debit_amount) AS sum_debit,
                       SUM(credit_amount) AS sum_credit
                FROM acct.journal_entries
                WHERE transaction_id = COALESCE(NEW.transaction_id, OLD.transaction_id)
                GROUP BY transaction_id
              ) s
              WHERE t.id = COALESCE(NEW.transaction_id, OLD.transaction_id);
              RETURN NULL;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER journal_entries_aiud
            AFTER INSERT OR UPDATE OR DELETE ON acct.journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION acct.recompute_transaction_totals();
        ");
    }

    public function down(): void
    {
        // Drop in reverse order
        DB::statement('DROP TRIGGER IF EXISTS journal_entries_aiud ON acct.journal_entries');
        DB::statement('DROP FUNCTION IF EXISTS acct.recompute_transaction_totals()');
        Schema::dropIfExists('acct.journal_entries');

        DB::statement('DROP TRIGGER IF EXISTS transactions_biu_period ON acct.transactions');
        DB::statement('DROP FUNCTION IF EXISTS acct.check_period_open()');
        Schema::dropIfExists('acct.transactions');

        Schema::dropIfExists('acct.accounting_periods');
        Schema::dropIfExists('acct.fiscal_years');
        Schema::dropIfExists('acct.accounts');
        Schema::dropIfExists('acct.account_templates');
    }
};
