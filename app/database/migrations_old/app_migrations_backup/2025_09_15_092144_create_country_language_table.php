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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_language');
    }
};
