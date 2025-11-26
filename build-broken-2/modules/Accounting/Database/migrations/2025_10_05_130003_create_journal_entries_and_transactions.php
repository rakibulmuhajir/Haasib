<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acct.journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('generate_uuid()'));
            $table->uuid('company_id');
            $table->string('reference', 100); // e.g., "INV-2025-001", "PAY-2025-001"
            $table->string('description', 500);
            $table->date('date');
            $table->string('type', 50); // sales, purchase, payment, receipt, adjustment, closing
            $table->string('status', 20)->default('draft'); // draft, posted, void
            $table->uuid('created_by');
            $table->uuid('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->uuid('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 15, 8)->default(1);
            $table->uuid('fiscal_year_id');
            $table->uuid('accounting_period_id');
            $table->json('attachments')->nullable(); // Store file references
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('auth.users')
                ->onDelete('restrict');

            $table->foreign('posted_by')
                ->references('id')
                ->on('auth.users')
                ->onDelete('set null');

            $table->foreign('voided_by')
                ->references('id')
                ->on('auth.users')
                ->onDelete('set null');

            $table->foreign('fiscal_year_id')
                ->references('id')
                ->on('acct.fiscal_years')
                ->onDelete('restrict');

            $table->foreign('accounting_period_id')
                ->references('id')
                ->on('acct.accounting_periods')
                ->onDelete('restrict');

            $table->unique(['company_id', 'reference']);
            $table->index(['company_id', 'status', 'date']);
            $table->index(['company_id', 'type', 'date']);
            $table->index(['accounting_period_id', 'status']);
        });

        Schema::create('acct.journal_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('generate_uuid()'));
            $table->uuid('journal_entry_id');
            $table->uuid('account_id');
            $table->string('debit_credit', 10); // debit, credit
            $table->decimal('amount', 20, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 15, 8)->default(1);
            $table->text('description')->nullable();
            $table->uuid('reconcile_id')->nullable(); // For bank reconciliation
            $table->uuid('tax_code_id')->nullable(); // For tax tracking
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')
                ->references('id')
                ->on('acct.journal_entries')
                ->onDelete('cascade');

            $table->foreign('account_id')
                ->references('id')
                ->on('acct.accounts')
                ->onDelete('restrict');

            $table->index(['journal_entry_id']);
        });

        // Create functional indexes manually since Blueprint doesn't support expressions
        DB::statement('CREATE INDEX idx_journal_transactions_account_date ON acct.journal_transactions (account_id, date(created_at))');
        DB::statement('CREATE INDEX idx_journal_transactions_debit_credit_date ON acct.journal_transactions (debit_credit, date(created_at))');

        // Create view for trial balance
        DB::statement('
            CREATE OR REPLACE VIEW acct.trial_balance AS
            SELECT
                c.id AS company_id,
                a.id AS account_id,
                a.code,
                a.name AS account_name,
                ac.name AS account_class,
                SUM(CASE WHEN jt.debit_credit = \'debit\' THEN jt.amount ELSE 0 END) AS total_debits,
                SUM(CASE WHEN jt.debit_credit = \'credit\' THEN jt.amount ELSE 0 END) AS total_credits,
                SUM(CASE WHEN jt.debit_credit = \'debit\' THEN jt.amount ELSE -jt.amount END) AS balance
            FROM auth.companies c
            JOIN acct.accounts a ON a.company_id = c.id
            JOIN acct.account_groups ag ON ag.id = a.account_group_id
            JOIN acct.account_classes ac ON ac.id = ag.account_class_id
            LEFT JOIN acct.journal_transactions jt ON jt.account_id = a.id
            LEFT JOIN acct.journal_entries je ON je.id = jt.journal_entry_id AND je.status = \'posted\'
            GROUP BY c.id, a.id, a.code, a.name, ac.name
        ');

        // Enable RLS
        DB::statement('ALTER TABLE acct.journal_entries ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.journal_transactions ENABLE ROW LEVEL SECURITY');

        // Force RLS to ensure even table owner bypasses policies
        DB::statement('ALTER TABLE acct.journal_entries FORCE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.journal_transactions FORCE ROW LEVEL SECURITY');

        // Create RLS policies
        DB::statement('
            CREATE POLICY journal_entries_company_policy ON acct.journal_entries
            FOR ALL
            TO authenticated
            USING (company_id = acct.company_id_policy()::uuid)
            WITH CHECK (company_id = acct.company_id_policy()::uuid)
        ');

        DB::statement('
            CREATE POLICY journal_transactions_company_policy ON acct.journal_transactions
            FOR ALL
            TO authenticated
            USING (
                journal_entry_id IN (
                    SELECT id FROM acct.journal_entries
                    WHERE company_id = acct.company_id_policy()::uuid
                )
            )
            WITH CHECK (
                journal_entry_id IN (
                    SELECT id FROM acct.journal_entries
                    WHERE company_id = acct.company_id_policy()::uuid
                )
            )
        ');

        // Create triggers for posting validation
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.validate_journal_entry_posting()
            RETURNS trigger AS $$
            DECLARE
                total_debits DECIMAL;
                total_credits DECIMAL;
            BEGIN
                IF NEW.status = \'posted\' AND OLD.status != \'posted\' THEN
                    -- Check if debits equal credits
                    SELECT COALESCE(SUM(amount), 0) INTO total_debits
                    FROM acct.journal_transactions
                    WHERE journal_entry_id = NEW.id AND debit_credit = \'debit\';

                    SELECT COALESCE(SUM(amount), 0) INTO total_credits
                    FROM acct.journal_transactions
                    WHERE journal_entry_id = NEW.id AND debit_credit = \'credit\';

                    IF total_debits != total_credits THEN
                        RAISE EXCEPTION \'Journal entry must balance. Debits: %, Credits: %\', total_debits, total_credits;
                    END IF;

                    IF total_debits = 0 THEN
                        RAISE EXCEPTION \'Cannot post empty journal entry\';
                    END IF;

                    -- Set posted timestamp if not set
                    NEW.posted_at = COALESCE(NEW.posted_at, NOW());
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::statement('
            CREATE TRIGGER journal_entries_posting_validation
            BEFORE UPDATE ON acct.journal_entries
            FOR EACH ROW EXECUTE FUNCTION acct.validate_journal_entry_posting();
        ');

        // Create function to check if period is locked
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.is_period_locked(uuid)
            RETURNS boolean AS $$
            DECLARE
                locked_count INTEGER;
            BEGIN
                SELECT COUNT(*) INTO locked_count
                FROM acct.accounting_periods ap
                JOIN acct.fiscal_years fy ON fy.id = ap.fiscal_year_id
                WHERE ap.id = $1
                  AND (ap.status = \'closed\' OR ap.status = \'locked\' OR fy.is_locked = true);

                RETURN locked_count > 0;
            END;
            $$ LANGUAGE plpgsql STABLE;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers and functions
        DB::statement('DROP TRIGGER IF EXISTS journal_entries_posting_validation ON acct.journal_entries');
        DB::statement('DROP FUNCTION IF EXISTS acct.validate_journal_entry_posting()');
        DB::statement('DROP FUNCTION IF EXISTS acct.is_period_locked(uuid)');

        // Drop view
        DB::statement('DROP VIEW IF EXISTS acct.trial_balance');

        // Drop policies
        DB::statement('DROP POLICY IF EXISTS journal_entries_company_policy ON acct.journal_entries');
        DB::statement('DROP POLICY IF EXISTS journal_transactions_company_policy ON acct.journal_transactions');

        // Disable RLS
        DB::statement('ALTER TABLE acct.journal_entries DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.journal_transactions DISABLE ROW LEVEL SECURITY');

        // Drop tables
        Schema::dropIfExists('acct.journal_transactions');
        Schema::dropIfExists('acct.journal_entries');
    }
};
