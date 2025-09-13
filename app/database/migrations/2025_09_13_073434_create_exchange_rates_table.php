<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id('exchange_rate_id');
            $table->foreignId('base_currency_id')->constrained('currencies');
            $table->foreignId('target_currency_id')->constrained('currencies');
            $table->decimal('rate', 20, 10);
            $table->date('effective_date');
            $table->string('source', 50)->default('manual');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['base_currency_id', 'target_currency_id', 'effective_date'], 'uq_rate');
        });
        
        // Add check constraint using raw SQL
        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT chk_diff_ccy CHECK (base_currency_id <> target_currency_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
