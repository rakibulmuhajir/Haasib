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
        Schema::create('acct.tax_agencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');

            // Basic information
            $table->string('name', 255);
            $table->string('tax_id', 100)->nullable(); // Tax registration number
            $table->string('country_code', 2);
            $table->string('state_province', 100)->nullable();
            $table->string('city', 100)->nullable();

            // Contact information
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('website', 500)->nullable();

            // Address
            $table->text('address_line_1')->nullable();
            $table->text('address_line_2')->nullable();
            $table->string('postal_code', 20)->nullable();

            // Configuration
            $table->string('reporting_frequency')->default('quarterly'); // monthly, quarterly, annually
            $table->string('filing_method')->default('electronic'); // electronic, paper, auto
            $table->boolean('is_active')->default(true);

            // System fields
            $table->uuid('created_by');
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('auth.users')->onDelete('restrict');

            // Indexes
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'country_code']);
            $table->index(['company_id', 'is_active']);
        });

        // Row Level Security policies
        DB::statement('
            ALTER TABLE acct.tax_agencies ENABLE ROW LEVEL SECURITY;
        ');

        // RLS policy for company isolation
        DB::statement("
            CREATE POLICY tax_agencies_company_isolation_policy ON acct.tax_agencies
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");

        // RLS policy for authenticated users in the app_user role
        DB::statement("
            CREATE POLICY tax_agencies_app_user_policy ON acct.tax_agencies
            FOR ALL TO app_user
            USING (company_id = current_setting('app.current_company_id')::uuid);
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.tax_agencies');
    }
};
