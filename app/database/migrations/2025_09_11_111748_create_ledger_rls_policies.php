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
        DB::statement('ALTER TABLE ledger.ledger_accounts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ledger.journal_entries ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ledger.journal_lines ENABLE ROW LEVEL SECURITY');

        // Create RLS policies for ledger_accounts
        DB::statement("
            CREATE POLICY company_isolation_ledger_accounts ON ledger.ledger_accounts
            FOR ALL TO PUBLIC
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid);
        ");

        // Create RLS policies for journal_entries
        DB::statement("
            CREATE POLICY company_isolation_journal_entries ON ledger.journal_entries
            FOR ALL TO PUBLIC
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid);
        ");

        // Create RLS policies for journal_lines
        DB::statement("
            CREATE POLICY company_isolation_journal_lines ON ledger.journal_lines
            FOR ALL TO PUBLIC
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid);
        ");

        // Create validation function for additional security
        DB::unprepared("
            CREATE OR REPLACE FUNCTION ledger.validate_company_access()
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
            BEFORE INSERT OR UPDATE OR DELETE ON ledger.ledger_accounts
            FOR EACH ROW EXECUTE FUNCTION ledger.validate_company_access();
        ');

        DB::unprepared('
            CREATE TRIGGER validate_journal_entries_access
            BEFORE INSERT OR UPDATE OR DELETE ON ledger.journal_entries
            FOR EACH ROW EXECUTE FUNCTION ledger.validate_company_access();
        ');

        DB::unprepared('
            CREATE TRIGGER validate_journal_lines_access
            BEFORE INSERT OR UPDATE OR DELETE ON ledger.journal_lines
            FOR EACH ROW EXECUTE FUNCTION ledger.validate_company_access();
        ');

        // Create company-scoped views for easier querying
        DB::unprepared("
            CREATE OR REPLACE VIEW ledger.company_ledger_accounts AS
            SELECT la.*
            FROM ledger.ledger_accounts la
            WHERE la.company_id = current_setting('app.current_company_id', true)::uuid;
        ");

        DB::unprepared("
            CREATE OR REPLACE VIEW ledger.company_journal_entries AS
            SELECT je.*
            FROM ledger.journal_entries je
            WHERE je.company_id = current_setting('app.current_company_id', true)::uuid;
        ");

        DB::unprepared("
            CREATE OR REPLACE VIEW ledger.company_journal_lines AS
            SELECT jl.*
            FROM ledger.journal_lines jl
            WHERE jl.company_id = current_setting('app.current_company_id', true)::uuid;
        ");

        // Set default permissions for views
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ledger.company_ledger_accounts TO PUBLIC');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ledger.company_journal_entries TO PUBLIC');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ledger.company_journal_lines TO PUBLIC');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers
        DB::unprepared('DROP TRIGGER IF EXISTS validate_ledger_accounts_access ON ledger.ledger_accounts');
        DB::unprepared('DROP TRIGGER IF EXISTS validate_journal_entries_access ON ledger.journal_entries');
        DB::unprepared('DROP TRIGGER IF EXISTS validate_journal_lines_access ON ledger.journal_lines');

        // Drop functions
        DB::unprepared('DROP FUNCTION IF EXISTS ledger.validate_company_access()');

        // Drop policies
        DB::unprepared('DROP POLICY IF EXISTS company_isolation_ledger_accounts ON ledger.ledger_accounts');
        DB::unprepared('DROP POLICY IF EXISTS company_isolation_journal_entries ON ledger.journal_entries');
        DB::unprepared('DROP POLICY IF EXISTS company_isolation_journal_lines ON ledger.journal_lines');

        // Drop views
        DB::unprepared('DROP VIEW IF EXISTS ledger.company_ledger_accounts');
        DB::unprepared('DROP VIEW IF EXISTS ledger.company_journal_entries');
        DB::unprepared('DROP VIEW IF EXISTS ledger.company_journal_lines');

        // Disable RLS
        DB::statement('ALTER TABLE ledger.ledger_accounts DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ledger.journal_entries DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ledger.journal_lines DISABLE ROW LEVEL SECURITY');
    }
};
