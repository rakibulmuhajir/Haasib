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
        // Drop existing tables if they exist
        DB::statement('DROP TABLE IF EXISTS journal_lines CASCADE');
        DB::statement('DROP TABLE IF EXISTS journal_entries CASCADE');
        DB::statement('DROP TABLE IF EXISTS ledger_accounts CASCADE');

        // Create ledger_accounts table
        DB::statement('
            CREATE TABLE ledger_accounts (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                company_id UUID NOT NULL,
                code VARCHAR(20) NOT NULL,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                normal_balance VARCHAR(10) DEFAULT \'debit\',
                active BOOLEAN DEFAULT true,
                system_account BOOLEAN DEFAULT false,
                description TEXT,
                parent_id UUID,
                level INTEGER DEFAULT 1,
                metadata JSON,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW(),
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW(),
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE
            )
        ');

        // Create journal_entries table
        DB::statement('
            CREATE TABLE journal_entries (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                company_id UUID NOT NULL,
                reference VARCHAR(255),
                date DATE NOT NULL,
                description VARCHAR(255) NOT NULL,
                status VARCHAR(50) DEFAULT \'draft\',
                total_debit DECIMAL(15,2) DEFAULT 0,
                total_credit DECIMAL(15,2) DEFAULT 0,
                source_type VARCHAR(255),
                source_id UUID,
                created_by_user_id UUID,
                posted_by_user_id UUID,
                posted_at TIMESTAMP(0) WITHOUT TIME ZONE,
                metadata JSON,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW(),
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW(),
                deleted_at TIMESTAMP(0) WITHOUT TIME ZONE
            )
        ');

        // Create journal_lines table
        DB::statement('
            CREATE TABLE journal_lines (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                company_id UUID NOT NULL,
                journal_entry_id UUID NOT NULL,
                ledger_account_id UUID NOT NULL,
                description VARCHAR(255),
                debit_amount DECIMAL(15,2) DEFAULT 0,
                credit_amount DECIMAL(15,2) DEFAULT 0,
                line_number INTEGER DEFAULT 1,
                metadata JSON,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW(),
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW()
            )
        ');

        // Create indexes
        DB::statement('CREATE UNIQUE INDEX ledger_accounts_company_id_code_unique ON ledger_accounts (company_id, code)');
        DB::statement('CREATE INDEX ledger_accounts_company_id_type_index ON ledger_accounts (company_id, type)');
        DB::statement('CREATE INDEX ledger_accounts_company_id_active_index ON ledger_accounts (company_id, active)');
        DB::statement('CREATE INDEX ledger_accounts_company_id_parent_id_index ON ledger_accounts (company_id, parent_id)');

        DB::statement('CREATE INDEX journal_entries_company_id_date_index ON journal_entries (company_id, date)');
        DB::statement('CREATE INDEX journal_entries_company_id_status_index ON journal_entries (company_id, status)');
        DB::statement('CREATE INDEX journal_entries_company_id_reference_index ON journal_entries (company_id, reference)');
        DB::statement('CREATE INDEX journal_entries_source_type_source_id_index ON journal_entries (source_type, source_id)');

        DB::statement('CREATE INDEX journal_lines_company_id_journal_entry_id_index ON journal_lines (company_id, journal_entry_id)');
        DB::statement('CREATE INDEX journal_lines_company_id_ledger_account_id_index ON journal_lines (company_id, ledger_account_id)');

        // Create foreign keys
        DB::statement('ALTER TABLE ledger_accounts ADD CONSTRAINT ledger_accounts_company_id_foreign FOREIGN KEY (company_id) REFERENCES auth.companies (id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE ledger_accounts ADD CONSTRAINT ledger_accounts_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES ledger_accounts (id) ON DELETE SET NULL');

        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_company_id_foreign FOREIGN KEY (company_id) REFERENCES auth.companies (id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_created_by_user_id_foreign FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_posted_by_user_id_foreign FOREIGN KEY (posted_by_user_id) REFERENCES users (id) ON DELETE SET NULL');

        DB::statement('ALTER TABLE acct.journal_lines ADD CONSTRAINT journal_lines_company_id_foreign FOREIGN KEY (company_id) REFERENCES auth.companies (id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE acct.journal_lines ADD CONSTRAINT journal_lines_journal_entry_id_foreign FOREIGN KEY (journal_entry_id) REFERENCES journal_entries (id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE acct.journal_lines ADD CONSTRAINT journal_lines_ledger_account_id_foreign FOREIGN KEY (ledger_account_id) REFERENCES ledger_accounts (id) ON DELETE RESTRICT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS journal_lines CASCADE');
        DB::statement('DROP TABLE IF EXISTS journal_entries CASCADE');
        DB::statement('DROP TABLE IF EXISTS ledger_accounts CASCADE');
    }
};
