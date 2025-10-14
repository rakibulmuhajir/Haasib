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
        Schema::create('invoicing.invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->string('invoice_number', 50);
            $table->string('order_number', 50)->nullable();
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('status', 20)->default('draft'); // draft, sent, posted, paid, cancelled
            $table->string('payment_status', 20)->default('unpaid'); // unpaid, partially_paid, paid, overdue
            $table->string('currency', 3);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('overdue_at')->nullable();
            $table->uuid('created_by_user_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('invoicing.customers')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->onDelete('set null');

            // Indexes
            $table->unique(['company_id', 'invoice_number']);
            $table->index(['company_id']);
            $table->index(['customer_id']);
            $table->index(['status']);
            $table->index(['payment_status']);
            $table->index(['due_date']);
            $table->index(['created_at']);
        });

        // Add soft deletes
        Schema::table('invoicing.invoices', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing.invoices');
    }
};
