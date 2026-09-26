<?php

use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/PendingDeliveryFixtures.php';

/*
 * Fuel sold straight from the supplier's tanker (FuelSaleController::storeDirect): a
 * direct-delivery invoice that posts its own income, never touches a tank, and -- paid in
 * cash -- lands in the station's cash account on the sale date.
 */
function directSaleFixture(): array
{
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = pendingDeliveryFixture();
    $f['item']->update(['income_account_id' => $f['accounts']['4100']->id]);

    return $f;
}

function postDirectSale(array $f, bool $paidInCash)
{
    return test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/sales/direct", [
        'customer_id' => $f['customer']->id,
        'item_id' => $f['item']->id,
        'quantity' => 994,
        'unit_price' => 280,
        'sale_date' => '2026-09-15',
        'paid_in_cash' => $paidInCash,
    ]);
}

test('a cash direct sale is a paid direct-delivery invoice with its cash in the drawer', function () {
    $f = directSaleFixture();
    $movementsBefore = DB::table('inv.stock_movements')->where('company_id', $f['company']->id)->count();

    postDirectSale($f, true)->assertRedirect()->assertSessionHasNoErrors();

    $invoice = Invoice::where('company_id', $f['company']->id)->where('is_direct_delivery', true)->sole();
    expect((float) $invoice->total_amount)->toBe(278320.0)
        ->and((float) $invoice->balance)->toBe(0.0)
        ->and($invoice->transaction_id)->not->toBeNull(); // income posted by the invoice itself

    $payment = Payment::where('company_id', $f['company']->id)->sole();
    expect($payment->deposit_account_id)->toBe($f['accounts']['1050']->id)
        ->and($payment->payment_date->toDateString())->toBe('2026-09-15');

    expect(DB::table('inv.stock_movements')->where('company_id', $f['company']->id)->count())->toBe($movementsBefore);
});

test('a credit direct sale stays owed with no payment', function () {
    $f = directSaleFixture();

    postDirectSale($f, false)->assertRedirect()->assertSessionHasNoErrors();

    $invoice = Invoice::where('company_id', $f['company']->id)->where('is_direct_delivery', true)->sole();
    expect((float) $invoice->balance)->toBe(278320.0)
        ->and(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
});
