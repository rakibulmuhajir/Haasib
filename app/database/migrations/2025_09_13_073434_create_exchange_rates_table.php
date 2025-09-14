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
            $table->id('exchange_rate_id');
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
        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT fk_exchange_rates_base_currency_id FOREIGN KEY (base_currency_id) REFERENCES currencies(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT fk_exchange_rates_target_currency_id FOREIGN KEY (target_currency_id) REFERENCES currencies(id) ON DELETE RESTRICT');

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
