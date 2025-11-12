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
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 15, 6);
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->string('provider')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['from_currency', 'to_currency']);
            $table->index(['from_currency', 'to_currency', 'valid_from']);
            $table->index('valid_from');
            $table->index('valid_until');
            $table->index('provider');

            // Unique constraint to prevent duplicate rates for the same period
            $table->unique(['from_currency', 'to_currency', 'valid_from'], 'unique_currency_rate_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
