<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * An overall (whole-bill) discount on top of the per-line discount_rate.
 * bills.discount_amount stays the LINE discounts only (the posting credits
 * Discount received with it); the overall discount is its own column and is
 * spread over the lines as overall_discount_share, so each line's stock or
 * expense debit and each received unit's cost already carry it.
 * Existing tables, so existing RLS applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.bills', function (Blueprint $table) {
            $table->string('overall_discount_type', 10)->nullable();
            $table->decimal('overall_discount_value', 18, 6)->default(0);
            $table->decimal('overall_discount_amount', 18, 6)->default(0);
        });

        DB::statement("ALTER TABLE acct.bills ADD CONSTRAINT bills_overall_discount_type_chk CHECK (overall_discount_type IS NULL OR overall_discount_type IN ('amount','percent'))");

        Schema::table('acct.bill_line_items', function (Blueprint $table) {
            $table->decimal('overall_discount_share', 18, 6)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('acct.bill_line_items', function (Blueprint $table) {
            $table->dropColumn('overall_discount_share');
        });

        DB::statement('ALTER TABLE acct.bills DROP CONSTRAINT IF EXISTS bills_overall_discount_type_chk');

        Schema::table('acct.bills', function (Blueprint $table) {
            $table->dropColumn(['overall_discount_type', 'overall_discount_value', 'overall_discount_amount']);
        });
    }
};
