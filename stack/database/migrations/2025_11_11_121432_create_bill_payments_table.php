<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.bill_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('payment_number')->unique();
            $table->enum('payment_type', ['bill_payment', 'expense_reimbursement', 'vendor_payment'])->default('bill_payment');
            $table->date('payment_date');
            $table->decimal('amount', 15, 2, true);
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 10, 6, true);
            $table->enum('payment_method', ['cash', 'check', 'bank_transfer', 'credit_card', 'debit_card', 'other'])->default('bank_transfer');
            $table->enum('status', ['pending', 'completed', 'cancelled', 'failed'])->default('completed');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('reference_number')->nullable(); // Check number, transaction ID, etc.
            $table->text('payment_details')->nullable(); // Bank account, card details (encrypted), etc.

            // Polymorphic relationships - can pay bills, expenses, or directly to vendors
            $table->uuid('payable_id')->nullable();
            $table->string('payable_type')->nullable(); // 'App\Models\Bill' or 'App\Models\Expense'

            // Direct vendor payment
            $table->uuid('vendor_id')->nullable();

            // Payer/Payee information
            $table->uuid('paid_by')->nullable(); // User who made the payment
            $table->uuid('created_by');

            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('vendor_id')->references('id')->on('acct.vendors')->onDelete('set null');
            $table->foreign('paid_by')->references('id')->on('auth.users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('auth.users')->onDelete('restrict');

            // Indexes
            $table->index(['company_id', 'payment_number']);
            $table->index(['company_id', 'payment_type']);
            $table->index(['company_id', 'payment_date']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'payable_type', 'payable_id']);
            $table->index(['company_id', 'vendor_id']);
        });

        // Row Level Security policies
        DB::statement('
            ALTER TABLE acct.bill_payments ENABLE ROW LEVEL SECURITY;
        ');

        // RLS policy for company isolation
        DB::statement("
            CREATE POLICY bill_payments_company_isolation_policy ON acct.bill_payments
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");

        // RLS policy for authenticated users in the app_user role
        DB::statement("
            CREATE POLICY bill_payments_app_user_policy ON acct.bill_payments
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('acct.bill_payments');
    }
};
