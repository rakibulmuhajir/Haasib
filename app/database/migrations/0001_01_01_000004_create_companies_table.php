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

        // Create companies table
        if (!Schema::hasTable('auth.companies')) {
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

        // Add foreign key constraint for created_by_user_id
        if (Schema::hasTable('auth.companies')) {
            $tableName = 'auth.companies';
            $constraintName = 'auth_companies_created_by_user_id_foreign';

            // Check if foreign key constraint already exists
            $foreignKeyExists = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.table_constraints
                WHERE table_schema = 'auth'
                AND table_name = 'companies'
                AND constraint_name = '$constraintName'
            ")[0]->count > 0;

            if (!$foreignKeyExists) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreign('created_by_user_id')
                        ->references('id')->on('auth.users')
                        ->nullOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            // Drop foreign key constraints first
            Schema::table('auth.companies', function (Blueprint $table) {
                $table->dropForeign(['created_by_user_id']);
            });

            Schema::dropIfExists('auth.companies');
        } catch (\Throwable $e) {
            // If the table still has dependencies, they'll be cleaned up by the company_relationships migration
            // This can happen when rolling back multiple migrations
        }
    }
};
