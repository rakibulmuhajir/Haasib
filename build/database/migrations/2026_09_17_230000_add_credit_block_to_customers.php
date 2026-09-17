<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The credit-customers page has offered a "block credit" toggle for a while,
 * but it was wired to nothing (CreditCustomerController::toggleBlock returned
 * a success flash and touched no column) -- so a blocked buyer could still be
 * sold to on credit from every entry point. This makes the toggle real: one
 * boolean, checked wherever a new credit sale is about to extend the buyer's
 * balance (a blocked buyer is refused there; an over-limit buyer only warns).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.customers', function (Blueprint $table) {
            $table->boolean('is_credit_blocked')->default(false)->after('credit_limit');
        });
    }

    public function down(): void
    {
        Schema::table('acct.customers', function (Blueprint $table) {
            $table->dropColumn('is_credit_blocked');
        });
    }
};
