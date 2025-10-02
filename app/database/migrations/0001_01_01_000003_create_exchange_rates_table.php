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
        // Create exchange_rates table
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('base_currency_id');
            $table->uuid('target_currency_id');
            $table->decimal('rate', 20, 10);
            $table->date('effective_date');
            $table->string('source', 50)->default('manual');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['base_currency_id', 'target_currency_id', 'effective_date'], 'uq_rate');
        });

        // Add foreign key constraints
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->foreign('base_currency_id')->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('target_currency_id')->references('id')->on('currencies')->onDelete('restrict');
        });

        // Add check constraint using raw SQL
        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT chk_diff_ccy CHECK (base_currency_id <> target_currency_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First drop foreign key constraints from tables that reference exchange_rates
        // These should be handled by the company_relationships migration, but we'll try to clean up here too
        try {
            Schema::table('auth.companies', function (Blueprint $table) {
                $table->dropForeign(['exchange_rate_id']);
            });
        } catch (\Throwable $e) {
            // Table or constraint might not exist
        }
        
        try {
            Schema::table('company_secondary_currencies', function (Blueprint $table) {
                $table->dropForeign(['exchange_rate_id']);
            });
        } catch (\Throwable $e) {
            // Table or constraint might not exist
        }
        
        // Use try-catch for the table drop to handle any remaining issues
        try {
            Schema::dropIfExists('exchange_rates');
        } catch (\Throwable $e) {
            // Table might have already been dropped by our patch migration
        }
    }
};
