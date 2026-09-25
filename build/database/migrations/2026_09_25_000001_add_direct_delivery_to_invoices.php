<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Fuel sometimes never goes through the pumps: a whole tanker load, or the tail
 * of one, is invoiced straight from Accounting -> Invoices to a buyer and never
 * reaches the station's tank or a meter. Such an invoice must never be picked up
 * by a daily close as a "channel of the close" the way a Fuel -> Sales credit
 * invoice is (see DailyCloseCreditSaleService::pendingFuelInvoiceDetails) --
 * is_direct_delivery is how the invoice says so itself.
 *
 * A plain invoice on a fuel revenue account that is NOT direct delivery is the
 * other half of the picture: it was for litres that DID pass a meter, so the
 * close must fold it in as a credit row exactly like a pending fuel-sale
 * invoice. included_in_close_id records which close's journal took it in, so
 * it is only ever picked up once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->boolean('is_direct_delivery')->default(false)->after('status');
            $table->uuid('included_in_close_id')->nullable()->after('transaction_id');
            $table->index(['company_id', 'is_direct_delivery']);
            $table->index(['included_in_close_id']);
        });
    }

    public function down(): void
    {
        Schema::table('acct.invoices', function (Blueprint $table) {
            $table->dropColumn(['is_direct_delivery', 'included_in_close_id']);
        });
    }
};
