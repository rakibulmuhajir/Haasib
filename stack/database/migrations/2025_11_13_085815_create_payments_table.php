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

        Schema::create('acct.payments', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Tenant Isolation
            $table->uuid('company_id');

            // Business Data
            $table->string('payment_number', 50);
            $table->uuid('customer_id');
            $table->enum('payment_type', ['payment', 'refund', 'credit_application'])->default('payment');
            $table->enum('payment_method', ['cash', 'check', 'credit_card', 'bank_transfer', 'online', 'other'])->default('bank_transfer');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->date('payment_date');
            $table->uuid('received_by');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'void', 'refunded'])->default('pending');
            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->json('payment_details')->nullable(); // For storing method-specific details
            $table->uuid('bank_account_id')->nullable(); // Link to bank account if applicable
            $table->decimal('processing_fee', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->timestamp('processed_at')->nullable();
            $table->uuid('processed_by')->nullable();
            $table->text('failure_reason')->nullable();
            $table->uuid('parent_payment_id')->nullable(); // For refunds/credits

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
            $table->foreign('received_by')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('cascade');
            $table->foreign('processed_by')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('set null');
            $table->foreign('parent_payment_id')
                  ->references('id')
                  ->on('acct.payments')
                  ->onDelete('cascade');

            // Unique Constraints (per-tenant)
            $table->unique(['company_id', 'payment_number']);

            // Indexes for Performance
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'payment_date']);
            $table->index(['company_id', 'payment_method']);
            $table->index(['customer_id', 'status']);
            $table->index(['payment_method', 'status']);
            $table->index(['payment_date', 'amount']);
        });

        // Enable RLS (Row Level Security)
        DB::statement('ALTER TABLE acct.payments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.payments FORCE ROW LEVEL SECURITY');

        // Create RLS Policy
        DB::statement("
            CREATE POLICY payments_company_policy ON acct.payments
            FOR ALL
            USING (company_id = current_setting('app.current_company_id', true)::uuid)
            WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid)
        ");

        // Create Audit Trigger (for financial tables)
        DB::statement('
            CREATE TRIGGER payments_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON acct.payments
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');

        // Create Function for Payment Number Generation
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.generate_payment_number(p_company_id UUID)
            RETURNS TEXT AS $$
            DECLARE
                next_number INTEGER;
            BEGIN
                SELECT COALESCE(MAX(CAST(SUBSTRING(payment_number FROM 4) AS INTEGER)), 0) + 1
                INTO next_number
                FROM acct.payments
                WHERE company_id = p_company_id;

                RETURN \'PAY-\' || LPAD(next_number::TEXT, 6, \'0\');
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Constraint to ensure net_amount >= 0 and <= amount
        DB::statement('
            ALTER TABLE acct.payments 
            ADD CONSTRAINT check_net_amount CHECK (net_amount >= 0 AND net_amount <= amount)
        ');
    }

    public function down(): void
    {
        // Clean up in reverse order
        DB::statement('DROP FUNCTION IF EXISTS acct.generate_payment_number(UUID)');
        DB::statement('DROP TRIGGER IF EXISTS payments_audit_trigger ON acct.payments');
        DB::statement('DROP POLICY IF EXISTS payments_company_policy ON acct.payments');
        Schema::dropIfExists('acct.payments');
    }
};
