<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The card sales a hand-entered charge covers (usually the month it is booked at the end of):
 * the charge over those sales is the rate each day's card sales, and each supplier bill a
 * vendor card paid, shows as its share. Display only -- the charge posts once, on charge_date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel.card_charges', function (Blueprint $table) {
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fuel.card_charges', function (Blueprint $table) {
            $table->dropColumn(['period_from', 'period_to']);
        });
    }
};
