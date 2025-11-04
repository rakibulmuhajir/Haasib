<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades.DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acct.invoices', function (Blueprint $table) {
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
            $table->foreign('customer_id')->references('id')->on('acct.customers')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->onDelete('restrict');

            // Indexes
            $table->unique(['company_id', 'invoice_number']);
            $table->index(['company_id']);
            $table->index(['customer_id']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'payment_status']);
            $table->index(['company_id', 'due_date']);
            $table->index(['company_id', 'created_at']);
        });

        // Add soft deletes
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::statement('
            ALTER TABLE acct.invoices
            ADD CONSTRAINT invoices_amounts_positive
            CHECK (
                subtotal >= 0
                AND tax_amount >= 0
                AND discount_amount >= 0
                AND total_amount >= 0
                AND balance_due >= 0
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.invoices');
    }
};
