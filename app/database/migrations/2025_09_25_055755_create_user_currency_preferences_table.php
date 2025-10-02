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
        Schema::create('auth.user_currency_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('currency_id');
            $table->boolean('is_base_currency')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references()->on('auth.users')->onDelete('cascade');
            $table->foreign('currency_id')->references()->on('public.currencies')->onDelete('cascade');

            // Unique constraint - user can only have a currency once
            $table->unique(['user_id', 'currency_id']);

            // Indexes
            $table->index(['user_id', 'is_base_currency']);
            $table->index(['user_id', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth.user_currency_preferences');
    }
};
