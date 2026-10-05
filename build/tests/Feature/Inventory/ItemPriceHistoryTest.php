<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemPrice;
use App\Modules\Inventory\Models\ItemPriceChange;
use App\Modules\Inventory\Services\ItemPriceService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Price history for products: the latest entry on or before a day is its price, one entry per
 * date, locked months are untouchable, every change is on the trail.
 */
function itemPriceFixture(): array
{
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));

    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Price Station', 'slug' => 'price-'.str()->lower(str()->random(8)),
        'owner_id' => $owner->id, 'base_currency' => 'PKR', 'industry_code' => 'retail',
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $owner->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($owner, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    if (! DB::table('public.currencies')->where('code', 'PKR')->exists()) {
        DB::table('public.currencies')->insert(['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs']);
    }

    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    $period = AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'Year', 'period_number' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

    $item = Item::create([
        'company_id' => $company->id, 'sku' => 'LUB-1L', 'name' => 'Engine oil 1L', 'item_type' => 'product',
        'unit_of_measure' => 'bottle', 'currency' => 'PKR', 'cost_price' => 50, 'selling_price' => 90,
    ]);

    return ['owner' => $owner, 'company' => $company, 'item' => $item, 'fy' => $fy, 'period' => $period];
}

function itemPriceSave(array $f, string $date, float $sale, ?float $purchase = null): ItemPrice
{
    return app(ItemPriceService::class)->save($f['company']->id, $f['item']->id, $date, $sale, $purchase, null, $f['owner']->id);
}

function itemPriceLockMonth(array $f, string $date): void
{
    Transaction::create([
        'company_id' => $f['company']->id, 'fiscal_year_id' => $f['fy']->id, 'period_id' => $f['period']->id,
        'transaction_number' => 'DC-'.str()->random(8), 'transaction_type' => 'fuel_daily_close',
        'transaction_date' => $date, 'posting_date' => $date, 'description' => 'Daily close',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'total_debit' => 0, 'total_credit' => 0,
        'status' => 'posted', 'is_locked' => true, 'lock_reason' => 'month_end', 'metadata' => [],
    ]);
}

test('the price on a day is the latest entry dated on or before it', function () {
    $f = itemPriceFixture();
    itemPriceSave($f, '2026-09-01', 100);
    itemPriceSave($f, '2026-09-10', 120);
    $at = fn (string $d) => app(ItemPriceService::class)->inForce($f['company']->id, $f['item']->id, $d)?->sale_price;

    expect($at('2026-08-31'))->toBeNull()
        ->and((float) $at('2026-09-01'))->toBe(100.0)
        ->and((float) $at('2026-09-09'))->toBe(100.0)
        ->and((float) $at('2026-09-10'))->toBe(120.0)
        ->and((float) $at('2026-10-30'))->toBe(120.0);
});

test('saving the same date replaces the entry', function () {
    $f = itemPriceFixture();
    $first = itemPriceSave($f, '2026-09-10', 100);
    $second = itemPriceSave($f, '2026-09-10', 110, 70);

    expect(ItemPrice::where('item_id', $f['item']->id)->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and((float) $first->fresh()->sale_price)->toBe(110.0)
        ->and((float) $first->fresh()->purchase_price)->toBe(70.0);
});

test('items.selling_price follows the entry in force today', function () {
    $f = itemPriceFixture(); // today is 15 Sep
    $item = $f['item'];

    itemPriceSave($f, '2026-09-01', 100, 60);
    expect((float) $item->fresh()->selling_price)->toBe(100.0)->and((float) $item->fresh()->cost_price)->toBe(60.0);

    // A future entry does not apply yet.
    itemPriceSave($f, '2026-09-20', 150, 80);
    expect((float) $item->fresh()->selling_price)->toBe(100.0);

    // A later entry in force replaces it; with no reference purchase price, cost stays.
    $mid = itemPriceSave($f, '2026-09-12', 130);
    expect((float) $item->fresh()->selling_price)->toBe(130.0)->and((float) $item->fresh()->cost_price)->toBe(60.0);

    // Deleting it hands the price back to the one before.
    app(ItemPriceService::class)->delete($f['company']->id, $mid->id, $f['owner']->id);
    expect((float) $item->fresh()->selling_price)->toBe(100.0);
});

test('a locked month refuses add, change and delete', function () {
    $f = itemPriceFixture();
    $existing = itemPriceSave($f, '2026-08-10', 90);
    itemPriceLockMonth($f, '2026-08-31');

    expect(fn () => itemPriceSave($f, '2026-08-20', 95))->toThrow(ValidationException::class);
    expect(fn () => itemPriceSave($f, '2026-08-10', 99))->toThrow(ValidationException::class);
    expect(fn () => app(ItemPriceService::class)->delete($f['company']->id, $existing->id, $f['owner']->id))
        ->toThrow(ValidationException::class);
    expect((float) $existing->fresh()->sale_price)->toBe(90.0)
        ->and(ItemPrice::where('item_id', $f['item']->id)->count())->toBe(1);

    // The same refusal reaches the person through the page, in words, and September is still open.
    $this->actingAs($f['owner'])->post("/{$f['company']->slug}/items/{$f['item']->id}/prices", [
        'effective_date' => '2026-08-25', 'sale_price' => 95,
    ])->assertSessionHasErrors(['effective_date' => 'August 2026 is locked.']);
    $this->actingAs($f['owner'])->post("/{$f['company']->slug}/items/{$f['item']->id}/prices", [
        'effective_date' => '2026-09-05', 'sale_price' => 95,
    ])->assertSessionHasNoErrors();
});

test('every add, change and delete is on the trail with who and old to new', function () {
    $f = itemPriceFixture();
    $entry = itemPriceSave($f, '2026-09-10', 100, 60);
    itemPriceSave($f, '2026-09-10', 110, 65);
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    app(CommandBus::class)->dispatch('item_price.delete', ['price_id' => $entry->id, 'user_id' => $f['owner']->id], $f['owner'], true);

    $log = ItemPriceChange::where('item_id', $f['item']->id)->orderBy('changed_at')->orderBy('action')->get()->keyBy('action');
    expect($log)->toHaveCount(3)
        ->and($log['created']->old_sale_price)->toBeNull()
        ->and((float) $log['created']->new_sale_price)->toBe(100.0)
        ->and((float) $log['updated']->old_sale_price)->toBe(100.0)
        ->and((float) $log['updated']->new_sale_price)->toBe(110.0)
        ->and((float) $log['updated']->old_purchase_price)->toBe(60.0)
        ->and((float) $log['updated']->new_purchase_price)->toBe(65.0)
        ->and((float) $log['deleted']->old_sale_price)->toBe(110.0)
        ->and($log['deleted']->new_sale_price)->toBeNull()
        ->and($log['deleted']->changed_by_user_id)->toBe($f['owner']->id)
        ->and($log['deleted']->effective_date->toDateString())->toBe('2026-09-10');

    // The trail cannot be rewritten.
    expect(fn () => ItemPriceChange::where('item_id', $f['item']->id)->update(['new_sale_price' => 1]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('the timeline merges sale prices, a bill line and a month lubricant correction, newest first', function () {
    $f = itemPriceFixture();
    $cid = $f['company']->id;
    itemPriceSave($f, '2026-09-01', 100);
    itemPriceSave($f, '2026-09-25', 140); // future: not in force

    $ap = Account::create(['company_id' => $cid, 'code' => '2000', 'name' => 'AP', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_active' => true]);
    $vendor = Vendor::create(['company_id' => $cid, 'vendor_number' => 'V-1', 'name' => 'Lube Depot', 'base_currency' => 'PKR', 'ap_account_id' => $ap->id, 'is_active' => true, 'created_by_user_id' => $f['owner']->id]);
    $bill = Bill::create([
        'company_id' => $cid, 'vendor_id' => $vendor->id, 'bill_number' => 'BILL-77', 'bill_date' => '2026-09-05', 'due_date' => '2026-09-05',
        'status' => 'received', 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => 600, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 600, 'paid_amount' => 0, 'balance' => 600, 'base_amount' => 600,
        'created_by_user_id' => $f['owner']->id,
    ]);
    BillLineItem::create([
        'company_id' => $cid, 'bill_id' => $bill->id, 'line_number' => 1, 'item_id' => $f['item']->id, 'description' => 'Oil',
        'quantity' => 12, 'quantity_received' => 0, 'unit_price' => 50, 'tax_rate' => 0, 'discount_rate' => 0,
        'line_total' => 600, 'tax_amount' => 0, 'total' => 600, 'created_by_user_id' => $f['owner']->id,
    ]);
    Transaction::create([
        'company_id' => $cid, 'fiscal_year_id' => $f['fy']->id, 'period_id' => $f['period']->id,
        'transaction_number' => 'LUB-'.str()->random(6), 'transaction_type' => 'fuel_lubricant_cost',
        'transaction_date' => '2026-08-31', 'posting_date' => '2026-08-31', 'description' => 'Lubricant cost Aug',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'total_debit' => 0, 'total_credit' => 0, 'status' => 'posted',
        'metadata' => ['month' => '2026-08', 'lines' => [
            ['item_id' => $f['item']->id, 'name' => 'Engine oil 1L', 'quantity' => 4, 'unit_cost' => 48, 'cost' => 192],
            ['item_id' => (string) str()->uuid(), 'name' => 'Other', 'quantity' => 1, 'unit_cost' => 9, 'cost' => 9],
        ]],
    ]);

    $rows = app(ItemPriceService::class)->timeline($f['item']->fresh(), $f['company']->slug);

    expect(array_column($rows, 'kind'))->toBe(['sale_price', 'purchase_bill', 'sale_price', 'month_correction']);

    $byKind = collect($rows)->groupBy('kind');
    expect(array_column($rows, 'date'))->toBe(['2026-09-25', '2026-09-05', '2026-09-01', '2026-08-31'])
        ->and($byKind['purchase_bill'][0]['price'])->toBe(50.0)
        ->and($byKind['purchase_bill'][0]['quantity'])->toBe(12.0)
        ->and($byKind['purchase_bill'][0]['source_label'])->toBe('BILL-77')
        ->and($byKind['purchase_bill'][0]['source_url'])->toBe("/{$f['company']->slug}/bills/{$bill->id}")
        ->and($byKind['month_correction'][0]['label'])->toBe('Month correction')
        ->and($byKind['month_correction'][0]['price'])->toBe(48.0)
        ->and($byKind['month_correction'][0]['quantity'])->toBe(4.0)
        ->and(collect($rows)->where('in_force', true)->pluck('date')->all())->toBe(['2026-09-01']);
});

test('a fuel item shows its rate changes and offers no price editing', function () {
    $f = itemPriceFixture();
    $f['item']->update(['fuel_category' => 'petrol']);
    RateChange::create(['company_id' => $f['company']->id, 'item_id' => $f['item']->id, 'effective_date' => '2026-09-01', 'purchase_rate' => 250, 'sale_rate' => 270]);
    RateChange::create(['company_id' => $f['company']->id, 'item_id' => $f['item']->id, 'effective_date' => '2026-09-10', 'purchase_rate' => 255, 'sale_rate' => 275]);

    $rows = app(ItemPriceService::class)->timeline($f['item']->fresh(), $f['company']->slug);

    expect(array_column($rows, 'kind'))->toBe(['fuel_rate', 'fuel_rate'])
        ->and(array_column($rows, 'price'))->toBe([275.0, 270.0])
        ->and(array_column($rows, 'in_force'))->toBe([true, false])
        ->and(array_column($rows, 'editable'))->toBe([false, false])
        ->and($rows[0]['source_url'])->toBe("/{$f['company']->slug}/fuel/rates");

    expect(fn () => itemPriceSave($f, '2026-09-12', 300))->toThrow(ValidationException::class);
    expect(ItemPrice::where('item_id', $f['item']->id)->count())->toBe(0);
});

test('the item page lists the price changes newest first with who and old to new', function () {
    $f = itemPriceFixture();
    $slug = $f['company']->slug;
    $f['owner']->update(['name' => 'Tariq Mahmood']);

    $entry = itemPriceSave($f, '2026-10-01', 5100, 4000);
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 10:00:00'));
    itemPriceSave($f, '2026-10-01', 5200, 4100);
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 11:00:00'));
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    app(CommandBus::class)->dispatch('item_price.delete', ['price_id' => $entry->id, 'user_id' => $f['owner']->id], $f['owner'], true);

    $this->actingAs($f['owner'])->get("/{$slug}/items/{$f['item']->id}")->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('priceHistory.changes', 3)
            ->where('priceHistory.changes.0.action', 'deleted')
            ->where('priceHistory.changes.0.text', 'Removed price from 1 Oct (5,200)')
            ->where('priceHistory.changes.1.text', 'Changed price from 1 Oct: 5,100 → 5,200')
            ->where('priceHistory.changes.1.purchase', 'Purchase ref: 4,000 → 4,100')
            ->where('priceHistory.changes.1.old_sale_price', 5100)
            ->where('priceHistory.changes.1.new_sale_price', 5200)
            ->where('priceHistory.changes.2.text', 'Added price from 1 Oct: 5,100')
            ->where('priceHistory.changes.2.user', 'Tariq Mahmood'));
});
