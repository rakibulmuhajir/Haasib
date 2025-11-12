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
        Schema::create('acct.tax_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('tax_agency_id');

            // Basic information
            $table->string('name', 255);
            $table->string('code', 50); // Unique code for identification
            $table->text('description')->nullable();

            // Tax calculation
            $table->decimal('rate', 8, 4, true); // Tax rate percentage (e.g., 8.2500 for 8.25%)
            $table->string('calculation_method')->default('percentage'); // percentage, fixed_amount
            $table->decimal('fixed_amount', 15, 2, true)->nullable(); // For fixed amount taxes

            // Applicability
            $table->enum('tax_type', ['sales', 'purchase', 'both'])->default('sales');
            $table->boolean('is_compound')->default(false); // Compound tax (tax on tax)
            $table->boolean('is_reverse_charge')->default(false); // Reverse charge applicable
            $table->boolean('is_inclusive')->default(false); // Price includes tax

            // Scope and jurisdiction
            $table->string('country_code', 2)->nullable();
            $table->string('state_province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code_pattern', 255)->nullable(); // Regex pattern for postal codes

            // Effective dates
            $table->date('effective_from')->default(now());
            $table->date('effective_to')->nullable();

            // Configuration
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Default tax rate for company

            // System fields
            $table->uuid('created_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('tax_agency_id')->references('id')->on('acct.tax_agencies')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('auth.users')->onDelete('restrict');

            // Indexes
            $table->index(['company_id', 'code']);
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'tax_type']);
            $table->index(['company_id', 'effective_from', 'effective_to']);
            $table->unique(['company_id', 'code']);
        });

        // Row Level Security policies
        DB::statement('
            ALTER TABLE acct.tax_rates ENABLE ROW LEVEL SECURITY;
        ');

        // RLS policy for company isolation
        DB::statement("
            CREATE POLICY tax_rates_company_isolation_policy ON acct.tax_rates
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");

        // RLS policy for authenticated users in the app_user role
        DB::statement("
            CREATE POLICY tax_rates_app_user_policy ON acct.tax_rates
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.tax_rates');
    }
};
