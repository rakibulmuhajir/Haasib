<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS auth');

        Schema::create('auth.users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('user');
            $table->boolean('is_admin')->default(false);
            $table->rememberToken();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('auth.password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('auth.sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('auth.companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('country', 2);
            $table->string('currency', 3);
            $table->timestamps();
        });

        Schema::create('auth.company_user', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->primary(['company_id', 'user_id']);
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('auth.users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth.company_user');
        Schema::dropIfExists('auth.companies');
        Schema::dropIfExists('auth.sessions');
        Schema::dropIfExists('auth.password_reset_tokens');
        Schema::dropIfExists('auth.users');
        DB::statement('DROP SCHEMA IF EXISTS auth CASCADE');
    }
};
