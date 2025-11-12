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
        Schema::create('acct.tax_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');

            // Tax calculation settings
            $table->boolean('tax_inclusive_pricing')->default(false); // Default pricing includes tax
            $table->boolean('round_tax_per_line')->default(true); // Round tax per line item
            $table->boolean('allow_compound_tax')->default(true); // Allow compound taxes
            $table->integer('rounding_precision')->default(2); // Decimal places for rounding

            // Tax registration and identification
            $table->string('tax_registration_number')->nullable(); // Company tax ID
            $table->string('vat_number')->nullable(); // VAT registration number
            $table->string('tax_country_code', 2)->default('US'); // Default tax jurisdiction

            // Reporting and filing settings
            $table->string('default_reporting_frequency')->default('quarterly'); // monthly, quarterly, annually
            $table->boolean('auto_file_tax_returns')->default(false); // Automatic filing
            $table->string('tax_year_end_month')->default(12); // Tax year end month
            $table->string('tax_year_end_day')->default(31); // Tax year end day

            // Sales tax settings
            $table->boolean('calculate_sales_tax')->default(true); // Calculate tax on sales
            $table->boolean('charge_tax_on_shipping')->default(false); // Tax shipping charges
            $table->boolean('tax_exempt_customers')->default(true); // Support tax-exempt customers
            $table->uuid('default_sales_tax_rate_id')->nullable(); // Default sales tax rate

            // Purchase tax settings
            $table->boolean('calculate_purchase_tax')->default(true); // Calculate tax on purchases
            $table->boolean('track_input_tax')->default(true); // Track input tax credits
            $table->uuid('default_purchase_tax_rate_id')->nullable(); // Default purchase tax rate

            // Integration settings
            $table->boolean('auto_calculate_tax')->default(true); // Auto-calculate on transactions
            $table->boolean('validate_tax_rates')->default(true); // Validate tax applicability
            $table->boolean('track_tax_by_jurisdiction')->default(true); // Track by location

            // System fields
            $table->uuid('created_by');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('default_sales_tax_rate_id')->references('id')->on('acct.tax_rates')->onDelete('set null');
            $table->foreign('default_purchase_tax_rate_id')->references('id')->on('acct.tax_rates')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('auth.users')->onDelete('restrict');
            $table->foreign('updated_by')->references('id')->on('auth.users')->onDelete('restrict');

            // Indexes
            $table->unique(['company_id']); // One record per company
        });

        // Row Level Security policies
        DB::statement('
            ALTER TABLE acct.tax_settings ENABLE ROW LEVEL SECURITY;
        ');

        // RLS policy for company isolation
        DB::statement("
            CREATE POLICY tax_settings_company_isolation_policy ON acct.tax_settings
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");

        // RLS policy for authenticated users in the app_user role
        DB::statement("
            CREATE POLICY tax_settings_app_user_policy ON acct.tax_settings
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.tax_settings');
    }
};
