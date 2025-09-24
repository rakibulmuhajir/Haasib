<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This is a no-op migration - it's only needed for rollback
    }

    /**
     * Reverse the migrations.
     *
     * This migration should be run SECOND during rollback to drop tables
     * in the correct order after all foreign keys have been removed.
     */
    public function down(): void
    {
        // Drop tables in reverse dependency order

        // First drop any remaining tables that might have dependencies
        Schema::dropIfExists('company_secondary_currencies');
        Schema::dropIfExists('auth.company_user');
        Schema::dropIfExists('auth.companies');

        // Drop exchange_rates after dependent tables are gone
        Schema::dropIfExists('exchange_rates');

        // Drop pivot tables
        Schema::dropIfExists('country_currency');
        Schema::dropIfExists('country_language');

        // Drop main reference tables
        Schema::dropIfExists('locales');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('languages');

        // Drop core tables
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('users');
    }
};
