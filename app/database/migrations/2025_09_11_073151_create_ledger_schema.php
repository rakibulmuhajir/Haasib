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
        // Create accounting schema if it doesn't exist
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        // Create ledger_accounts table
        Schema::create('acct.ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->notNullable();
            $table->string('code', 20)->notNullable(); // e.g., "1001", "2001"
            $table->string('name')->notNullable(); // e.g., "Cash", "Accounts Receivable"
            $table->string('type')->notNullable(); // asset, liability, equity, revenue, expense
            $table->string('normal_balance')->default('debit'); // debit or credit
            $table->boolean('active')->default(true);
            $table->boolean('system_account')->default(false); // Cannot be deleted
            $table->text('description')->nullable();
            $table->uuid('parent_id')->nullable(); // For hierarchical accounts
            $table->integer('level')->default(1); // For hierarchical display
            $table->json('metadata')->nullable(); // For additional properties
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['company_id', 'code']); // Unique account code per company
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'active']);
            $table->index(['company_id', 'parent_id']);
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            // Note: parent_id foreign key will be added after table creation to avoid self-reference issues
        });

        // Add self-referencing foreign key for parent_id
        DB::statement('ALTER TABLE acct.ledger_accounts ADD CONSTRAINT fk_ledger_accounts_parent_id FOREIGN KEY (parent_id) REFERENCES acct.ledger_accounts(id) ON DELETE SET NULL');

        // Create journal_entries table
        Schema::create('acct.journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->notNullable();
            $table->string('reference')->nullable(); // e.g., "INV-2025-001", "JE-2025-001"
            $table->date('date')->notNullable();
            $table->string('description')->notNullable();
            $table->string('status')->default('draft'); // draft, posted, void
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->string('source_type')->nullable(); // e.g., "invoice", "payment", "manual"
            $table->uuid('source_id')->nullable(); // Reference to source document
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('posted_by_user_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['company_id', 'date']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'reference']);
            $table->index(['source_type', 'source_id']);
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->onDelete('set null');
            $table->foreign('posted_by_user_id')->references('id')->on('auth.users')->onDelete('set null');
        });

        // Create journal_lines table
        Schema::create('acct.journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->notNullable();
            $table->uuid('journal_entry_id')->notNullable();
            $table->uuid('ledger_account_id')->notNullable();
            $table->string('description')->nullable();
            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->integer('line_number')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['company_id', 'journal_entry_id']);
            $table->index(['company_id', 'ledger_account_id']);
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('journal_entry_id')->references('id')->on('acct.journal_entries')->onDelete('cascade');
            $table->foreign('ledger_account_id')->references('id')->on('acct.ledger_accounts')->onDelete('restrict');
        });

        // Create balance constraint trigger function
        DB::unprepared('
            CREATE OR REPLACE FUNCTION check_journal_entry_balance()
            RETURNS TRIGGER AS $$
            DECLARE
                total_debit DECIMAL(15,2);
                total_credit DECIMAL(15,2);
            BEGIN
                IF NEW.status = \'posted\' THEN
                    SELECT COALESCE(SUM(debit_amount), 0) INTO total_debit FROM acct.journal_lines
                    WHERE journal_entry_id = NEW.id;

                    SELECT COALESCE(SUM(credit_amount), 0) INTO total_credit FROM acct.journal_lines
                    WHERE journal_entry_id = NEW.id;

                    IF total_debit != total_credit THEN
                        RAISE EXCEPTION \'Journal entry must balance. Debits: %, Credits: %\', total_debit, total_credit;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Create trigger for balance validation
        DB::unprepared('
            CREATE TRIGGER validate_journal_entry_balance
            BEFORE UPDATE ON acct.journal_entries
            FOR EACH ROW
            EXECUTE FUNCTION check_journal_entry_balance();
        ');

        // Create trigger function to recompute totals on the journal entry header
        DB::unprepared('
            CREATE OR REPLACE FUNCTION recompute_journal_entry_totals()
            RETURNS TRIGGER AS $$
            DECLARE
                entry_id UUID;
            BEGIN
                entry_id := COALESCE(NEW.journal_entry_id, OLD.journal_entry_id);

                UPDATE acct.journal_entries
                SET
                    total_debit = (SELECT COALESCE(SUM(debit_amount), 0) FROM acct.journal_lines WHERE journal_entry_id = entry_id),
                    total_credit = (SELECT COALESCE(SUM(credit_amount), 0) FROM acct.journal_lines WHERE journal_entry_id = entry_id)
                WHERE id = entry_id;

                RETURN NULL; -- result is ignored since this is an AFTER trigger
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Attach the recomputation trigger to the journal_lines table
        DB::unprepared('
            CREATE TRIGGER update_journal_entry_totals
            AFTER INSERT OR UPDATE OR DELETE ON acct.journal_lines
            FOR EACH ROW
            EXECUTE FUNCTION recompute_journal_entry_totals();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS update_journal_entry_totals ON acct.journal_lines');
        DB::unprepared('DROP FUNCTION IF EXISTS recompute_journal_entry_totals()');
        DB::unprepared('DROP TRIGGER IF EXISTS validate_journal_entry_balance ON acct.journal_entries');
        DB::unprepared('DROP FUNCTION IF EXISTS check_journal_entry_balance()');

        DB::statement('ALTER TABLE acct.ledger_accounts DROP CONSTRAINT IF EXISTS fk_ledger_accounts_parent_id');

        // Drop tables
        Schema::dropIfExists('acct.journal_lines');
        Schema::dropIfExists('acct.journal_entries');
        Schema::dropIfExists('acct.ledger_accounts');
    }
};
