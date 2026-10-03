<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\OpeningStockLedgerService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * One way to record an item's opening stock (Items -> New item and the fuel quick add share it),
 * and a delete that takes a never-used item's opening stock with it.
 */
function itemOpeningFixture(string $industry = 'retail'): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Lube Station', 'slug' => 'lube-'.str()->lower(str()->random(8)),
        'owner_id' => $owner->id, 'base_currency' => 'PKR', 'industry_code' => $industry,
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
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $stock = Account::create(['company_id' => $company->id, 'code' => '1250', 'name' => 'Lubricant stock', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit', 'is_active' => true]);

    return compact('owner', 'company', 'stock');
}

function itemOpeningPayload(array $f, array $extra = []): array
{
    return array_merge([
        'sku' => 'LUB-1L', 'name' => 'Engine oil 1L', 'item_type' => 'product', 'unit_of_measure' => 'bottle',
        'track_inventory' => true, 'delivery_mode' => 'requires_receiving', 'is_purchasable' => true, 'is_sellable' => true,
        'cost_price' => 500, 'selling_price' => 650, 'currency' => 'PKR', 'asset_account_id' => $f['stock']->id,
        'opening_quantity' => 10, 'opening_unit_cost' => 500, 'opening_date' => '2026-09-15',
    ], $extra);
}

function itemOpeningJournal(array $f): float
{
    return (float) JournalEntry::join('acct.transactions as t', 't.id', '=', 'acct.journal_entries.transaction_id')
        ->whereNull('t.deleted_at')->where('t.reference_type', OpeningStockLedgerService::REFERENCE)
        ->where('acct.journal_entries.account_id', $f['stock']->id)
        ->sum(DB::raw('acct.journal_entries.debit_amount - acct.journal_entries.credit_amount'));
}

test('item created with opening stock records one opening movement, its stock level and the journal', function () {
    $f = itemOpeningFixture();

    $this->actingAs($f['owner'])->post("/{$f['company']->slug}/items", itemOpeningPayload($f))->assertRedirect();

    $item = Item::where('company_id', $f['company']->id)->where('sku', 'LUB-1L')->firstOrFail();
    $movements = StockMovement::where('item_id', $item->id)->get();

    expect($movements)->toHaveCount(1)
        ->and($movements[0]->movement_type)->toBe('opening')
        ->and((float) $movements[0]->quantity)->toBe(10.0)
        ->and((float) $movements[0]->total_cost)->toBe(5000.0)
        ->and((float) StockLevel::where('item_id', $item->id)->sum('quantity'))->toBe(10.0)
        ->and(itemOpeningJournal($f))->toBe(5000.0);
});

test('item created without opening stock records no movement', function () {
    $f = itemOpeningFixture();

    $this->actingAs($f['owner'])->post("/{$f['company']->slug}/items", itemOpeningPayload($f, ['opening_quantity' => '']))->assertRedirect();

    $item = Item::where('sku', 'LUB-1L')->firstOrFail();
    expect(StockMovement::where('item_id', $item->id)->count())->toBe(0);
});

test('the fuel quick add records packaged opening stock the same way', function () {
    $f = itemOpeningFixture('fuel_station');

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('fuel.products.setup', [
        'effective_date' => '2026-09-15',
        'products' => [[
            'type' => 'lubricant', 'name' => 'Engine oil 1L', 'sku' => 'LUB-QA', 'lubricant_format' => 'packaged',
            'purchase_rate' => 500, 'sale_rate' => 650, 'opening_quantity' => 10,
        ]],
    ], $f['owner'], true));

    $item = Item::where('company_id', $f['company']->id)->where('sku', 'LUB-QA')->firstOrFail();
    $movement = StockMovement::where('item_id', $item->id)->firstOrFail();

    expect($movement->movement_type)->toBe('opening')
        ->and((float) $movement->quantity)->toBe(10.0)
        ->and((float) $movement->total_cost)->toBe(5000.0)
        ->and((float) StockLevel::where('item_id', $item->id)->sum('quantity'))->toBe(10.0)
        ->and((float) $item->cost_price)->toBe(500.0)
        ->and((float) $item->selling_price)->toBe(650.0)
        ->and(Transaction::where('company_id', $f['company']->id)->where('reference_type', OpeningStockLedgerService::REFERENCE)->count())->toBe(1);
});

test('unused item with opening stock deletes with its movement, stock level and journal', function () {
    $f = itemOpeningFixture();
    $this->actingAs($f['owner'])->post("/{$f['company']->slug}/items", itemOpeningPayload($f))->assertRedirect();
    $item = Item::where('sku', 'LUB-1L')->firstOrFail();
    expect(itemOpeningJournal($f))->toBe(5000.0);

    $this->actingAs($f['owner'])->delete("/{$f['company']->slug}/items/{$item->id}")->assertRedirect();

    expect(Item::find($item->id))->toBeNull()
        ->and(StockMovement::where('item_id', $item->id)->count())->toBe(0)
        ->and((float) StockLevel::where('item_id', $item->id)->sum('quantity'))->toBe(0.0)
        ->and(itemOpeningJournal($f))->toBe(0.0)
        ->and(Transaction::where('company_id', $f['company']->id)->where('reference_type', OpeningStockLedgerService::REFERENCE)->whereNull('deleted_at')->count())->toBe(0);
});

test('item that was used is refused and kept', function () {
    $f = itemOpeningFixture();
    $this->actingAs($f['owner'])->post("/{$f['company']->slug}/items", itemOpeningPayload($f))->assertRedirect();
    $item = Item::where('sku', 'LUB-1L')->firstOrFail();
    $warehouseId = StockMovement::where('item_id', $item->id)->value('warehouse_id');
    StockMovement::create([
        'company_id' => $f['company']->id, 'warehouse_id' => $warehouseId, 'item_id' => $item->id,
        'movement_date' => '2026-09-16', 'movement_type' => 'sale', 'quantity' => -2, 'unit_cost' => 500, 'total_cost' => -1000,
    ]);

    $this->actingAs($f['owner'])->delete("/{$f['company']->slug}/items/{$item->id}")
        ->assertRedirect()
        ->assertSessionHas('error', 'Used in sales or purchases. Deactivate it instead.');

    expect(Item::find($item->id))->not->toBeNull()
        ->and(StockMovement::where('item_id', $item->id)->count())->toBe(2)
        ->and((float) StockLevel::where('item_id', $item->id)->sum('quantity'))->toBe(8.0);
});
