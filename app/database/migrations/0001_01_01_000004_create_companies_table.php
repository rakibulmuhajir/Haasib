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
        DB::statement('CREATE SCHEMA IF NOT EXISTS auth');
if (! Schema::hasTable('auth.companies')) {
        // Create companies table
        Schema::create('auth.companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('created_by_user_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('base_currency', 3)->default('AED');
            $table->uuid('currency_id')->nullable();
            $table->uuid('exchange_rate_id')->nullable();
            $table->string('language', 5)->default('en');
            $table->string('locale', 10)->default('en_AE');
            $table->jsonb('settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::dropIfExists('auth.companies');
        } catch (\Throwable $e) {
            // If the table still has dependencies, they'll be cleaned up by the company_relationships migration
            // This can happen when rolling back multiple migrations
        }
    }
};
