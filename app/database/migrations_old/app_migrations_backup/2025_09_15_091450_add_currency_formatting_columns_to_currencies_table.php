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
        Schema::table('currencies', function (Blueprint $table) {
            $table->string('symbol_position')->default('before')->after('symbol');
            $table->string('thousands_separator')->default(',')->after('symbol_position');
            $table->string('decimal_separator')->default('.')->after('thousands_separator');
            $table->boolean('is_active')->default(true)->after('fund');
            $table->decimal('exchange_rate', 10, 6)->default(1.0)->after('is_active');
            $table->timestamp('last_updated_at')->nullable()->after('exchange_rate');
            $table->json('metadata')->nullable()->after('last_updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn([
                'symbol_position',
                'thousands_separator',
                'decimal_separator',
                'is_active',
                'exchange_rate',
                'last_updated_at',
                'metadata',
            ]);
        });
    }
};
