<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure the company-user pivot table exists with the expected columns.
     */
    public function up(): void
    {
        // If the correctly shaped table already exists, do nothing
        if (Schema::hasTable('auth.company_user')) {
            return;
        }

        // Remove the incorrect public.company_users table if it exists
        if (Schema::hasTable('company_users')) {
            Schema::drop('company_users');
        }

        // Ensure the auth schema exists (PostgreSQL)
        DB::statement('CREATE SCHEMA IF NOT EXISTS auth');

        // Create the correctly shaped pivot table in the auth schema
        Schema::create('auth.company_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->string('role')->default('member');
            $table->uuid('invited_by_user_id')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index('user_id');
            $table->index('company_id');
        });
    }

    /**
    * Reverse the migrations.
    */
    public function down(): void
    {
        Schema::dropIfExists('auth.company_user');
    }
};
