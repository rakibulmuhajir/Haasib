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
        Schema::create('ledger.bank_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
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
            $table->timestamps();

            // Indexes
            $table->unique(['company_id', 'ledger_account_id', 'status'], 'unique_active_reconciliation');
            $table->index(['company_id']);
            $table->index(['statement_id']);
            $table->index(['ledger_account_id']);
            $table->index(['status']);
            $table->index(['started_at']);

            // Foreign key constraints
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('statement_id')->references('id')->on('ops.bank_statements')->onDelete('cascade');
            $table->foreign('ledger_account_id')->references('id')->on('ledger.chart_of_accounts')->onDelete('cascade');
            $table->foreign('started_by')->references('id')->on('auth.users')->onDelete('cascade');
            $table->foreign('completed_by')->references('id')->on('auth.users')->onDelete('set null');
        });

        Schema::create('ledger.bank_reconciliation_matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reconciliation_id');
            $table->uuid('statement_line_id');
            $table->string('source_type'); // Morph type
            $table->uuid('source_id');     // Morph ID
            $table->timestamp('matched_at');
            $table->uuid('matched_by');
            $table->decimal('amount', 16, 4);
            $table->boolean('auto_matched')->default(false);
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['statement_line_id', 'source_type', 'source_id'], 'unique_match');
            $table->index(['reconciliation_id']);
            $table->index(['statement_line_id']);
            $table->index(['source_type', 'source_id']);
            $table->index(['matched_at']);

            // Foreign key constraints
            $table->foreign('reconciliation_id')->references('id')->on('ledger.bank_reconciliations')->onDelete('cascade');
            $table->foreign('statement_line_id')->references('id')->on('ops.bank_statement_lines')->onDelete('cascade');
            $table->foreign('matched_by')->references('id')->on('auth.users')->onDelete('cascade');
        });

        Schema::create('ledger.bank_reconciliation_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reconciliation_id');
            $table->uuid('company_id');
            $table->uuid('statement_line_id')->nullable();
            $table->enum('adjustment_type', ['bank_fee', 'interest', 'write_off', 'timing']);
            $table->uuid('journal_entry_id');
            $table->decimal('amount', 16, 4);
            $table->string('description');
            $table->uuid('created_by');
            $table->timestamps();

            // Indexes
            $table->index(['reconciliation_id']);
            $table->index(['company_id']);
            $table->index(['statement_line_id']);
            $table->index(['adjustment_type']);
            $table->index(['journal_entry_id']);

            // Foreign key constraints
            $table->foreign('reconciliation_id')->references('id')->on('ledger.bank_reconciliations')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('statement_line_id')->references('id')->on('ops.bank_statement_lines')->onDelete('set null');
            $table->foreign('journal_entry_id')->references('id')->on('ledger.journal_entries')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('auth.users')->onDelete('cascade');
        });

        // Add RLS policies for bank_reconciliations
        DB::statement('
            ALTER TABLE ledger.bank_reconciliations ENABLE ROW LEVEL SECURITY
        ');

        DB::statement('
            CREATE POLICY bank_reconciliations_company_policy ON ledger.bank_reconciliations
                USING (company_id = auth.uid())
                WITH CHECK (company_id = auth.uid())
        ');

        // Add RLS policies for bank_reconciliation_matches
        DB::statement('
            ALTER TABLE ledger.bank_reconciliation_matches ENABLE ROW LEVEL SECURITY
        ');

        DB::statement('
            CREATE POLICY bank_reconciliation_matches_company_policy ON ledger.bank_reconciliation_matches
                USING (EXISTS (
                    SELECT 1 FROM ledger.bank_reconciliations br 
                    WHERE br.id = ledger.bank_reconciliation_matches.reconciliation_id 
                    AND br.company_id = auth.uid()
                ))
                WITH CHECK (EXISTS (
                    SELECT 1 FROM ledger.bank_reconciliations br 
                    WHERE br.id = ledger.bank_reconciliation_matches.reconciliation_id 
                    AND br.company_id = auth.uid()
                ))
        ');

        // Add RLS policies for bank_reconciliation_adjustments
        DB::statement('
            ALTER TABLE ledger.bank_reconciliation_adjustments ENABLE ROW LEVEL SECURITY
        ');

        DB::statement('
            CREATE POLICY bank_reconciliation_adjustments_company_policy ON ledger.bank_reconciliation_adjustments
                USING (company_id = auth.uid())
                WITH CHECK (company_id = auth.uid())
        ');

        // Create helpful indexes
        DB::statement('
            CREATE INDEX idx_bank_reconciliations_account_status 
            ON ledger.bank_reconciliations (company_id, ledger_account_id, status)
        ');

        DB::statement('
            CREATE INDEX idx_bank_reconciliation_matches_sources 
            ON ledger.bank_reconciliation_matches (source_type, source_id)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger.bank_reconciliation_adjustments');
        Schema::dropIfExists('ledger.bank_reconciliation_matches');
        Schema::dropIfExists('ledger.bank_reconciliations');
    }
};
