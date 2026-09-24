<?php

use App\Modules\Inventory\Models\Item;
use Illuminate\Support\Str;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * StoreFuelSaleRequest used bare 'inv.items,id' / 'fuel.pumps,id' / 'acct.customers,id'
 * strings in exists() rules -- the same connection-splitting bug fixed across the rest of
 * the app (see tests/Feature/Accounting/ExistsRuleConnectionRoutingTest.php for the full
 * explanation and the connection-poisoning proof). This is the Daily-Close-adjacent entry
 * point: every fuel sale recorded on the standalone Sales/Form page is what the Daily Close
 * later imports for its date.
 *
 * Relies on creditCloseFixture()/standaloneFuelSaleFixture() from
 * DailyCloseCreditSalesTest.php and StandaloneFuelSaleTest.php, which Pest loads when the
 * FuelStation directory is run together (module scope), per project convention.
 */
test('a fuel sale is accepted when item_id and customer_id belong to this company', function () {
    $f = standaloneFuelSaleFixture();
    $item = Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/sales", [
        'sale_type' => 'credit',
        'item_id' => $item->id,
        'quantity' => 10,
        'sale_date' => '2026-09-15',
        'customer_id' => $f['customer']->id,
    ]);

    $response->assertSessionHasNoErrors();
});

test('a fuel sale naming an item_id that does not exist at all is rejected', function () {
    $f = standaloneFuelSaleFixture();

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/sales", [
        'sale_type' => 'credit',
        'item_id' => (string) Str::uuid(),
        'quantity' => 10,
        'sale_date' => '2026-09-15',
        'customer_id' => $f['customer']->id,
    ]);

    $response->assertSessionHasErrors('item_id');
});

test('a fuel sale naming a customer_id that does not exist at all is rejected', function () {
    $f = standaloneFuelSaleFixture();
    $item = Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/sales", [
        'sale_type' => 'credit',
        'item_id' => $item->id,
        'quantity' => 10,
        'sale_date' => '2026-09-15',
        'customer_id' => (string) Str::uuid(),
    ]);

    $response->assertSessionHasErrors('customer_id');
});
