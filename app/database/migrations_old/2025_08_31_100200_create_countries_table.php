<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
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
        Schema::create('country_language', function (Blueprint $table) {
            $table->string('country_code', 2);
            $table->string('language_code', 10);
            $table->boolean('official')->default(false);
            $table->boolean('primary')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->primary(['country_code', 'language_code']);
            $table->foreign('country_code')->references('code')->on('countries')->onDelete('cascade');
            $table->foreign('language_code')->references('code')->on('languages')->onDelete('cascade');
        });

        // Create country_currency pivot table
        Schema::create('country_currency', function (Blueprint $table) {
            $table->string('country_code', 2);
            $table->string('currency_code', 3);
            $table->boolean('official')->default(false);
            $table->timestamps();

            $table->primary(['country_code', 'currency_code']);
            $table->foreign('country_code')->references('code')->on('countries')->onDelete('cascade');
            $table->foreign('currency_code')->references('code')->on('currencies')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_currency');
        Schema::dropIfExists('country_language');
        Schema::dropIfExists('countries');
    }
};
