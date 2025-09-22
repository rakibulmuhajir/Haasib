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
        // This is a no-op migration - it's only needed for rollback
    }

    /**
     * Reverse the migrations.
     * 
     * This migration should be run FIRST during rollback to clean up
     * all foreign key constraints before dropping any tables.
     */
    public function down(): void
    {
        // Drop all foreign key constraints in the correct order
        // to avoid circular dependency issues during rollback
        
        try {
            // Drop company_secondary_currencies foreign keys
            Schema::table('company_secondary_currencies', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropForeign(['currency_id']);
                $table->dropForeign(['exchange_rate_id']);
            });
        } catch (\Throwable $e) {
            // Table might not exist
        }
        
        try {
            // Drop auth.company_user foreign keys
            Schema::table('auth.company_user', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropForeign(['user_id']);
                $table->dropForeign(['invited_by_user_id']);
            });
        } catch (\Throwable $e) {
            // Table might not exist
        }
        
        try {
            // Drop auth.companies foreign keys
            Schema::table('auth.companies', function (Blueprint $table) {
                $table->dropForeign(['created_by_user_id']);
                $table->dropForeign(['currency_id']);
                $table->dropForeign(['exchange_rate_id']);
            });
        } catch (\Throwable $e) {
            // Table might not exist
        }
        
        try {
            // Drop exchange_rates foreign keys
            Schema::table('exchange_rates', function (Blueprint $table) {
                $table->dropForeign(['base_currency_id']);
                $table->dropForeign(['target_currency_id']);
            });
        } catch (\Throwable $e) {
            // Table might not exist
        }
        
        try {
            // Drop country_currency foreign keys
            Schema::table('country_currency', function (Blueprint $table) {
                $table->dropForeign(['country_code']);
                $table->dropForeign(['currency_code']);
            });
        } catch (\Throwable $e) {
            // Table might not exist
        }
        
        try {
            // Drop country_language foreign keys
            Schema::table('country_language', function (Blueprint $table) {
                $table->dropForeign(['country_code']);
                $table->dropForeign(['language_code']);
            });
        } catch (\Throwable $e) {
            // Table might not exist
        }
        
        try {
            // Drop locales foreign keys
            Schema::table('locales', function (Blueprint $table) {
                $table->dropForeign(['language_code']);
            });
        } catch (\Throwable $e) {
            // Table might not exist
        }
        
        try {
            // Drop sessions foreign keys
            Schema::table('sessions', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (\Throwable $e) {
            // Table might not exist
        }
        
        // Check if auth schema exists and drop it
        try {
            $schemaExists = DB::select("SELECT schema_name FROM information_schema.schemata WHERE schema_name = 'auth'");
            if (!empty($schemaExists)) {
                DB::statement('DROP SCHEMA IF EXISTS auth CASCADE');
            }
        } catch (\Throwable $e) {
            // Ignore errors
        }
    }
};