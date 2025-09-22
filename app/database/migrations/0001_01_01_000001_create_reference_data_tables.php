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
        // Create languages table
        Schema::create('languages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // ISO 639-1/2 codes (e.g., en, zh, etc.)
            $table->char('code', 10)->unique();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Create currencies table
        Schema::create('currencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // ISO 4217 alpha code (e.g., USD)
            $table->string('code', 3)->unique();
            $table->string('numeric_code', 3)->nullable();
            $table->string('name');
            $table->string('symbol', 8)->nullable();
            $table->string('symbol_position')->default('before');
            $table->string('thousands_separator')->default(',');
            $table->string('decimal_separator')->default('.');
            $table->unsignedTinyInteger('minor_unit')->default(2); // decimals
            $table->unsignedTinyInteger('cash_minor_unit')->nullable();
            $table->decimal('rounding', 6, 3)->default(0); // some currencies round (e.g., CHF cash)
            $table->boolean('fund')->default(false); // ISO fund codes
            $table->boolean('is_active')->default(true);
            $table->decimal('exchange_rate', 10, 6)->default(1.0);
            $table->timestamp('last_updated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Add indexes
            $table->index('code');
            $table->index('is_active');
        });

        // Create locales table
        Schema::create('locales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // e.g., en_US, en_AE, etc.
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->string('language_code', 10);
            $table->string('country_code', 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Foreign key to languages
            $table->foreign('language_code')->references('code')->on('languages')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locales');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('languages');
    }
};
