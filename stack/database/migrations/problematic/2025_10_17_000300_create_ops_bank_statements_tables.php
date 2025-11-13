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
        DB::statement('CREATE SCHEMA IF NOT EXISTS ops');

        Schema::create('ops.bank_statements', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Tenant Isolation
            $table->uuid('company_id');

            // Business Data
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

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys (cross-schema)
            $table->foreign('company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('cascade');
            $table->foreign('ledger_account_id')
                  ->references('id')
                  ->on('ledger.chart_of_accounts')
                  ->onDelete('cascade');
            $table->foreign('imported_by')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('cascade');

            // Unique Constraints (per-tenant)
            $table->unique(['company_id', 'statement_uid']);
            $table->unique(['company_id', 'ledger_account_id', 'statement_uid']);

            // Indexes for Performance
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'statement_start_date', 'statement_end_date']);
            $table->index(['ledger_account_id']);
        });

        Schema::create('ops.bank_statement_lines', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Business Data
            $table->uuid('statement_id');
            $table->date('transaction_date');
            $table->timestamp('posted_at')->nullable();
            $table->text('description');
            $table->string('reference_number')->nullable();
            $table->decimal('amount', 16, 4);
            $table->decimal('balance_after', 16, 4)->nullable();
            $table->string('external_id')->nullable();
            $table->string('line_hash');
            $table->jsonb('categorization')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('statement_id')
                  ->references('id')
                  ->on('ops.bank_statements')
                  ->onDelete('cascade');

            // Unique Constraints
            $table->unique(['statement_id', 'line_hash']);

            // Indexes for Performance
            $table->index(['statement_id', 'transaction_date']);
            $table->index(['statement_id', 'amount']);
            $table->index(['reference_number']);
            $table->index(['external_id']);
        });

        // Enable RLS (Row Level Security) for all tables
        DB::statement('ALTER TABLE ops.bank_statements ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ops.bank_statements FORCE ROW LEVEL SECURITY');

        DB::statement('ALTER TABLE ops.bank_statement_lines ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ops.bank_statement_lines FORCE ROW LEVEL SECURITY');

        // Create RLS Policies with proper company context
        DB::statement("
            CREATE POLICY bank_statements_company_policy ON ops.bank_statements
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        DB::statement("
            CREATE POLICY bank_statement_lines_company_policy ON ops.bank_statement_lines
            FOR ALL
            USING (EXISTS (
                SELECT 1 FROM ops.bank_statements bs 
                WHERE bs.id = ops.bank_statement_lines.statement_id 
                AND bs.company_id = current_setting('app.current_company_id', true)::uuid
            ))
            WITH CHECK (EXISTS (
                SELECT 1 FROM ops.bank_statements bs 
                WHERE bs.id = ops.bank_statement_lines.statement_id 
                AND bs.company_id = current_setting('app.current_company_id', true)::uuid
            ))
        ");

        // Create Audit Triggers (for operations tables)
        DB::statement('
            CREATE TRIGGER bank_statements_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON ops.bank_statements
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');

        DB::statement('
            CREATE TRIGGER bank_statement_lines_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON ops.bank_statement_lines
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');

        // Create helpful performance indexes
        DB::statement('
            CREATE INDEX idx_bank_statements_company_dates 
            ON ops.bank_statements (company_id, statement_start_date, statement_end_date)
        ');

        DB::statement('
            CREATE INDEX idx_bank_statement_lines_search 
            ON ops.bank_statement_lines (statement_id, transaction_date, amount, description)
        ');
    }

    public function down(): void
    {
        // Clean up in reverse order
        DB::statement('DROP TRIGGER IF EXISTS bank_statement_lines_audit_trigger ON ops.bank_statement_lines');
        DB::statement('DROP TRIGGER IF EXISTS bank_statements_audit_trigger ON ops.bank_statements');
        
        DB::statement('DROP POLICY IF EXISTS bank_statement_lines_company_policy ON ops.bank_statement_lines');
        DB::statement('DROP POLICY IF EXISTS bank_statements_company_policy ON ops.bank_statements');
        
        Schema::dropIfExists('ops.bank_statement_lines');
        Schema::dropIfExists('ops.bank_statements');

        // Note: We don't drop the ops schema as other tables might exist
    }
};
