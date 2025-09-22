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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_currency');
    }
};
