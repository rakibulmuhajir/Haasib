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
        // Create ops schema if it doesn't exist
        DB::statement('CREATE SCHEMA IF NOT EXISTS ops');

        Schema::create('ops.bank_statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('ledger_account_id');
            $table->string('statement_uid');
            $table->string('statement_name');
            $table->decimal('opening_balance', 16, 4);
            $table->decimal('closing_balance', 16, 4);
            $table->char('currency', 3);
            $table->date('statement_start_date');
            $table->date('statement_end_date');
            $table->string('file_path');
            $table->enum('format', ['csv', 'ofx', 'qfx']);
            $table->uuid('imported_by');
            $table->timestamp('imported_at');
            $table->timestamp('processed_at')->nullable();
            $table->enum('status', ['pending', 'processed', 'reconciled', 'archived'])->default('pending');
            $table->timestamps();

            // Indexes
            $table->unique(['company_id', 'statement_uid']);
            $table->unique(['company_id', 'ledger_account_id', 'statement_uid']);
            $table->index(['company_id']);
            $table->index(['ledger_account_id']);
            $table->index(['status']);
            $table->index(['statement_start_date', 'statement_end_date']);

            // Foreign key constraints
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('ledger_account_id')->references('id')->on('ledger.chart_of_accounts')->onDelete('cascade');
            $table->foreign('imported_by')->references('id')->on('auth.users')->onDelete('cascade');
        });

        Schema::create('ops.bank_statement_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('statement_id');
            $table->uuid('company_id');
            $table->date('transaction_date');
            $table->timestamp('posted_at')->nullable();
            $table->text('description');
            $table->string('reference_number')->nullable();
            $table->decimal('amount', 16, 4);
            $table->decimal('balance_after', 16, 4)->nullable();
            $table->string('external_id')->nullable();
            $table->string('line_hash');
            $table->jsonb('categorization')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['statement_id', 'line_hash']);
            $table->index(['statement_id']);
            $table->index(['company_id']);
            $table->index(['transaction_date']);
            $table->index(['amount']);
            $table->index(['reference_number']);

            // Foreign key constraints
            $table->foreign('statement_id')->references('id')->on('ops.bank_statements')->onDelete('cascade');
        });

        // Add RLS policies for bank_statements
        DB::statement('
            ALTER TABLE ops.bank_statements ENABLE ROW LEVEL SECURITY
        ');

        DB::statement('
            CREATE POLICY bank_statements_company_policy ON ops.bank_statements
                USING (company_id = auth.uid())
                WITH CHECK (company_id = auth.uid())
        ');

        // Add RLS policies for bank_statement_lines
        DB::statement('
            ALTER TABLE ops.bank_statement_lines ENABLE ROW LEVEL SECURITY
        ');

        DB::statement('
            CREATE POLICY bank_statement_lines_company_policy ON ops.bank_statement_lines
                USING (company_id = auth.uid())
                WITH CHECK (company_id = auth.uid())
        ');

        // Create helpful indexes
        DB::statement('
            CREATE INDEX idx_bank_statements_company_dates 
            ON ops.bank_statements (company_id, statement_start_date, statement_end_date)
        ');

        DB::statement('
            CREATE INDEX idx_bank_statement_lines_search 
            ON ops.bank_statement_lines (company_id, transaction_date, amount, description)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ops.bank_statement_lines');
        Schema::dropIfExists('ops.bank_statements');

        // Note: We don't drop the ops schema as other tables might exist
    }
};
