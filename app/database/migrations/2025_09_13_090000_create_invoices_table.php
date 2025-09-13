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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id('invoice_id');
            $table->foreignId('company_id')->constrained('companies', 'company_id');
            $table->foreignId('customer_id')->nullable()->constrained('customers', 'customer_id');
            $table->string('invoice_number', 100);
            $table->string('reference_number', 100)->nullable();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->foreignId('currency_id')->constrained('currencies');
            $table->decimal('exchange_rate', 20, 10)->default(1);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->string('status', 50)->default('draft'); // draft, sent, posted, cancelled
            $table->string('payment_status', 50)->default('unpaid'); // unpaid, partial, paid, overpaid
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreignId('created_by')->nullable()->constrained('user_accounts', 'user_id');
            $table->foreignId('updated_by')->nullable()->constrained('user_accounts', 'user_id');
            
            $table->unique(['company_id', 'invoice_number']);
        });
        
        // Add check constraints
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_due_date CHECK (due_date >= invoice_date)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_subtotal_nonneg CHECK (subtotal >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_tax_nonneg CHECK (tax_amount >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_discount_nonneg CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_shipping_nonneg CHECK (shipping_amount >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_total_nonneg CHECK (total_amount >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_paid_nonneg CHECK (paid_amount >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_balance_nonneg CHECK (balance_due >= 0)');
        
        // Add indexes
        DB::statement('CREATE INDEX idx_invoices_company ON invoices(company_id)');
        DB::statement('CREATE INDEX idx_invoices_dates ON invoices(company_id, invoice_date)');
        DB::statement('CREATE INDEX idx_invoices_status ON invoices(company_id, status)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};