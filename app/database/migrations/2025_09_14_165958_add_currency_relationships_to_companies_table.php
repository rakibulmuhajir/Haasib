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
        // Add currency foreign key constraints to companies table
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->foreign('currency_id')
                ->references('id')->on('currencies')
                ->nullOnDelete();
            $table->foreign('exchange_rate_id')
                ->references('id')->on('exchange_rates')
                ->nullOnDelete();
        });

        // Create company_secondary_currencies table for multi-currency support
        Schema::create('company_secondary_currencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('currency_id');
            $table->uuid('exchange_rate_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'currency_id'], 'uq_company_currency');
        });

        // Add foreign key constraints for company_secondary_currencies
        Schema::table('company_secondary_currencies', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->onDelete('cascade');
            $table->foreign('currency_id')
                ->references('id')->on('currencies')
                ->onDelete('restrict');
            $table->foreign('exchange_rate_id')
                ->references('id')->on('exchange_rates')
                ->onDelete('set null');
        });

        // Backfill currency_id for existing companies based on base_currency
        DB::statement('UPDATE auth.companies SET currency_id = currencies.id FROM currencies WHERE auth.companies.base_currency = currencies.code');

        // Create index for better performance
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->index('currency_id');
            $table->index('exchange_rate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->dropIndex(['currency_id']);
            $table->dropIndex(['exchange_rate_id']);
        });

        // Drop foreign keys
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->dropForeign(['exchange_rate_id']);
            $table->dropForeign(['currency_id']);
        });

        // Drop company_secondary_currencies table
        Schema::dropIfExists('company_secondary_currencies');
    }
};
