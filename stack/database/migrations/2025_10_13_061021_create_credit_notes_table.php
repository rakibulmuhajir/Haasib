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
        Schema::create('invoicing.credit_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('invoice_id');
            $table->string('credit_note_number', 50);
            $table->text('reason')->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->default('draft'); // draft, posted, cancelled
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->uuid('created_by_user_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoicing.invoices')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->onDelete('set null');

            // Indexes
            $table->unique(['company_id', 'credit_note_number']);
            $table->index(['company_id']);
            $table->index(['invoice_id']);
            $table->index(['status']);
            $table->index(['posted_at']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing.credit_notes');
    }
};
