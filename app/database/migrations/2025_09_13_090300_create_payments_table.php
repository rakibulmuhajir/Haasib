<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->foreignId('company_id')->constrained('companies', 'company_id');
            $table->string('payment_number', 100);
            $table->string('payment_type', 50)->default('customer_payment');
            $table->string('entity_type', 50)->default('customer');
            $table->bigInteger('entity_id')->nullable(); // Will be FK to crm.customers when available
            $table->bigInteger('bank_account_id')->nullable(); // Will be FK to bank.company_bank_accounts when available
            $table->string('payment_method', 50); // cash, bank_transfer, card
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('exchange_rate', 20, 10)->default(1);
            $table->string('reference_number', 100)->nullable();
            $table->string('check_number', 50)->nullable();
            $table->string('bank_txn_id', 100)->nullable();
            $table->string('status', 50)->default('completed');
            $table->boolean('reconciled')->default(false);
            $table->timestamp('reconciled_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('created_by')->nullable()->constrained('user_accounts', 'user_id');
            $table->foreignId('updated_by')->nullable()->constrained('user_accounts', 'user_id');
            $table->foreignId('reconciled_by')->nullable()->constrained('user_accounts', 'user_id');
            
            $table->unique(['company_id', 'payment_number']);
        });
        
        // Add check constraints
        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_amount_positive CHECK (amount > 0)');
        
        // Add indexes
        DB::statement('CREATE INDEX idx_payments_company ON payments(company_id)');
        DB::statement('CREATE INDEX idx_payments_date ON payments(company_id, payment_date)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};