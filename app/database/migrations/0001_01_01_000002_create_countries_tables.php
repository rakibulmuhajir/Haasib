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
        // Create countries table
        Schema::create('public.countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // ISO 3166-1 alpha-2 and alpha-3
            $table->char('code', 2)->unique();
            $table->char('alpha3', 3)->nullable()->index();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->string('region')->nullable();
            $table->string('subregion')->nullable();
            $table->string('emoji', 8)->nullable();
            $table->string('capital')->nullable();
            $table->string('calling_code', 8)->nullable();
            $table->boolean('eea_member')->default(false);
            $table->timestamps();
        });

        // Create country_language pivot table
        Schema::create('public.country_language', function (Blueprint $table) {
            $table->string('country_code', 2);
            $table->string('language_code', 10);
            $table->boolean('official')->default(false);
            $table->boolean('primary')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->primary(['country_code', 'language_code']);
            $table->foreign('country_code')->references('code')->on('public.countries')->onDelete('cascade');
            $table->foreign('language_code')->references('code')->on('public.languages')->onDelete('cascade');
        });

        // Create country_currency pivot table
        Schema::create('public.country_currency', function (Blueprint $table) {
            $table->string('country_code', 2);
            $table->string('currency_code', 3);
            $table->boolean('official')->default(false);
            $table->timestamps();

            $table->primary(['country_code', 'currency_code']);
            $table->foreign('country_code')->references('code')->on('public.countries')->onDelete('cascade');
            $table->foreign('currency_code')->references('code')->on('public.currencies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public.country_currency');
        Schema::dropIfExists('public.country_language');
        Schema::dropIfExists('public.countries');
    }
};
