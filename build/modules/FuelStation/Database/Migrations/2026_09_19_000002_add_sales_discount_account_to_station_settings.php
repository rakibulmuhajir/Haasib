<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a bulk discount lands. The close posts revenue from the meters at the posted pump
 * rate, so a sale below that rate has to debit contra revenue and credit the drawer —
 * otherwise the discount shows up as an unexplained cash shortage on the close, which is
 * the one signal the manager uses to spot theft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel.station_settings', function (Blueprint $table) {
            $table->uuid('sales_discount_account_id')->nullable()->after('fuel_sales_account_id');

            $table->foreign('sales_discount_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('fuel.station_settings', function (Blueprint $table) {
            $table->dropForeign(['sales_discount_account_id']);
            $table->dropColumn('sales_discount_account_id');
        });
    }
};
