<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create schema if not exists
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        Schema::create('acct.invoices', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Tenant Isolation
            $table->uuid('company_id');

            // Business Data
            $table->string('invoice_number', 50);
            $table->uuid('customer_id');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->uuid('created_by');
            $table->enum('status', ['draft', 'sent', 'viewed', 'overdue', 'paid', 'void', 'write_off'])->default('draft');
            $table->enum('type', ['invoice', 'credit_memo', 'debit_memo'])->default('invoice');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('customer_notes')->nullable();
            $table->json('line_items');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->uuid('paid_by')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys (cross-schema)
            $table->foreign('company_id')
                  ->references('id')
                  ->on('auth.companies')
                  ->onDelete('cascade');
            $table->foreign('customer_id')
                  ->references('id')
                  ->on('acct.customers')
                  ->onDelete('cascade');
            $table->foreign('created_by')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('cascade');
            $table->foreign('approved_by')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('set null');
            $table->foreign('paid_by')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('set null');

            // Unique Constraints (per-tenant)
            $table->unique(['company_id', 'invoice_number']);

            // Indexes for Performance
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'invoice_date']);
            $table->index(['company_id', 'due_date']);
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'due_date']);
        });

        // Enable RLS (Row Level Security)
        DB::statement('ALTER TABLE acct.invoices ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.invoices FORCE ROW LEVEL SECURITY');

        // Create RLS Policy
        DB::statement("
            CREATE POLICY invoices_company_policy ON acct.invoices
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        // Create Audit Trigger (for financial tables)
        DB::statement('
            CREATE TRIGGER invoices_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON acct.invoices
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');

        // Create Function for Invoice Number Generation
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.generate_invoice_number(p_company_id UUID)
            RETURNS TEXT AS $$
            DECLARE
                next_number INTEGER;
            BEGIN
                SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_number FROM 4) AS INTEGER)), 0) + 1
                INTO next_number
                FROM acct.invoices
                WHERE company_id = p_company_id;

                RETURN \'INV-\' || LPAD(next_number::TEXT, 6, \'0\');
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Constraint to ensure balance_due = total_amount - paid_amount
        DB::statement('
            ALTER TABLE acct.invoices 
            ADD CONSTRAINT check_balance_due CHECK (balance_due = total_amount - paid_amount)
        ');
    }

    public function down(): void
    {
        // Clean up in reverse order
        DB::statement('DROP FUNCTION IF EXISTS acct.generate_invoice_number(UUID)');
        DB::statement('DROP TRIGGER IF EXISTS invoices_audit_trigger ON acct.invoices');
        DB::statement('DROP POLICY IF EXISTS invoices_company_policy ON acct.invoices');
        Schema::dropIfExists('acct.invoices');
    }
};
