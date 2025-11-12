<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('expense_category_id');
            $table->string('expense_number')->unique();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'paid', 'reimbursed'])->default('draft');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('expense_date');
            $table->decimal('amount', 15, 2, true);
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 10, 6, true);
            $table->uuid('employee_id')->nullable(); // For employee expenses
            $table->uuid('vendor_id')->nullable(); // For vendor expenses
            $table->string('receipt_number')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_reference')->nullable();
            $table->uuid('created_by');
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('expense_category_id')->references('id')->on('acct.expense_categories')->onDelete('restrict');
            $table->foreign('employee_id')->references('id')->on('auth.users')->onDelete('set null');
            $table->foreign('vendor_id')->references('id')->on('acct.vendors')->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on('auth.users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('auth.users')->onDelete('restrict');
            $table->foreign('rejected_by')->references('id')->on('auth.users')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('auth.users')->onDelete('restrict');

            // Indexes
            $table->index(['company_id', 'expense_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'expense_date']);
            $table->index(['company_id', 'expense_category_id']);
            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'vendor_id']);
        });

        // Row Level Security policies
        DB::statement('
            ALTER TABLE acct.expenses ENABLE ROW LEVEL SECURITY;
        ');

        // RLS policy for company isolation
        DB::statement("
            CREATE POLICY expenses_company_isolation_policy ON acct.expenses
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");

        // RLS policy for authenticated users in the app_user role
        DB::statement("
            CREATE POLICY expenses_app_user_policy ON acct.expenses
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('acct.expenses');
    }
};
