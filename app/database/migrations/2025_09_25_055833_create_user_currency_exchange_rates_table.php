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
        Schema::create('user_currency_exchange_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('from_currency_id');
            $table->uuid('to_currency_id');
            $table->decimal('exchange_rate', 19, 6)->default(1.000000);
            $table->date('effective_date')->default(now());
            $table->date('cease_date')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('from_currency_id')->references('id')->on('currencies')->onDelete('cascade');
            $table->foreign('to_currency_id')->references('id')->on('currencies')->onDelete('cascade');

            // Unique constraint - user can only have one active rate between two currencies at a time
            $table->unique(['user_id', 'from_currency_id', 'to_currency_id'], 'unique_active_exchange_rate');

            // Indexes
            $table->index(['user_id', 'from_currency_id']);
            $table->index(['user_id', 'to_currency_id']);
            $table->index(['effective_date', 'cease_date']);
            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_currency_exchange_rates');
    }
};
