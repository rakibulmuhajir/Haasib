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

        Schema::create('acct.payment_allocations', function (Blueprint $table) {
            // UUID Primary Key
            $table->uuid('id')->primary();

            // Business Data
            $table->uuid('payment_id');
            $table->uuid('invoice_id');
            $table->decimal('allocated_amount', 12, 2);
            $table->enum('allocation_type', ['payment', 'credit_memo', 'refund', 'write_off'])->default('payment');
            $table->uuid('allocated_by');
            $table->timestamp('allocated_at');
            $table->text('notes')->nullable();
            $table->uuid('reversed_by')->nullable(); // For allocation reversals
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys (cross-schema within acct)
            $table->foreign('payment_id')
                  ->references('id')
                  ->on('acct.payments')
                  ->onDelete('cascade');
            $table->foreign('invoice_id')
                  ->references('id')
                  ->on('acct.invoices')
                  ->onDelete('cascade');
            $table->foreign('allocated_by')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('cascade');
            $table->foreign('reversed_by')
                  ->references('id')
                  ->on('auth.users')
                  ->onDelete('set null');

            // Unique Constraints - prevent double allocation
            $table->unique(['payment_id', 'invoice_id'], 'unique_payment_invoice_allocation');

            // Indexes for Performance
            $table->index(['payment_id', 'allocated_at']);
            $table->index(['invoice_id', 'allocated_at']);
            $table->index(['allocation_type']);
            $table->index(['allocated_by']);
            $table->index(['allocated_at']);
        });

        // Enable RLS (Row Level Security)
        DB::statement('ALTER TABLE acct.payment_allocations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acct.payment_allocations FORCE ROW LEVEL SECURITY');

        // Create RLS Policy - inherits through payment relationship
        DB::statement("
            CREATE POLICY payment_allocations_company_policy ON acct.payment_allocations
            FOR ALL
            USING (EXISTS (
                SELECT 1 FROM acct.payments p 
                WHERE p.id = acct.payment_allocations.payment_id 
                AND p.company_id = current_setting('app.current_company_id', true)::uuid
            ))
            WITH CHECK (EXISTS (
                SELECT 1 FROM acct.payments p 
                WHERE p.id = acct.payment_allocations.payment_id 
                AND p.company_id = current_setting('app.current_company_id', true)::uuid
            ))
        ");

        // Create Audit Trigger (for financial tables)
        DB::statement('
            CREATE TRIGGER payment_allocations_audit_trigger
            AFTER INSERT OR UPDATE OR DELETE ON acct.payment_allocations
            FOR EACH ROW EXECUTE FUNCTION audit.audit_log()
        ');

        // Create Function for Payment Allocation
        DB::statement('
            CREATE OR REPLACE FUNCTION acct.allocate_payment(
                p_payment_id UUID,
                p_invoice_id UUID,
                p_amount DECIMAL(12,2),
                p_allocated_by UUID,
                p_notes TEXT DEFAULT NULL
            )
            RETURNS UUID AS $$
            DECLARE
                allocation_id UUID;
                current_balance DECIMAL(12,2);
                current_paid DECIMAL(12,2);
            BEGIN
                -- Check if allocation already exists
                SELECT id INTO allocation_id
                FROM acct.payment_allocations
                WHERE payment_id = p_payment_id AND invoice_id = p_invoice_id
                AND deleted_at IS NULL;

                IF allocation_id IS NOT NULL THEN
                    RAISE EXCEPTION \'Payment already allocated to this invoice\';
                END IF;

                -- Check invoice balance
                SELECT balance_due INTO current_balance
                FROM acct.invoices
                WHERE id = p_invoice_id;

                IF current_balance <= 0 THEN
                    RAISE EXCEPTION \'Invoice has no balance due\';
                END IF;

                IF p_amount > current_balance THEN
                    RAISE EXCEPTION \'Allocation amount exceeds invoice balance\';
                END IF;

                -- Create allocation
                INSERT INTO acct.payment_allocations (
                    id, payment_id, invoice_id, allocated_amount, allocated_by, allocated_at, notes
                ) VALUES (
                    gen_random_uuid(), p_payment_id, p_invoice_id, p_amount, p_allocated_by, NOW(), p_notes
                ) RETURNING id INTO allocation_id;

                -- Update invoice paid amount
                UPDATE acct.invoices
                SET 
                    paid_amount = paid_amount + p_amount,
                    balance_due = balance_due - p_amount,
                    updated_at = NOW()
                WHERE id = p_invoice_id;

                -- Update invoice status if fully paid
                IF (SELECT balance_due FROM acct.invoices WHERE id = p_invoice_id) <= 0 THEN
                    UPDATE acct.invoices
                    SET status = \'paid\', paid_at = NOW()
                    WHERE id = p_invoice_id;
                END IF;

                RETURN allocation_id;
            END;
            $$ LANGUAGE plpgsql;
        ');
    }

    public function down(): void
    {
        // Clean up in reverse order
        DB::statement('DROP FUNCTION IF EXISTS acct.allocate_payment(UUID, UUID, DECIMAL, UUID, TEXT)');
        DB::statement('DROP TRIGGER IF EXISTS payment_allocations_audit_trigger ON acct.payment_allocations');
        DB::statement('DROP POLICY IF EXISTS payment_allocations_company_policy ON acct.payment_allocations');
        Schema::dropIfExists('acct.payment_allocations');
    }
};
