<?php

use App\Modules\Accounting\Models\Invoice;
use App\Modules\FuelStation\Models\SaleMetadata;
use App\Modules\FuelStation\Services\FuelSaleService;
use App\Modules\Inventory\Models\Item;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;

/**
 * A standalone fuel credit sale is the entry point the Daily Close imports from:
 * DailyCloseController::getPendingFuelInvoicesForDailyClose() looks for invoices on the
 * business date carrying credit SaleMetadata and no transaction yet. Only
 * FuelSaleService::createSale() writes that metadata, and until the GET /fuel/sales/form
 * route existed nothing served the page that posts to it -- so the import could never
 * fire in practice. These tests cover that path end to end.
 *
 * Relies on creditCloseFixture() from DailyCloseCreditSalesTest.php, which Pest loads
 * when the FuelStation directory is run together (module scope), per project convention.
 */
function standaloneFuelSaleFixture(): array
{
    $f = creditCloseFixture();
    // Same HTTP setup the other fuel feature tests use: membership so identify.company
    // resolves, the RBAC roles, and the module the /fuel route group requires.
    \Illuminate\Support\Facades\DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    \Illuminate\Support\Facades\DB::table('auth.company_user')->insert([
        'company_id' => $f['company']->id, 'user_id' => $f['user']->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CompanyContextService::class)->assignRole($f['user'], 'owner'));
    $f['company']->enableModule('fuel_station');
    \Illuminate\Support\Facades\DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    test()->actingAs($f['user']);
    app(\App\Services\CurrentCompany::class)->set($f['company']);

    // FuelSaleService prices the sale from the item's current rate and refuses without one.
    $item = Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();
    \App\Modules\FuelStation\Models\RateChange::create([
        'company_id' => $f['company']->id,
        'item_id' => $item->id,
        'effective_date' => '2026-09-01',
        'purchase_rate' => 250,
        'sale_rate' => 300,
    ]);

    return $f;
}

test('the standalone fuel sale form is reachable and lists this company fuel and buyers', function () {
    $f = standaloneFuelSaleFixture();

    test()->actingAs($f['user'])
        ->get("/{$f['company']->slug}/fuel/sales/form")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('FuelStation/Sales/Form')
            ->has('fuelItems')
            ->has('customers')
            ->has('pumps'));
});

test('each pump is labelled with the fuel its tank holds, not "No fuel"', function () {
    $f = standaloneFuelSaleFixture();

    $props = test()->actingAs($f['user'])
        ->get("/{$f['company']->slug}/fuel/sales/form")
        ->assertOk()
        ->viewData('page')['props'];

    // The form renders `pump.tank?.linked_item?.name || 'No fuel'`, so the tank's linked
    // item has to survive serialisation or every pump reads as holding nothing.
    expect($props['pumps'])->toHaveCount(1)
        ->and($props['pumps'][0]['tank']['linked_item']['name'])->toBe('Petrol');
});

test('a standalone credit sale writes credit sale metadata and raises the buyer receivable', function () {
    $f = standaloneFuelSaleFixture();
    $item = Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();

    app(FuelSaleService::class)->createSale([
        'sale_type' => 'credit',
        'item_id' => $item->id,
        'quantity' => 40,
        'sale_date' => '2026-09-15',
        'customer_id' => $f['customer']->id,
    ]);

    $invoice = Invoice::where('company_id', $f['company']->id)->sole();
    expect($invoice->customer_id)->toBe($f['customer']->id)
        ->and($invoice->invoice_date->toDateString())->toBe('2026-09-15')
        ->and((float) $invoice->balance)->toBeGreaterThan(0.0);

    $metadata = SaleMetadata::where('company_id', $f['company']->id)->sole();
    expect($metadata->sale_type)->toBe(SaleMetadata::TYPE_CREDIT);
});

test('a standalone credit sale is imported into the close for its own business date', function () {
    $f = standaloneFuelSaleFixture();
    $item = Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();

    app(FuelSaleService::class)->createSale([
        'sale_type' => 'credit',
        'item_id' => $item->id,
        'quantity' => 40,
        'sale_date' => '2026-09-15',
        'customer_id' => $f['customer']->id,
    ]);

    $invoice = Invoice::where('company_id', $f['company']->id)->sole();

    $response = test()->actingAs($f['user'])->get("/{$f['company']->slug}/fuel/daily-close?date=2026-09-15");
    $response->assertOk()->assertInertia(fn ($page) => $page->has('pendingFuelInvoices', 1));

    $pending = $response->viewData('page')['props']['pendingFuelInvoices'];
    expect($pending[0]['invoice_id'])->toBe($invoice->id)
        ->and((float) $pending[0]['amount'])->toBe((float) $invoice->total_amount)
        ->and((float) $pending[0]['litres'])->toBe(40.0);
});

test('a sale dated to another day is not imported into this close', function () {
    $f = standaloneFuelSaleFixture();
    $item = Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();

    app(FuelSaleService::class)->createSale([
        'sale_type' => 'credit',
        'item_id' => $item->id,
        'quantity' => 40,
        'sale_date' => '2026-09-14',
        'customer_id' => $f['customer']->id,
    ]);

    test()->actingAs($f['user'])
        ->get("/{$f['company']->slug}/fuel/daily-close?date=2026-09-15")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('pendingFuelInvoices', 0));
});

test('a ten litre cash sale without a customer succeeds and reuses a tenant walk-in customer', function (string $saleType) {
    $f = standaloneFuelSaleFixture();
    $item = Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();
    $payload = [
        'sale_type' => $saleType, 'item_id' => $item->id, 'quantity' => 10,
        'sale_date' => '2026-09-19', 'customer_id' => null,
        'discount_per_liter' => $saleType === SaleMetadata::TYPE_BULK ? 5 : null,
    ];
    $url = "/{$f['company']->slug}/fuel/sales";
    $this->from("/{$f['company']->slug}/fuel/sales/form")->post($url, $payload)
        ->assertRedirect()->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Fuel sale recorded successfully.');

    $invoice = Invoice::where('company_id', $f['company']->id)->sole();
    $expected = $saleType === SaleMetadata::TYPE_BULK ? 2950.0 : 3000.0;
    expect($invoice->customer->company_id)->toBe($f['company']->id)
        ->and($invoice->customer->customer_number)->toBe('CASH-FUEL')
        ->and($invoice->customer->base_currency)->toBe('PKR')
        ->and($invoice->status)->toBe('paid')
        ->and((float) $invoice->paid_amount)->toBe($expected)
        ->and((float) $invoice->balance)->toBe(0.0);
    $this->post($url, $payload)->assertRedirect()->assertSessionHas('success');
    expect(Invoice::where('company_id', $f['company']->id)->distinct()->pluck('customer_id')->all())
        ->toBe([$invoice->customer_id]);
})->with([SaleMetadata::TYPE_RETAIL, SaleMetadata::TYPE_BULK]);

test('a cash sale preserves a selected customer', function () {
    $f = standaloneFuelSaleFixture();
    $item = Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();
    $invoice = app(FuelSaleService::class)->createSale([
        'sale_type' => SaleMetadata::TYPE_RETAIL, 'item_id' => $item->id,
        'quantity' => 10, 'customer_id' => $f['customer']->id,
    ]);
    expect($invoice->customer_id)->toBe($f['customer']->id);
});

test('a blocked buyer is refused and no invoice is created', function () {
    $f = standaloneFuelSaleFixture();
    $item = Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();
    $f['customer']->update(['is_credit_blocked' => true]);

    expect(fn () => app(FuelSaleService::class)->createSale([
        'sale_type' => 'credit',
        'item_id' => $item->id,
        'quantity' => 40,
        'sale_date' => '2026-09-15',
        'customer_id' => $f['customer']->id,
    ]))->toThrow(\InvalidArgumentException::class, 'blocked from further credit sales');

    expect(Invoice::where('company_id', $f['company']->id)->count())->toBe(0);
});
