<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoicing.payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('payment_id');
            $table->uuid('invoice_id');
            $table->decimal('allocated_amount', 15, 2);
            $table->timestamp('allocation_date');
            $table->string('allocation_method', 50)->default('manual'); // manual, automatic, fifo, proportional
            $table->string('allocation_strategy', 50)->nullable(); // fifo, due_date, amount, custom
            $table->text('notes')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->uuid('reversed_by_user_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('invoicing.payments')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoicing.invoices')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->onDelete('set null');
            $table->foreign('reversed_by_user_id')->references('id')->on('auth.users')->onDelete('set null');

            // Indexes
            $table->index(['company_id']);
            $table->index(['payment_id']);
            $table->index(['invoice_id']);
            $table->index(['allocation_date']);
            $table->index(['allocation_method']);
            $table->index(['allocation_strategy']);
            $table->index(['reversed_at']);
            $table->unique(['payment_id', 'invoice_id', 'reversed_at'], 'unique_payment_invoice_allocation');
        });

        // Add soft deletes
        Schema::table('invoicing.payment_allocations', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing.payment_allocations');
    }
};
