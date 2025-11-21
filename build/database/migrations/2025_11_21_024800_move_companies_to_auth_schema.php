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
        // First, backup the existing data
        $existingCompanies = DB::table('public.companies')->get();

        // Drop foreign key constraint on company_user
        Schema::table('auth.company_user', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        // Drop the existing table
        Schema::dropIfExists('public.companies');

        // Create the companies table in auth schema with proper structure
        Schema::create('auth.companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->string('slug')->unique();
            $table->string('country')->nullable();
            $table->uuid('country_id')->nullable();
            $table->string('base_currency', 3)->default('USD');
            $table->uuid('currency_id')->nullable();
            $table->unsignedBigInteger('exchange_rate_id')->nullable();
            $table->string('language', 10)->default('en');
            $table->string('locale', 10)->default('en_US');
            $table->json('settings')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('slug');
            $table->index('country');
            $table->index('industry');
            $table->index('base_currency');
            $table->index('currency_id');
            $table->index('exchange_rate_id');
            $table->index('is_active');
            $table->index(['is_active', 'country']);
            $table->unique(['name', 'country']);
        });

        // Restore the data with proper defaults
        foreach ($existingCompanies as $company) {
            // Handle settings - convert from JSON string or object to proper JSON
            $settings = '{}';
            if (!empty($company->settings)) {
                if (is_string($company->settings)) {
                    // Already a JSON string, validate it
                    $decoded = json_decode($company->settings);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $settings = $company->settings;
                    }
                } elseif (is_object($company->settings) || is_array($company->settings)) {
                    // Convert object/array to JSON string
                    $settings = json_encode($company->settings);
                }
            }

            DB::table('auth.companies')->insert([
                'id' => $company->id,
                'name' => $company->name,
                'industry' => $company->industry ?: null,
                'slug' => \Illuminate\Support\Str::slug($company->name),
                'country' => $company->country ?: null,
                'country_id' => $company->country_id ?: null,
                'base_currency' => $company->base_currency ?: 'USD',
                'currency_id' => $company->currency_id ?: null,
                'exchange_rate_id' => $company->exchange_rate_id ?: null,
                'language' => $company->language ?: 'en',
                'locale' => $company->locale ?: 'en_US',
                'created_by_user_id' => $company->created_by_user_id ?: null,
                'is_active' => ($company->is_active ?? 1) ? true : false,
                'settings' => $settings,
                'created_at' => $company->created_at,
                'updated_at' => $company->updated_at,
            ]);
        }

        // Add foreign key constraints
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->foreign('created_by_user_id')
                ->references('id')->on('auth.users')
                ->onDelete('set null');
        });

        // Restore foreign key constraint for company_user
        Schema::table('auth.company_user', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->onDelete('cascade');
        });

        // Enable Row Level Security
        DB::statement('ALTER TABLE auth.companies ENABLE ROW LEVEL SECURITY;');

        // Create RLS policies
        DB::statement("
            CREATE POLICY companies_select_policy ON auth.companies
            FOR SELECT
            USING (
                current_setting('app.is_super_admin', true)::boolean = true OR
                current_setting('app.current_user_id', true)::uuid = created_by_user_id OR
                EXISTS (
                    SELECT 1 FROM auth.company_user 
                    WHERE auth.company_user.company_id = auth.companies.id 
                    AND auth.company_user.user_id = current_setting('app.current_user_id', true)::uuid
                    AND auth.company_user.is_active = true
                )
            );
        ");

        DB::statement("
            CREATE POLICY companies_update_policy ON auth.companies
            FOR UPDATE
            USING (
                current_setting('app.is_super_admin', true)::boolean = true OR
                EXISTS (
                    SELECT 1 FROM auth.company_user 
                    WHERE auth.company_user.company_id = auth.companies.id 
                    AND auth.company_user.user_id = current_setting('app.current_user_id', true)::uuid
                    AND auth.company_user.is_active = true
                    AND auth.company_user.role IN ('owner', 'admin')
                )
            );
        ");

        DB::statement("
            CREATE POLICY companies_insert_policy ON auth.companies
            FOR INSERT
            WITH CHECK (true);
        ");

        DB::statement("
            CREATE POLICY companies_delete_policy ON auth.companies
            FOR DELETE
            USING (
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backup current auth.companies data
        $authCompanies = DB::table('auth.companies')->get();

        // Drop the auth.companies table
        Schema::dropIfExists('auth.companies');

        // Recreate public.companies with original simple structure
        Schema::create('public.companies', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        // Restore data (simplified - only basic fields that existed in original)
        foreach ($authCompanies as $company) {
            DB::table('public.companies')->insert([
                'id' => 1, // Simple auto-increment for rollback
                'created_at' => $company->created_at,
                'updated_at' => $company->updated_at,
            ]);
        }
    }
};
