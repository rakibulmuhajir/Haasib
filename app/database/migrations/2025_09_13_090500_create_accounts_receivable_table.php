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
        Schema::create('accounts_receivable', function (Blueprint $table) {
            $table->id('ar_id');
            $table->foreignId('company_id')->constrained('companies', 'company_id');
            $table->foreignId('customer_id')->nullable()->constrained('customers', 'customer_id');
            $table->foreignId('invoice_id')->unique()->constrained('invoices', 'invoice_id');
            $table->decimal('amount_due', 15, 2);
            $table->decimal('original_amount', 15, 2);
            $table->foreignId('currency_id')->constrained('currencies');
            $table->date('due_date');
            $table->integer('days_overdue')->default(0);
            $table->timestamps();
            
            $table->index('company_id');
            $table->index('customer_id');
            $table->index('due_date');
        });
        
        // Add check constraints
        DB::statement('ALTER TABLE accounts_receivable ADD CONSTRAINT chk_amount_due_nonneg CHECK (amount_due >= 0)');
        DB::statement('ALTER TABLE accounts_receivable ADD CONSTRAINT chk_original_positive CHECK (original_amount > 0)');
        DB::statement('ALTER TABLE accounts_receivable ADD CONSTRAINT chk_days_overdue_nonneg CHECK (days_overdue >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts_receivable');
    }
};