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
        Schema::dropIfExists('auth.company_secondary_currencies');
        Schema::dropIfExists('auth.company_user');
        Schema::dropIfExists('auth.companies');

        // Drop exchange_rates after dependent tables are gone
        Schema::dropIfExists('public.exchange_rates');

        // Drop pivot tables
        Schema::dropIfExists('public.country_currency');
        Schema::dropIfExists('public.country_language');

        // Drop main reference tables
        Schema::dropIfExists('public.locales');
        Schema::dropIfExists('public.countries');
        Schema::dropIfExists('public.currencies');
        Schema::dropIfExists('public.languages');

        // Drop core tables
        Schema::dropIfExists('auth.sessions');
        Schema::dropIfExists('auth.password_reset_tokens');
        Schema::dropIfExists('public.failed_jobs');
        Schema::dropIfExists('public.job_batches');
        Schema::dropIfExists('public.jobs');
        Schema::dropIfExists('public.cache_locks');
        Schema::dropIfExists('public.cache');
        Schema::dropIfExists('auth.users');
    }
};
