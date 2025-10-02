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
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('auth.companies')->cascadeOnDelete();

            $table->date('payment_date');
            $table->string('payment_method')->nullable(); // e.g., 'bank_transfer', 'cheque', 'cash'
            $table->string('reference')->nullable();

            $table->string('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->decimal('amount', 15, 4);
            $table->decimal('unallocated_amount', 15, 4);

            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE bill_payments ADD CONSTRAINT bill_payments_amounts_check CHECK (amount >= 0 AND unallocated_amount >= 0)');

        // Add indexes for better query performance
        DB::statement('CREATE INDEX idx_bill_payments_company_date ON bill_payments(company_id, payment_date) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_bill_payments_company_currency ON bill_payments(company_id, currency_code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX idx_bill_payments_payment_method ON bill_payments(payment_method) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_payments');
    }
};
