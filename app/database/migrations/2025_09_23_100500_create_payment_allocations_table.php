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
        Schema::create('bill_payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('auth.companies')->cascadeOnDelete();
            $table->foreignUuid('bill_payment_id')->constrained('bill_payments')->cascadeOnDelete();
            $table->foreignUuid('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->foreignUuid('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 4);
            $table->date('allocated_at');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE bill_payment_allocations ADD CONSTRAINT bill_payment_allocations_amount_check CHECK (amount > 0)');

        // Add indexes for better query performance
        DB::statement('CREATE INDEX idx_bill_payment_allocations_company_bill ON bill_payment_allocations(company_id, bill_id)');
        DB::statement('CREATE INDEX idx_bill_payment_allocations_payment_bill ON bill_payment_allocations(bill_payment_id, bill_id)');
        DB::statement('CREATE INDEX idx_bill_payment_allocations_allocated_by ON bill_payment_allocations(allocated_by)');
        DB::statement('CREATE INDEX idx_bill_payment_allocations_allocated_date ON bill_payment_allocations(allocated_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_payment_allocations');
    }
};
