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
        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('created_by', 'fk_companies_created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('updated_by', 'fk_companies_updated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->index('country_id', 'idx_company_country');
        });

        // Currency_id column doesn't exist in countries table yet

        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->index(['base_currency_id', 'target_currency_id', 'effective_date'], 'idx_rates_pair_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign('fk_companies_created_by');
            $table->dropForeign('fk_companies_updated_by');
        });

        // user_accounts table doesn't exist yet

        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('idx_company_country');
        });

        // Currency_id column doesn't exist in countries table yet

        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropIndex('idx_rates_pair_date');
        });
    }
};
