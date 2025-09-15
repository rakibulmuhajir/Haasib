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
            $table->id('allocation_id');
            $table->foreignId('payment_id')->constrained('payments', 'payment_id');
            $table->foreignId('invoice_id')->constrained('invoices', 'invoice_id');
            $table->decimal('allocated_amount', 15, 2);
            $table->timestamps();
        });

        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->index('payment_id', 'idx_alloc_payment');
            $table->index('invoice_id', 'idx_alloc_invoice');
        });

        // Add check constraint
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT chk_allocated_positive CHECK (allocated_amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
