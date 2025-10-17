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
        // Find the existing accounting_periods table
        if (! Schema::hasTable('acct.accounting_periods')) {
            return;
        }

        // Extend the status enum to include new statuses
        DB::statement('
            ALTER TABLE acct.accounting_periods 
            DROP CONSTRAINT IF EXISTS accounting_periods_status_check
        ');

        DB::statement("
            ALTER TABLE acct.accounting_periods 
            ADD CONSTRAINT accounting_periods_status_check 
            CHECK (status IN ('future', 'open', 'closing', 'closed', 'reopened'))
        ");

        // Add new columns for reopening functionality
        Schema::table('acct.accounting_periods', function (Blueprint $table) {
            if (! Schema::hasColumn('acct.accounting_periods', 'reopened_by')) {
                $table->uuid('reopened_by')->nullable()->after('closed_by');
                $table->foreign('reopened_by')
                    ->references('id')->on('auth.users')
                    ->onDelete('set null');
            }

            if (! Schema::hasColumn('acct.accounting_periods', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable()->after('reopened_by');
            }

            if (! Schema::hasColumn('acct.accounting_periods', 'closing_notes')) {
                $table->text('closing_notes')->nullable()->after('closed_at');
            }
        });

        // Create trigger to prevent journal entries in closed periods
        DB::statement('
            DROP TRIGGER IF EXISTS prevent_journal_entries_in_closed_periods ON acct.journal_entries;
        ');

        DB::statement('
            CREATE OR REPLACE FUNCTION prevent_journal_entries_in_closed_periods()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Check if the period is closed and we are inserting/updating a journal entry
                IF EXISTS (
                    SELECT 1 FROM acct.accounting_periods 
                    WHERE id = NEW.accounting_period_id 
                    AND status = \'closed\'
                    AND company_id = current_setting(\'app.current_company_id\', true)::uuid
                ) THEN
                    RAISE EXCEPTION \'Cannot create journal entry: period is closed. Period must be reopened first to make changes\';
                END IF;

                -- Additional check: prevent entries after period end date (for open periods)
                IF EXISTS (
                    SELECT 1 FROM acct.accounting_periods 
                    WHERE id = NEW.accounting_period_id 
                    AND NEW.entry_date > end_date
                    AND status IN (\'open\', \'closing\')
                    AND company_id = current_setting(\'app.current_company_id\', true)::uuid
                ) THEN
                    RAISE EXCEPTION \'Journal entry date cannot be after period end date\';
                END IF;

                -- Prevent modifications to existing entries in closed periods
                IF TG_OP = \'UPDATE\' AND OLD.accounting_period_id IS NOT NULL THEN
                    IF EXISTS (
                        SELECT 1 FROM acct.accounting_periods 
                        WHERE id = OLD.accounting_period_id 
                        AND status = \'closed\'
                        AND company_id = current_setting(\'app.current_company_id\', true)::uuid
                    ) THEN
                        RAISE EXCEPTION \'Cannot modify journal entry: period is closed. Period must be reopened first to make changes\';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;
        ');

        DB::statement('
            CREATE TRIGGER prevent_journal_entries_in_closed_periods
                BEFORE INSERT OR UPDATE ON acct.journal_entries
                FOR EACH ROW
                EXECUTE FUNCTION prevent_journal_entries_in_closed_periods();
        ');

        // Create trigger to prevent journal entry deletions in closed periods
        DB::statement('
            DROP TRIGGER IF EXISTS prevent_journal_entry_deletions_in_closed_periods ON acct.journal_entries;
        ');

        DB::statement('
            CREATE OR REPLACE FUNCTION prevent_journal_entry_deletions_in_closed_periods()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Check if the period is closed and we are deleting a journal entry
                IF EXISTS (
                    SELECT 1 FROM acct.accounting_periods 
                    WHERE id = OLD.accounting_period_id 
                    AND status = \'closed\'
                    AND company_id = current_setting(\'app.current_company_id\', true)::uuid
                ) THEN
                    RAISE EXCEPTION \'Cannot delete journal entry: period is closed. Period must be reopened first to delete entries\';
                END IF;

                RETURN OLD;
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;
        ');

        DB::statement('
            CREATE TRIGGER prevent_journal_entry_deletions_in_closed_periods
                BEFORE DELETE ON acct.journal_entries
                FOR EACH ROW
                EXECUTE FUNCTION prevent_journal_entry_deletions_in_closed_periods();
        ');

        // Create trigger to prevent payment modifications in closed periods
        DB::statement('
            DROP TRIGGER IF EXISTS prevent_payment_modifications_in_closed_periods ON acct.payments;
        ');

        DB::statement('
            CREATE OR REPLACE FUNCTION prevent_payment_modifications_in_closed_periods()
            RETURNS TRIGGER AS $$
            DECLARE
                period_status TEXT;
            BEGIN
                -- Check if the payment is linked to an invoice in a closed period
                SELECT ap.status INTO period_status
                FROM acct.accounting_periods ap
                JOIN acct.invoices i ON i.accounting_period_id = ap.id
                WHERE i.id = NEW.invoice_id
                AND ap.company_id = current_setting(\'app.current_company_id\', true)::uuid;

                IF period_status = \'closed\' THEN
                    RAISE EXCEPTION \'Cannot modify payments for invoices in closed periods. Period must be reopened first\';
                END IF;

                -- Additional check: prevent payment modifications after invoice period end date
                IF EXISTS (
                    SELECT 1 FROM acct.accounting_periods ap
                    JOIN acct.invoices i ON i.accounting_period_id = ap.id
                    WHERE i.id = NEW.invoice_id
                    AND NEW.payment_date > ap.end_date
                    AND ap.status IN (\'open\', \'closing\')
                    AND ap.company_id = current_setting(\'app.current_company_id\', true)::uuid
                ) THEN
                    RAISE EXCEPTION \'Payment date cannot be after period end date\';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;
        ');

        DB::statement('
            CREATE TRIGGER prevent_payment_modifications_in_closed_periods
                BEFORE INSERT OR UPDATE ON acct.payments
                FOR EACH ROW
                EXECUTE FUNCTION prevent_payment_modifications_in_closed_periods();
        ');

        // Create trigger to prevent payment deletions in closed periods
        DB::statement('
            DROP TRIGGER IF EXISTS prevent_payment_deletions_in_closed_periods ON acct.payments;
        ');

        DB::statement('
            CREATE OR REPLACE FUNCTION prevent_payment_deletions_in_closed_periods()
            RETURNS TRIGGER AS $$
            DECLARE
                period_status TEXT;
            BEGIN
                -- Check if the payment is linked to an invoice in a closed period
                SELECT ap.status INTO period_status
                FROM acct.accounting_periods ap
                JOIN acct.invoices i ON i.accounting_period_id = ap.id
                WHERE i.id = OLD.invoice_id
                AND ap.company_id = current_setting(\'app.current_company_id\', true)::uuid;

                IF period_status = \'closed\' THEN
                    RAISE EXCEPTION \'Cannot delete payments for invoices in closed periods. Period must be reopened first\';
                END IF;

                RETURN OLD;
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;
        ');

        DB::statement('
            CREATE TRIGGER prevent_payment_deletions_in_closed_periods
                BEFORE DELETE ON acct.payments
                FOR EACH ROW
                EXECUTE FUNCTION prevent_payment_deletions_in_closed_periods();
        ');

        // Create function to check if period can be closed
        DB::statement('
            CREATE OR REPLACE FUNCTION can_close_period(
                p_period_id UUID,
                p_company_id UUID DEFAULT NULL
            ) RETURNS BOOLEAN AS $$
            DECLARE
                v_company_id UUID := COALESCE(p_company_id, current_setting(\'app.current_company_id\', true)::uuid);
                v_period_status TEXT;
                v_unclosed_invoices INTEGER := 0;
                v_unposted_entries INTEGER := 0;
                v_open_payments INTEGER := 0;
            BEGIN
                -- Check period status
                SELECT status INTO v_period_status
                FROM acct.accounting_periods
                WHERE id = p_period_id AND company_id = v_company_id;
                
                IF v_period_status NOT IN (\'open\', \'reopened\') THEN
                    RETURN FALSE;
                END IF;

                -- Check for unposted invoices
                SELECT COUNT(*) INTO v_unclosed_invoices
                FROM acct.invoices
                WHERE accounting_period_id = p_period_id
                AND status NOT IN (\'posted\', \'paid\', \'void\')
                AND company_id = v_company_id;

                IF v_unclosed_invoices > 0 THEN
                    RETURN FALSE;
                END IF;

                -- Check for draft journal entries
                SELECT COUNT(*) INTO v_unposted_entries
                FROM acct.journal_entries
                WHERE accounting_period_id = p_period_id
                AND status != \'posted\'
                AND company_id = v_company_id;

                IF v_unposted_entries > 0 THEN
                    RETURN FALSE;
                END IF;

                -- Check for incomplete payment allocations
                SELECT COUNT(*) INTO v_open_payments
                FROM acct.payments p
                JOIN acct.invoices i ON p.invoice_id = i.id
                WHERE i.accounting_period_id = p_period_id
                AND p.status = \'pending\'
                AND p.company_id = v_company_id;

                IF v_open_payments > 0 THEN
                    RETURN FALSE;
                END IF;

                RETURN TRUE;
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;
        ');

        // Create audit trigger for accounting period status changes
        DB::statement('
            CREATE OR REPLACE FUNCTION audit_accounting_period_status_change()
            RETURNS TRIGGER AS $$
            BEGIN
                IF OLD.status IS DISTINCT FROM NEW.status THEN
                    INSERT INTO audit.audit_logs (action_type, details, user_id, table_name, record_id, company_id) VALUES (
                        \'accounting_period_status_changed\'::TEXT,
                        jsonb_build_object(
                            \'period_id\', NEW.id,
                            \'old_status\', OLD.status,
                            \'new_status\', NEW.status,
                            \'changed_by\', current_setting(\'app.current_user_id\', true)::uuid,
                            \'changed_at\', NOW()
                        ),
                        current_setting(\'app.current_user_id\', true)::uuid,
                        \'acct.accounting_periods\'::TEXT,
                        NEW.id,
                        NEW.company_id
                    );
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;
        ');

        DB::statement('
            DROP TRIGGER IF EXISTS audit_accounting_period_status_change ON acct.accounting_periods;
        ');

        DB::statement('
            CREATE TRIGGER audit_accounting_period_status_change
                AFTER UPDATE ON acct.accounting_periods
                FOR EACH ROW
                EXECUTE FUNCTION audit_accounting_period_status_change();
        ');

        // Create index for period status lookups
        if (! Schema::hasIndex('acct.accounting_periods', ['company_id', 'status'])) {
            Schema::table('acct.accounting_periods', function (Blueprint $table) {
                $table->index(['company_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers
        DB::statement('DROP TRIGGER IF EXISTS audit_accounting_period_status_change ON acct.accounting_periods');
        DB::statement('DROP TRIGGER IF EXISTS prevent_payment_modifications_in_closed_periods ON acct.payments');
        DB::statement('DROP TRIGGER IF EXISTS prevent_payment_deletions_in_closed_periods ON acct.payments');
        DB::statement('DROP TRIGGER IF EXISTS prevent_journal_entries_in_closed_periods ON acct.journal_entries');
        DB::statement('DROP TRIGGER IF EXISTS prevent_journal_entry_deletions_in_closed_periods ON acct.journal_entries');

        // Drop functions
        DB::statement('DROP FUNCTION IF EXISTS can_close_period(uuid, uuid)');
        DB::statement('DROP FUNCTION IF EXISTS prevent_payment_modifications_in_closed_periods()');
        DB::statement('DROP FUNCTION IF EXISTS prevent_payment_deletions_in_closed_periods()');
        DB::statement('DROP FUNCTION IF EXISTS prevent_journal_entries_in_closed_periods()');
        DB::statement('DROP FUNCTION IF EXISTS prevent_journal_entry_deletions_in_closed_periods()');
        DB::statement('DROP FUNCTION IF EXISTS audit_accounting_period_status_change()');

        // Remove columns
        Schema::table('acct.accounting_periods', function (Blueprint $table) {
            if (Schema::hasColumn('acct.accounting_periods', 'reopened_by')) {
                $table->dropForeign(['reopened_by']);
                $table->dropColumn('reopened_by');
            }

            if (Schema::hasColumn('acct.accounting_periods', 'reopened_at')) {
                $table->dropColumn('reopened_at');
            }

            if (Schema::hasColumn('acct.accounting_periods', 'closing_notes')) {
                $table->dropColumn('closing_notes');
            }
        });

        // Revert status enum (remove new values)
        DB::statement('
            ALTER TABLE acct.accounting_periods 
            DROP CONSTRAINT IF EXISTS accounting_periods_status_check
        ');

        DB::statement("
            ALTER TABLE acct.accounting_periods 
            ADD CONSTRAINT accounting_periods_status_check 
            CHECK (status IN (\'future\', \'open\', \'closed\'))
        ");
    }
};
