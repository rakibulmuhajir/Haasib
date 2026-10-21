<?php

use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Vendor;

function findResults($test, array $f, string $q, string $as = 'owner'): array
{
    return $test->actingAs($f[$as])->withHeader('X-Company-Slug', $f['company']->slug)
        ->getJson('/api/palette/find?q='.urlencode($q))->assertOk()->json('results');
}

test('find returns the vendor under Vendors with its page', function () {
    $f = paletteFixture(false);
    $vendor = Vendor::create(['company_id' => $f['company']->id, 'vendor_number' => 'V-1', 'name' => 'Ali Traders', 'base_currency' => 'PKR']);

    $results = findResults($this, $f, 'ali');

    expect($results)->toHaveCount(1)
        ->and($results[0]['group'])->toBe('Vendors')
        ->and($results[0]['value'])->toBe($vendor->id)
        ->and($results[0]['label'])->toBe('Ali Traders')
        ->and($results[0]['meta'])->toBe('V-1')
        ->and($results[0]['url'])->toBe("/{$f['company']->slug}/vendors/{$vendor->id}");
});

test('find groups customers, vendors and bills, five at most per group', function () {
    $f = paletteFixture(false);
    $cid = $f['company']->id;
    foreach (range(1, 7) as $i) {
        Customer::create(['company_id' => $cid, 'customer_number' => "C-{$i}", 'name' => "Zed Customer {$i}", 'base_currency' => 'PKR', 'is_active' => true]);
    }
    $vendor = Vendor::create(['company_id' => $cid, 'vendor_number' => 'V-1', 'name' => 'Zed Vendor', 'base_currency' => 'PKR']);
    Bill::create([
        'company_id' => $cid, 'vendor_id' => $vendor->id, 'bill_number' => 'ZED-1', 'bill_date' => '2026-10-01',
        'due_date' => '2026-10-01', 'status' => 'received', 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => 100, 'total_amount' => 100, 'paid_amount' => 0, 'balance' => 100,
    ]);

    $results = collect(findResults($this, $f, 'zed'));

    expect($results->where('group', 'Customers'))->toHaveCount(5)
        ->and($results->where('group', 'Vendors'))->toHaveCount(1)
        ->and($results->where('group', 'Bills')->first()['url'])->toStartWith("/{$f['company']->slug}/bills/")
        ->and($results->pluck('group')->unique()->values()->all())->toBe(['Customers', 'Vendors', 'Bills']);
});

test('find with no text, or nothing matching, is empty', function () {
    $f = paletteFixture(false);

    expect(findResults($this, $f, ''))->toBe([])
        ->and(findResults($this, $f, 'nothing at all'))->toBe([]);
});

test('find does not cross companies', function () {
    $a = paletteFixture(false);
    $b = paletteFixture(false);
    Vendor::create(['company_id' => $b['company']->id, 'vendor_number' => 'V-9', 'name' => 'Ali Elsewhere', 'base_currency' => 'PKR']);
    enterCompany($a['company']);

    expect(findResults($this, $a, 'ali'))->toBe([]);
});

test('find needs sign in', function () {
    $this->getJson('/api/palette/find?q=ali')->assertUnauthorized();
});
