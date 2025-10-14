<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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

            // Foreign key constraints added after table creation
        });

        // Add foreign key constraint after table creation
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->foreign('created_by_user_id')
                ->references('id')->on('auth.users')
                ->onDelete('set null');
        });

        // Add RLS policy for companies table
        DB::statement('
            ALTER TABLE auth.companies ENABLE ROW LEVEL SECURITY;
        ');

        // Policy: Only superadmins can see companies initially (will be updated after company_user table exists)
        DB::statement("
            CREATE POLICY companies_select_policy ON auth.companies
            FOR SELECT
            USING (
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Policy: Only superadmins can update companies initially (will be updated after company_user table exists)
        DB::statement("
            CREATE POLICY companies_update_policy ON auth.companies
            FOR UPDATE
            USING (
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Policy: Users can insert companies (will be filtered at application level)
        DB::statement('
            CREATE POLICY companies_insert_policy ON auth.companies
            FOR INSERT
            WITH CHECK (true);
        ');

        // Create trigger for updated_at
        DB::statement('
            CREATE TRIGGER companies_updated_at
                BEFORE UPDATE ON auth.companies
                FOR EACH ROW
                EXECUTE FUNCTION auth.set_updated_by();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger first
        DB::statement('DROP TRIGGER IF EXISTS companies_updated_at ON auth.companies');

        // Drop table
        Schema::dropIfExists('auth.companies');
    }
};
