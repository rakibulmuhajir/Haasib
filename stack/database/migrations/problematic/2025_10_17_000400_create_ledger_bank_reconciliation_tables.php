<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create schema if not exists
        DB::statement('CREATE SCHEMA IF NOT EXISTS ledger');

        Schema::create('ledger.bank_reconciliations', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Tenant Isolation
            $table->uuid('company_id');

            // Business Data
            $table->uuid('statement_id');
            $table->uuid('ledger_account_id');
            $table->uuid('started_by');
            $table->timestamp('started_at');
            $table->uuid('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->enum('status', ['draft', 'in_progress', 'completed', 'locked', 'reopened'])->default('draft');
            $table->decimal('unmatched_statement_total', 16, 4)->default(0);
            $table->decimal('unmatched_internal_total', 16, 4)->default(0);
            $table->decimal('variance', 16, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('locked_at')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys (cross-schema)
            $table->foreign('company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('cascade');
            $table->foreign('statement_id')
                  ->references('id')
                  ->on('ops.bank_statements')
                  ->onDelete('cascade');
            $table->foreign('ledger_account_id')
                  ->references('id')
                  ->on('ledger.chart_of_accounts')
                  ->onDelete('cascade');
            $table->foreign('started_by')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('cascade');
            $table->foreign('completed_by')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('set null');

            // Unique Constraints (per-tenant)
            $table->unique(['company_id', 'ledger_account_id', 'status'], 'unique_active_reconciliation');

            // Indexes for Performance
            $table->index(['company_id', 'ledger_account_id', 'status']);
            $table->index(['company_id', 'status']);
            $table->index(['statement_id']);
            $table->index(['started_at']);
        });

        Schema::create('ledger.bank_reconciliation_matches', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Business Data
            $table->uuid('reconciliation_id');
            $table->uuid('statement_line_id');
            $table->string('source_type'); // Morph type
            $table->uuid('source_id');     // Morph ID
            $table->timestamp('matched_at');
            $table->uuid('matched_by');
            $table->decimal('amount', 16, 4);
            $table->boolean('auto_matched')->default(false);
            $table->decimal('confidence_score', 5, 2)->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('reconciliation_id')
                  ->references('id')
                  ->on('ledger.bank_reconciliations')
                  ->onDelete('cascade');
            $table->foreign('statement_line_id')
                  ->references('id')
                  ->on('ops.bank_statement_lines')
                  ->onDelete('cascade');
            $table->foreign('matched_by')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('cascade');

            // Unique Constraints
            $table->unique(['statement_line_id', 'source_type', 'source_id'], 'unique_match');

            // Indexes for Performance
            $table->index(['reconciliation_id']);
            $table->index(['source_type', 'source_id']);
            $table->index(['matched_at']);
        });

        Schema::create('ledger.bank_reconciliation_adjustments', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Tenant Isolation
            $table->uuid('company_id');

            // Business Data
            $table->uuid('reconciliation_id');
            $table->uuid('statement_line_id')->nullable();
            $table->enum('adjustment_type', ['bank_fee', 'interest', 'write_off', 'timing']);
            $table->uuid('journal_entry_id');
            $table->decimal('amount', 16, 4);
            $table->string('description');
            $table->uuid('created_by');

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys (cross-schema)
            $table->foreign('company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('cascade');
            $table->foreign('reconciliation_id')
                  ->references('id')
                  ->on('ledger.bank_reconciliations')
                  ->onDelete('cascade');
            $table->foreign('statement_line_id')
                  ->references('id')
                  ->on('ops.bank_statement_lines')
                  ->onDelete('set null');
            $table->foreign('journal_entry_id')
                  ->references('id')
                  ->on('ledger.journal_entries')
                  ->onDelete('cascade');
            $table->foreign('created_by')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('cascade');

            // Indexes for Performance
            $table->index(['company_id', 'reconciliation_id']);
            $table->index(['company_id', 'adjustment_type']);
            $table->index(['statement_line_id']);
            $table->index(['journal_entry_id']);
        });

        // Enable RLS (Row Level Security) for all tables
        DB::statement('ALTER TABLE ledger.bank_reconciliations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ledger.bank_reconciliations FORCE ROW LEVEL SECURITY');

        DB::statement('ALTER TABLE ledger.bank_reconciliation_matches ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ledger.bank_reconciliation_matches FORCE ROW LEVEL SECURITY');

        DB::statement('ALTER TABLE ledger.bank_reconciliation_adjustments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ledger.bank_reconciliation_adjustments FORCE ROW LEVEL SECURITY');

        // Create RLS Policies with proper company context
        DB::statement("
            CREATE POLICY bank_reconciliations_company_policy ON ledger.bank_reconciliations
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        DB::statement("
            CREATE POLICY bank_reconciliation_matches_company_policy ON ledger.bank_reconciliation_matches
            FOR ALL
            USING (EXISTS (
                SELECT 1 FROM ledger.bank_reconciliations br 
                WHERE br.id = ledger.bank_reconciliation_matches.reconciliation_id 
                AND br.company_id = current_setting('app.current_company_id', true)::uuid
            ))
            WITH CHECK (EXISTS (
                SELECT 1 FROM ledger.bank_reconciliations br 
                WHERE br.id = ledger.bank_reconciliation_matches.reconciliation_id 
                AND br.company_id = current_setting('app.current_company_id', true)::uuid
            ))
        ");

        DB::statement("
            CREATE POLICY bank_reconciliation_adjustments_company_policy ON ledger.bank_reconciliation_adjustments
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        // Create Audit Triggers (for financial tables)
        DB::statement('
            CREATE TRIGGER bank_reconciliations_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON ledger.bank_reconciliations
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');

        DB::statement('
            CREATE TRIGGER bank_reconciliation_matches_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON ledger.bank_reconciliation_matches
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');

        DB::statement('
            CREATE TRIGGER bank_reconciliation_adjustments_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON ledger.bank_reconciliation_adjustments
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');

        // Create helpful performance indexes
        DB::statement('
            CREATE INDEX idx_bank_reconciliations_account_status 
            ON ledger.bank_reconciliations (company_id, ledger_account_id, status)
        ');

        DB::statement('
            CREATE INDEX idx_bank_reconciliation_matches_sources 
            ON ledger.bank_reconciliation_matches (source_type, source_id)
        ');
    }

    public function down(): void
    {
        // Clean up in reverse order
        DB::statement('DROP TRIGGER IF EXISTS bank_reconciliation_adjustments_audit_trigger ON ledger.bank_reconciliation_adjustments');
        DB::statement('DROP TRIGGER IF EXISTS bank_reconciliation_matches_audit_trigger ON ledger.bank_reconciliation_matches');
        DB::statement('DROP TRIGGER IF EXISTS bank_reconciliations_audit_trigger ON ledger.bank_reconciliations');
        
        DB::statement('DROP POLICY IF EXISTS bank_reconciliation_adjustments_company_policy ON ledger.bank_reconciliation_adjustments');
        DB::statement('DROP POLICY IF EXISTS bank_reconciliation_matches_company_policy ON ledger.bank_reconciliation_matches');
        DB::statement('DROP POLICY IF EXISTS bank_reconciliations_company_policy ON ledger.bank_reconciliations');
        
        Schema::dropIfExists('ledger.bank_reconciliation_adjustments');
        Schema::dropIfExists('ledger.bank_reconciliation_matches');
        Schema::dropIfExists('ledger.bank_reconciliations');
    }
};
