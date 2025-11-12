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
        Schema::create('acct.tax_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('tax_agency_id');

            // Return identification
            $table->string('return_number', 50); // Unique return number
            $table->string('return_type'); // 'sales_tax', 'purchase_tax', 'vat', 'income_tax'
            $table->string('filing_frequency'); // 'monthly', 'quarterly', 'annually'

            // Period information
            $table->date('filing_period_start');
            $table->date('filing_period_end');
            $table->date('due_date');
            $table->date('filing_date')->nullable();

            // Status and workflow
            $table->enum('status', ['draft', 'prepared', 'filed', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->enum('filing_method', ['paper', 'electronic', 'auto'])->default('electronic');
            $table->string('confirmation_number')->nullable(); // Filing confirmation

            // Financial summary
            $table->decimal('total_sales', 15, 2, true)->default(0); // Total taxable sales
            $table->decimal('total_purchases', 15, 2, true)->default(0); // Total taxable purchases
            $table->decimal('output_tax', 15, 2, true)->default(0); // Tax collected on sales
            $table->decimal('input_tax', 15, 2, true)->default(0); // Tax paid on purchases
            $table->decimal('tax_due', 15, 2, true)->default(0); // Net tax payable/refundable
            $table->decimal('penalty', 15, 2, true)->default(0); // Late filing penalties
            $table->decimal('interest', 15, 2, true)->default(0); // Interest charges
            $table->decimal('total_amount_due', 15, 2, true)->default(0); // Final amount due

            // Payment information
            $table->decimal('amount_paid', 15, 2, true)->default(0);
            $table->date('payment_date')->nullable();
            $table->string('payment_reference')->nullable();
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid');

            // Notes and attachments
            $table->text('notes')->nullable();
            $table->json('attachments')->nullable(); // Store file information
            $table->string('filing_form_path')->nullable(); // Path to filed form

            // Audit and system fields
            $table->uuid('prepared_by')->nullable();
            $table->uuid('filed_by')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('tax_agency_id')->references('id')->on('acct.tax_agencies')->onDelete('restrict');
            $table->foreign('prepared_by')->references('id')->on('auth.users')->onDelete('set null');
            $table->foreign('filed_by')->references('id')->on('auth.users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('auth.users')->onDelete('restrict');

            // Indexes
            $table->index(['company_id', 'return_number']);
            $table->index(['company_id', 'tax_agency_id']);
            $table->index(['company_id', 'return_type']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'filing_period_start', 'filing_period_end']);
            $table->index(['company_id', 'due_date']);
            $table->unique(['company_id', 'return_number']);
        });

        // Row Level Security policies
        DB::statement('
            ALTER TABLE acct.tax_returns ENABLE ROW LEVEL SECURITY;
        ');

        // RLS policy for company isolation
        DB::statement("
            CREATE POLICY tax_returns_company_isolation_policy ON acct.tax_returns
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");

        // RLS policy for authenticated users in the app_user role
        DB::statement("
            CREATE POLICY tax_returns_app_user_policy ON acct.tax_returns
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.tax_returns');
    }
};
