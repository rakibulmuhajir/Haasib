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

        // Foreign key constraints will be added after auth.users table is created

        // Enable Row Level Security
        DB::statement('ALTER TABLE auth.companies ENABLE ROW LEVEL SECURITY;');

        // Simple RLS policies - detailed policies will be added after company_user table exists
        DB::statement("
            CREATE POLICY companies_select_policy ON auth.companies
            FOR SELECT
            USING (
                current_setting('app.is_super_admin', true)::boolean = true OR
                current_setting('app.current_user_id', true)::uuid = created_by_user_id
            );
        ");

        DB::statement("
            CREATE POLICY companies_update_policy ON auth.companies
            FOR UPDATE
            USING (
                current_setting('app.is_super_admin', true)::boolean = true OR
                current_setting('app.current_user_id', true)::uuid = created_by_user_id
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
            USING (current_setting('app.is_super_admin', true)::boolean = true);
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
