<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auth.companies')) {
            return;
        }

        DB::statement('CREATE SCHEMA IF NOT EXISTS auth');

        Schema::create('auth.companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->string('slug')->unique();
            $table->string('country')->nullable();
            $table->uuid('country_id')->nullable();
            $table->char('base_currency', 3)->default('USD');
            $table->string('language', 10)->default('en');
            $table->string('locale', 10)->default('en_US');
            $table->json('settings')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('slug');
            $table->index('country');
            $table->index('industry');
            $table->index('base_currency');
            $table->index('is_active');
            $table->index(['is_active', 'country']);
            $table->unique(['name', 'country']);
        });

        DB::statement("ALTER TABLE auth.companies ADD CONSTRAINT companies_base_currency_format CHECK (base_currency ~ '^[A-Z]{3}$')");

        Schema::table('auth.companies', function (Blueprint $table) {
            $table->foreign('created_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete();
        });

        DB::statement('ALTER TABLE auth.companies ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY companies_select_policy ON auth.companies
            FOR SELECT USING ( current_setting('app.is_super_admin', true)::boolean = true );
        ");
        DB::statement("
            CREATE POLICY companies_update_policy ON auth.companies
            FOR UPDATE USING ( current_setting('app.is_super_admin', true)::boolean = true );
        ");
        DB::statement("
            CREATE POLICY companies_insert_policy ON auth.companies
            FOR INSERT WITH CHECK ( true );
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS companies_select_policy ON auth.companies');
        DB::statement('DROP POLICY IF EXISTS companies_update_policy ON auth.companies');
        DB::statement('DROP POLICY IF EXISTS companies_insert_policy ON auth.companies');
        Schema::dropIfExists('auth.companies');
    }
};
