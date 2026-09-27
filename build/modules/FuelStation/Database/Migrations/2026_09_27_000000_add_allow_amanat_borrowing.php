<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An amanat holder the owner allows to borrow from the company: their amanat balance may go
 * below zero (withdrawals / fuel beyond what they deposited). Off by default -- everyone else
 * is still refused beyond their balance. See CustomerProfile::canDrawAmanat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel.customer_profiles', function (Blueprint $table) {
            $table->boolean('allow_amanat_borrowing')->default(false)->after('amanat_balance');
        });
    }

    public function down(): void
    {
        Schema::table('fuel.customer_profiles', function (Blueprint $table) {
            $table->dropColumn('allow_amanat_borrowing');
        });
    }
};
