<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Vendor;
use Illuminate\Support\Facades\DB;

/*
 * The record, document and lookup commands against /api/palette/resolve and /api/palette/catalog.
 * Nothing here posts: resolve only reads.
 */

function coreResolve($test, array $f, string $command, array $slots = []): array
{
    $body = [];
    foreach ($slots as $name => $slot) {
        $body[$name] = is_array($slot) ? $slot : ['text' => $slot];
    }

    return $test->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)
        ->postJson('/api/palette/resolve', ['command' => $command, 'slots' => $body])->assertOk()->json();
}

function coreSentences($test, array $f, string $as = 'owner'): array
{
    return collect($test->actingAs($f[$as])->withHeader('X-Company-Slug', $f['company']->slug)
        ->getJson('/api/palette/catalog')->assertOk()->json('sentences'))->keyBy('name')->all();
}

function coreBill(array $f, Vendor $vendor, string $no): Bill
{
    return Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $vendor->id, 'bill_number' => $no, 'bill_date' => '2026-10-01',
        'due_date' => '2026-10-01', 'status' => 'received', 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => 100, 'total_amount' => 100, 'paid_amount' => 0, 'balance' => 100,
    ]);
}

test('add vendor and add customer are ready with the name alone', function () {
    $f = paletteFixture(false);

    $v = coreResolve($this, $f, 'add vendor', ['name' => ['text' => 'Ali']]);
    expect($v['status'])->toBe('ready')
        ->and($v['key'])->toBe('vendor.create')
        ->and($v['params'])->toBe(['name' => 'Ali'])
        ->and($v['card'])->toBe(['what' => 'New vendor', 'who' => 'Ali', 'currency' => 'PKR']);

    $c = coreResolve($this, $f, 'add customer', ['name' => ['text' => 'Hira Stores']]);
    expect($c['status'])->toBe('ready')
        ->and($c['key'])->toBe('customer.create')
        ->and($c['card']['what'])->toBe('New customer');
});

test('add vendor without a name is pending', function () {
    $f = paletteFixture(false);

    $r = coreResolve($this, $f, 'add vendor');

    expect($r['status'])->toBe('pending')->and($r['slots']['name']['state'])->toBe('missing');
});

test('invoice, bill and credit open their create pages; add item and add account open forms', function () {
    $f = paletteFixture(false);
    $s = coreSentences($this, $f);
    $slug = $f['company']->slug;

    expect($s['invoice']['form_url'])->toBe("/{$slug}/invoices/create")
        ->and($s['invoice']['aliases'])->toBe(['inv'])
        ->and($s['invoice']['sentence'])->toBe('Invoice {customer?}')
        ->and($s['bill']['form_url'])->toBe("/{$slug}/bills/create")
        ->and($s['credit']['form_url'])->toBe("/{$slug}/credit-notes/create")
        ->and($s['add account']['form_url'])->toBe("/{$slug}/accounts/create")
        ->and($s['add item']['form_url'])->toBe("/{$slug}/items/create")
        ->and($s['add vendor']['opens'])->toBe('/{company}/vendors/{id}')
        ->and($s['void invoice']['key'])->toBe('invoice.void')
        ->and($s['void bill']['key'])->toBe('bill.void');
});

test('balance is a query that shows the account balance and never executes', function () {
    $f = paletteFixture(false);
    $cid = $f['company']->id;
    $fy = FiscalYear::create(['company_id' => $cid, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    $period = AccountingPeriod::create(['company_id' => $cid, 'fiscal_year_id' => $fy->id, 'name' => 'October', 'period_number' => 10, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']);
    $make = fn (string $code, string $name, string $type, string $subtype, string $normal) => Account::create([
        'company_id' => $cid, 'code' => $code, 'name' => $name, 'type' => $type, 'subtype' => $subtype,
        'normal_balance' => $normal, 'currency' => 'PKR', 'is_active' => true,
    ]);
    $bank = $make('1060', 'Meezan Bank', 'asset', 'bank', 'debit');
    $sales = $make('4000', 'Sales', 'revenue', 'revenue', 'credit');

    $tx = (string) str()->uuid();
    DB::table('acct.transactions')->insert([
        'id' => $tx, 'company_id' => $cid, 'transaction_number' => 'T-'.str()->random(6),
        'fiscal_year_id' => $fy->id, 'period_id' => $period->id, 'transaction_type' => 'manual',
        'transaction_date' => '2026-10-05', 'posting_date' => '2026-10-05', 'description' => 'x',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'total_debit' => 7500, 'total_credit' => 7500, 'status' => 'posted',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ([[$bank, 7500, 0], [$sales, 0, 7500]] as $i => [$a, $d, $c]) {
        DB::table('acct.journal_entries')->insert([
            'id' => (string) str()->uuid(), 'company_id' => $cid, 'transaction_id' => $tx, 'account_id' => $a->id,
            'line_number' => $i + 1, 'debit_amount' => $d, 'credit_amount' => $c, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $r = coreResolve($this, $f, 'balance', ['account' => 'meezan']);

    expect($r['status'])->toBe('ready')
        ->and($r['card'])->toEqual(['what' => 'Meezan Bank', 'amount' => 7500, 'currency' => 'PKR'])
        ->and(coreSentences($this, $f)['balance']['query'])->toBeTrue();

    expect(coreResolve($this, $f, 'balance', ['account' => 'nothing here'])['slots']['account']['state'])->toBe('not_found');
});

test('report lists the report pages and gives the page to open', function () {
    $f = paletteFixture(false);
    $slug = $f['company']->slug;

    $s = coreSentences($this, $f)['report'];
    expect($s['query'])->toBeTrue()
        ->and(in_array('Balance sheet', $s['slots'][0]['options'], true))->toBeTrue();

    $r = coreResolve($this, $f, 'report', ['report' => 'balance sheet']);
    expect($r['status'])->toBe('ready')
        ->and($r['params'])->toBe(['url' => "/{$slug}/reports/balance-sheet"])
        ->and($r['card']['what'])->toBe('Balance sheet');

    $many = coreResolve($this, $f, 'report', ['report' => 'aging']);
    expect($many['status'])->toBe('pending')
        ->and($many['slots']['report']['state'])->toBe('choices')
        ->and(array_column($many['slots']['report']['choices'], 'label'))->toBe(['Receivables aging', 'Payables aging']);

    expect(coreResolve($this, $f, 'report', ['report' => 'zzz'])['slots']['report']['state'])->toBe('not_found');
});

test('void bill names the bill and does not run', function () {
    $f = paletteFixture(false);
    $vendor = Vendor::create(['company_id' => $f['company']->id, 'vendor_number' => 'V-1', 'name' => 'Ali Traders', 'base_currency' => 'PKR']);
    $bill = coreBill($f, $vendor, 'BILL-0001');

    $r = coreResolve($this, $f, 'void bill', ['bill' => 'BILL-0001']);

    expect($r['status'])->toBe('ready')
        ->and($r['key'])->toBe('bill.void')
        ->and($r['params'])->toBe(['id' => $bill->id])
        ->and($r['card']['what'])->toBe('Void')
        ->and($r['card']['who'])->toBe('BILL-0001');

    $picked = coreResolve($this, $f, 'void bill', ['bill' => ['text' => 'bill', 'value' => $bill->id]]);
    expect($picked['status'])->toBe('ready')->and($picked['params']['id'])->toBe($bill->id);

    expect(coreResolve($this, $f, 'void invoice', ['invoice' => 'INV-404'])['slots']['invoice']['state'])->toBe('not_found');
});

test('invoice takes an optional customer search', function () {
    $f = paletteFixture(false);
    Customer::create(['company_id' => $f['company']->id, 'customer_number' => 'C-1', 'name' => 'Hira Stores', 'base_currency' => 'PKR', 'is_active' => true]);

    $sentence = coreSentences($this, $f)['invoice'];

    expect($sentence['slots'])->toBe([['name' => 'customer', 'kind' => 'search', 'optional' => true, 'entity' => 'customer']]);
});

test('a role that may not create vendors or accounts is not offered those commands', function () {
    $f = paletteFixture(false, 'operations');

    $s = coreSentences($this, $f, 'other');

    expect($s)->not->toHaveKey('add vendor')->not->toHaveKey('add account');
});

test('add item is offered only to a company with the inventory module', function () {
    $f = paletteFixture(false);
    expect(coreSentences($this, $f))->toHaveKey('add item');

    $company = $f['company'];
    $company->settings = array_merge((array) $company->settings, ['modules' => ['inventory' => false]]);
    $company->save();

    expect(coreSentences($this, $f))->not->toHaveKey('add item');
    $this->actingAs($f['owner'])->withHeader('X-Company-Slug', $company->slug)
        ->postJson('/api/palette/resolve', ['command' => 'add item', 'slots' => []])->assertNotFound();
});
