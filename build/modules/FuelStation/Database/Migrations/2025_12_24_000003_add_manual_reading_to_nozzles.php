<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add last_manual_reading to fuel.nozzles for dual meter tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel.nozzles', function (Blueprint $table) {
            $table->decimal('last_manual_reading', 12, 2)->default(0)->after('last_closing_reading');
        });
    }

    public function down(): void
    {
        Schema::table('fuel.nozzles', function (Blueprint $table) {
            $table->dropColumn('last_manual_reading');
        });
    }
};
