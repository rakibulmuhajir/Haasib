<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Many-to-many: countries <-> languages
        Schema::create('country_language', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2);
            $table->string('language_code', 8);
            $table->boolean('official')->default(false);
            $table->boolean('primary')->default(false);
            $table->unsignedTinyInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['country_code', 'language_code']);
            $table->foreign('country_code')->references('code')->on('countries')->cascadeOnDelete();
            $table->foreign('language_code')->references('code')->on('languages')->cascadeOnDelete();
        });

        // Many-to-many: countries <-> currencies (some countries use multiple currencies)
        Schema::create('country_currency', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2);
            $table->string('currency_code', 3);
            $table->boolean('official')->default(true);
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->timestamps();

            $table->unique(['country_code', 'currency_code']);
            $table->foreign('country_code')->references('code')->on('countries')->cascadeOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_currency');
        Schema::dropIfExists('country_language');
    }
};

