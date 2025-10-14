<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('locales', function (Blueprint $table) {
            $table->id();
            // BCP 47 tag, e.g. en-US, pt-BR, zh-Hant-TW
            $table->string('tag', 35)->unique();
            $table->string('name')->nullable();
            $table->string('native_name')->nullable();
            $table->string('language_code', 8)->index(); // FK -> languages.code
            $table->char('country_code', 2)->nullable()->index(); // FK -> countries.code
            $table->string('script', 10)->nullable();
            $table->string('variant', 15)->nullable();
            $table->timestamps();

            $table->foreign('language_code')->references('code')->on('languages')->cascadeOnDelete();
            $table->foreign('country_code')->references('code')->on('countries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locales');
    }
};

