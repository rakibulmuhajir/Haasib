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
        Schema::create('invoicing.payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->string('payment_number', 50);
            $table->date('payment_date');
            $table->string('payment_method', 50);
            $table->string('reference_number', 100)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->default('pending'); // pending, completed, failed, cancelled
            $table->text('notes')->nullable();
            $table->uuid('paymentable_id')->nullable(); // For backward compatibility
            $table->string('paymentable_type')->nullable(); // For backward compatibility
            $table->uuid('created_by_user_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('invoicing.customers')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->onDelete('set null');

            // Indexes
            $table->unique(['company_id', 'payment_number']);
            $table->index(['company_id']);
            $table->index(['customer_id']);
            $table->index(['payment_date']);
            $table->index(['status']);
            $table->index(['payment_method']);
        });

        // Add soft deletes
        Schema::table('invoicing.payments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoicing.payments');
    }
};
