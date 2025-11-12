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
        Schema::create('acct.tax_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('tax_rate_id');

            // Reference to the transaction this tax component belongs to
            $table->string('transaction_type'); // 'App\Models\Invoice', 'App\Models\Bill', etc.
            $table->uuid('transaction_id'); // ID of the invoice, bill, etc.
            $table->uuid('transaction_line_id')->nullable(); // ID of the specific line item

            // Tax calculation details
            $table->decimal('taxable_amount', 15, 2, true); // Amount before tax
            $table->decimal('tax_rate_percentage', 8, 4, true); // Tax rate used
            $table->decimal('tax_amount', 15, 2, true); // Calculated tax amount
            $table->string('currency', 3); // Currency code

            // Tax configuration at time of calculation
            $table->string('tax_code', 50); // Tax rate code used
            $table->string('tax_name', 255); // Tax rate name used
            $table->boolean('is_compound')->default(false); // Whether this is compound tax
            $table->decimal('compound_base_amount', 15, 2, true)->default(0); // Base amount for compound tax

            // Reversal information
            $table->boolean('is_reversed')->default(false);
            $table->uuid('reversed_by')->nullable(); // ID of the reversal component
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();

            // Payment/credit tracking
            $table->decimal('paid_amount', 15, 2, true)->default(0); // Amount paid to tax authority
            $table->decimal('credited_amount', 15, 2, true)->default(0); // Amount credited

            // Reporting information
            $table->date('tax_period_start'); // Start date of tax period
            $table->date('tax_period_end'); // End date of tax period
            $table->uuid('tax_return_id')->nullable(); // Associated tax return

            // System fields
            $table->uuid('created_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('tax_rate_id')->references('id')->on('acct.tax_rates')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('auth.users')->onDelete('restrict');

            // Indexes
            $table->index(['company_id', 'transaction_type', 'transaction_id']);
            $table->index(['company_id', 'tax_rate_id']);
            $table->index(['company_id', 'tax_period_start', 'tax_period_end']);
            $table->index(['company_id', 'tax_return_id']);
            $table->index(['company_id', 'is_reversed']);
            $table->index(['company_id', 'created_at']);
        });

        // Row Level Security policies
        DB::statement('
            ALTER TABLE acct.tax_components ENABLE ROW LEVEL SECURITY;
        ');

        // RLS policy for company isolation
        DB::statement("
            CREATE POLICY tax_components_company_isolation_policy ON acct.tax_components
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");

        // RLS policy for authenticated users in the app_user role
        DB::statement("
            CREATE POLICY tax_components_app_user_policy ON acct.tax_components
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.tax_components');
    }
};
