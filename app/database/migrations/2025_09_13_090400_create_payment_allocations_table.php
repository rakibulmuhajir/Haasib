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
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->uuid('allocation_id')->primary();
            $table->uuid('payment_id');
            $table->uuid('invoice_id');
            $table->decimal('allocated_amount', 15, 2);
            $table->string('status', 20)->default('active');
            $table->date('allocation_date')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->foreign('payment_id')->references('payment_id')->on('payments')->onDelete('cascade');
            $table->foreign('invoice_id')->references('invoice_id')->on('invoices')->onDelete('cascade');
            $table->index('payment_id', 'idx_alloc_payment');
            $table->index('invoice_id', 'idx_alloc_invoice');
        });

        // Add check constraint
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT chk_allocated_positive CHECK (allocated_amount > 0)');
        DB::statement("ALTER TABLE payment_allocations ADD CONSTRAINT chk_alloc_status_valid CHECK (status IN ('active','void','refunded'))");

        // Enable RLS and tenant policy (via parent payment)
        DB::statement('ALTER TABLE payment_allocations ENABLE ROW LEVEL SECURITY');
        DB::statement(<<<SQL
            CREATE POLICY payment_allocations_tenant_isolation ON payment_allocations
            USING (EXISTS (
                SELECT 1 FROM payments p
                WHERE p.payment_id = payment_allocations.payment_id
                  AND p.company_id = current_setting('app.current_company', true)::uuid
            ))
            WITH CHECK (EXISTS (
                SELECT 1 FROM payments p
                WHERE p.payment_id = payment_allocations.payment_id
                  AND p.company_id = current_setting('app.current_company', true)::uuid
            ));
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
