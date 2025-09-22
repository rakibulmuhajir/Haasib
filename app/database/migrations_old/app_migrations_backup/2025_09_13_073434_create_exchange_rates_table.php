<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('base_currency_id');
            $table->uuid('target_currency_id');
            $table->decimal('rate', 20, 10);
            $table->date('effective_date');
            $table->string('source', 50)->default('manual');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['base_currency_id', 'target_currency_id', 'effective_date'], 'uq_rate');
        });

        // Add foreign key constraints
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->foreign('base_currency_id')->references('id')->on('currencies')->onDelete('restrict');
            $table->foreign('target_currency_id')->references('id')->on('currencies')->onDelete('restrict');
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
