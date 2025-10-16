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
        Schema::create('acct.credit_note_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('credit_note_id');
            $table->uuid('invoice_id');
            $table->decimal('amount_applied', 15, 2);
            $table->timestamp('applied_at');
            $table->uuid('applied_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('credit_note_id')->references('id')->on('acct.credit_notes')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('acct.invoices')->onDelete('cascade');
            $table->foreign('applied_by_user_id')->references('id')->on('auth.users')->onDelete('set null');

            // Indexes
            $table->index(['credit_note_id']);
            $table->index(['invoice_id']);
            $table->index(['applied_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.credit_note_applications');
    }
};
