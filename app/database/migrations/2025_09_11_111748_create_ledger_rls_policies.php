<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable RLS on ledger tables
        DB::statement('ALTER TABLE acct.ledger_accounts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.journal_entries ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.journal_lines ENABLE ROW LEVEL SECURITY');

        // Create RLS policies for ledger_accounts
        $policyExists = DB::select("
            SELECT COUNT(*) as count
            FROM pg_policies
            WHERE tablename = 'ledger_accounts'
            AND policyname = 'company_isolation_ledger_accounts'
        ")[0]->count > 0;

        if (!$policyExists) {
            DB::statement("
                CREATE POLICY company_isolation_ledger_accounts ON acct.ledger_accounts
                FOR ALL TO PUBLIC
                USING (company_id = current_setting('app.current_company_id', true)::uuid)
                WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid);
            ");
        }

        // Create RLS policies for journal_entries
        $policyExists = DB::select("
            SELECT COUNT(*) as count
            FROM pg_policies
            WHERE tablename = 'journal_entries'
            AND policyname = 'company_isolation_journal_entries'
        ")[0]->count > 0;

        if (!$policyExists) {
            DB::statement("
                CREATE POLICY company_isolation_journal_entries ON acct.journal_entries
                FOR ALL TO PUBLIC
                USING (company_id = current_setting('app.current_company_id', true)::uuid)
                WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid);
            ");
        }

        // Create RLS policies for journal_lines
        $policyExists = DB::select("
            SELECT COUNT(*) as count
            FROM pg_policies
            WHERE tablename = 'journal_lines'
            AND policyname = 'company_isolation_journal_lines'
        ")[0]->count > 0;

        if (!$policyExists) {
            DB::statement("
                CREATE POLICY company_isolation_journal_lines ON acct.journal_lines
                FOR ALL TO PUBLIC
                USING (company_id = current_setting('app.current_company_id', true)::uuid)
                WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid);
            ");
        }

        // Create validation function for additional security
        DB::unprepared("
            CREATE OR REPLACE FUNCTION validate_company_access()
            RETURNS TRIGGER AS $$
            DECLARE
                current_company_id UUID;
            BEGIN
                -- Get current company from session context
                BEGIN
                    current_company_id := current_setting('app.current_company_id', true)::uuid;
                EXCEPTION WHEN OTHERS THEN
                    RAISE EXCEPTION 'Company context not set';
                END;

                -- Validate access
                IF TG_OP = 'INSERT' THEN
                    IF NEW.company_id != current_company_id THEN
                        RAISE EXCEPTION 'Cannot create records for other companies';
                    END IF;
                ELSIF TG_OP = 'UPDATE' THEN
                    IF OLD.company_id != current_company_id OR NEW.company_id != current_company_id THEN
                        RAISE EXCEPTION 'Cannot change company ownership';
                    END IF;
                ELSIF TG_OP = 'DELETE' THEN
                    IF OLD.company_id != current_company_id THEN
                        RAISE EXCEPTION 'Cannot delete records from other companies';
                    END IF;
                END IF;

                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql SECURITY DEFINER;
        ");

        // Add triggers for additional security
        DB::unprepared('
            CREATE TRIGGER validate_ledger_accounts_access
            BEFORE INSERT OR UPDATE OR DELETE ON acct.ledger_accounts
            FOR EACH ROW EXECUTE FUNCTION validate_company_access();
        ');

        DB::unprepared('
            CREATE TRIGGER validate_journal_entries_access
            BEFORE INSERT OR UPDATE OR DELETE ON acct.journal_entries
            FOR EACH ROW EXECUTE FUNCTION validate_company_access();
        ');

        DB::unprepared('
            CREATE TRIGGER validate_journal_lines_access
            BEFORE INSERT OR UPDATE OR DELETE ON acct.journal_lines
            FOR EACH ROW EXECUTE FUNCTION validate_company_access();
        ');

        // Create company-scoped views for easier querying
        DB::unprepared("
            CREATE OR REPLACE VIEW company_ledger_accounts AS
            SELECT la.*
            FROM acct.ledger_accounts la
            WHERE la.company_id = current_setting('app.current_company_id', true)::uuid;
        ");

        DB::unprepared("
            CREATE OR REPLACE VIEW company_journal_entries AS
            SELECT je.*
            FROM acct.journal_entries je
            WHERE je.company_id = current_setting('app.current_company_id', true)::uuid;
        ");

        DB::unprepared("
            CREATE OR REPLACE VIEW company_journal_lines AS
            SELECT jl.*
            FROM acct.journal_lines jl
            WHERE jl.company_id = current_setting('app.current_company_id', true)::uuid;
        ");

        // Set default permissions for views
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON company_ledger_accounts TO PUBLIC');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON company_journal_entries TO PUBLIC');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON company_journal_lines TO PUBLIC');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers
        DB::unprepared('DROP TRIGGER IF EXISTS validate_ledger_accounts_access ON acct.ledger_accounts');
        DB::unprepared('DROP TRIGGER IF EXISTS validate_journal_entries_access ON acct.journal_entries');
        DB::unprepared('DROP TRIGGER IF EXISTS validate_journal_lines_access ON acct.journal_lines');

        // Drop functions
        DB::unprepared('DROP FUNCTION IF EXISTS validate_company_access()');

        // Drop policies
        DB::unprepared('DROP POLICY IF EXISTS company_isolation_ledger_accounts ON acct.ledger_accounts');
        DB::unprepared('DROP POLICY IF EXISTS company_isolation_journal_entries ON acct.journal_entries');
        DB::unprepared('DROP POLICY IF EXISTS company_isolation_journal_lines ON acct.journal_lines');

        // Drop views
        DB::unprepared('DROP VIEW IF EXISTS company_ledger_accounts');
        DB::unprepared('DROP VIEW IF EXISTS company_journal_entries');
        DB::unprepared('DROP VIEW IF EXISTS company_journal_lines');

        // Disable RLS
        DB::statement('ALTER TABLE acct.ledger_accounts DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.journal_entries DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.journal_lines DISABLE ROW LEVEL SECURITY');
    }
};
