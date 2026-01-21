<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add current_manual_reading to fuel.pumps for dual meter tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel.pumps', function (Blueprint $table) {
            $table->decimal('current_manual_reading', 12, 2)->default(0)->after('current_meter_reading');
        });
    }

    public function down(): void
    {
        Schema::table('fuel.pumps', function (Blueprint $table) {
            $table->dropColumn('current_manual_reading');
        });
    }
};
