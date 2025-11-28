<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS public');

        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->string('name', 100);
            $table->string('symbol', 10);
            $table->smallInteger('decimal_places')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });

        DB::statement("ALTER TABLE currencies ADD CONSTRAINT currencies_code_format CHECK (code ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE currencies ADD CONSTRAINT currencies_decimal_places_range CHECK (decimal_places BETWEEN 0 AND 8)");
        DB::statement("CREATE INDEX idx_currencies_active ON currencies (code) WHERE is_active = true");
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
