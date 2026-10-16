<?php

use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Vendor;

require_once __DIR__.'/../Accounting/CashSaleFixtures.php';

function defaultsGet($test, array $f, string $path)
{
    return $test->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)->getJson($path);
}

test('defaults give the company date, the default accounts and the lists names are matched against', function () {
    $f = cashSaleFixture();
    $f['company']->bank_account_id = $f['bank2']->id;
    $f['company']->save();

    $res = defaultsGet($this, $f, '/api/palette/defaults')->assertOk();

    expect($res->json('today'))->toBe($f['today']);
    expect($res->json('currency'))->toBe('PKR');
    expect($res->json('default_bank_id'))->toBe($f['bank2']->id);
    expect($res->json('default_cash_id'))->toBe($f['cash']->id);
    expect($res->json('bank_charges_account_id'))->toBe($f['charges']->id);
    expect(collect($res->json('banks'))->pluck('name')->all())->toBe(['Meezan Bank', 'HBL Bank']);
    expect(collect($res->json('cash'))->pluck('name')->all())->toBe(['Cash on Hand']);
    expect(collect($res->json('expense'))->pluck('code')->all())->toBe(['6200', '6520']);
});

test('the default bank falls back to the first bank when the company default is not one', function () {
    $f = cashSaleFixture();
    $f['company']->bank_account_id = $f['cash']->id;
    $f['company']->save();

    expect(defaultsGet($this, $f, '/api/palette/defaults')->json('default_bank_id'))->toBe($f['bank']->id);
});

test('open bills list a supplier\'s unpaid bills oldest first', function () {
    $f = cashSaleFixture();
    $vendor = Vendor::create(['company_id' => $f['company']->id, 'vendor_number' => 'V-1', 'name' => 'Karachi Office Supplies', 'base_currency' => 'PKR']);
    $make = fn (string $no, string $date, string $status, float $balance) => Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $vendor->id, 'bill_number' => $no, 'bill_date' => $date,
        'due_date' => $date, 'status' => $status, 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => $balance, 'total_amount' => $balance, 'paid_amount' => 0, 'balance' => $balance,
    ]);
    $make('B-2', '2026-09-05', 'received', 700);
    $make('B-1', '2026-09-01', 'partial', 300);
    $make('B-3', '2026-09-09', 'paid', 0);
    $make('B-4', '2026-09-10', 'draft', 100);

    $res = defaultsGet($this, $f, "/api/palette/open-bills?vendor_id={$vendor->id}")->assertOk();

    expect(collect($res->json('bills'))->pluck('bill_number')->all())->toBe(['B-1', 'B-2']);
    expect(collect($res->json('bills'))->pluck('balance')->all())->toBe([300, 700]);
});

test('open bills need a vendor id', function () {
    $f = cashSaleFixture();

    defaultsGet($this, $f, '/api/palette/open-bills')->assertStatus(422);
});
