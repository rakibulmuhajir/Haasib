<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\FuelNavigationAccess;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Products & stock (/fuel/products): one row per sellable product -- fuels on hand as the tank dip
 * of the latest live close, everything else from the stock ledger, each flagged low or not.
 */
function stationProductsFixture(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Products Station', 'slug' => 'products-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id, 'base_currency' => 'PKR',
    ]);
    if (! DB::table('public.currencies')->where('code', 'PKR')->exists()) {
        DB::table('public.currencies')->insert(['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs']);
    }
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    enterCompany($company);
    $company->enableModule('fuel_station');

    return compact('company', 'user');
}

function stationProductsClose(Company $company, string $date, array $tanks, ?string $reversedBy = null, string $type = 'fuel_daily_close'): Transaction
{
    $fy = FiscalYear::firstOrCreate(['company_id' => $company->id, 'name' => '2026'], ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    $day = \Carbon\Carbon::parse($date);
    $period = AccountingPeriod::firstOrCreate(
        ['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'period_number' => $day->month],
        ['name' => $day->format('F'), 'start_date' => $day->copy()->startOfMonth()->toDateString(), 'end_date' => $day->copy()->endOfMonth()->toDateString()]
    );

    return Transaction::create([
        'company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'period_id' => $period->id, 'base_currency' => 'PKR',
        'transaction_number' => 'DC-'.$date.'-'.str()->random(4),
        'transaction_type' => $type, 'transaction_date' => $date, 'posting_date' => $date,
        'description' => 'Daily close '.$date, 'currency' => 'PKR', 'total_debit' => 0, 'total_credit' => 0,
        'status' => 'posted', 'reversed_by_id' => $reversedBy,
        'metadata' => ['posting_snapshot' => ['tanks' => $tanks]],
    ]);
}

test('products and stock list a fuel on its tank dip and a pack on its stock level, low flagged', function () {
    $f = stationProductsFixture();
    $companyId = $f['company']->id;

    $petrol = Item::create(['company_id' => $companyId, 'sku' => 'PETROL', 'name' => 'Petrol', 'item_type' => 'product', 'fuel_category' => 'petrol',
        'unit_of_measure' => 'liter', 'currency' => 'PKR', 'track_inventory' => true, 'is_sellable' => true, 'avg_cost' => 250, 'selling_price' => 300]);
    $tank = Warehouse::create(['company_id' => $companyId, 'code' => 'T1', 'name' => 'Petrol tank', 'warehouse_type' => 'tank',
        'linked_item_id' => $petrol->id, 'capacity' => 10000, 'low_level_alert' => 500]);
    stationProductsClose($f['company'], '2026-09-14', [['tank_id' => $tank->id, 'item_id' => $petrol->id, 'physical_liters' => 9000]]);
    stationProductsClose($f['company'], '2026-09-15', [['tank_id' => $tank->id, 'item_id' => $petrol->id, 'physical_liters' => 4000]]);
    // A reversed close does not count, even though it is the newest.
    $reversal = stationProductsClose($f['company'], '2026-09-16', [], null, 'reversal');
    stationProductsClose($f['company'], '2026-09-16', [['tank_id' => $tank->id, 'item_id' => $petrol->id, 'physical_liters' => 1]], $reversal->id);

    $shop = Warehouse::create(['company_id' => $companyId, 'code' => 'SHOP', 'name' => 'Shop']);
    $oil = Item::create(['company_id' => $companyId, 'sku' => 'OIL-1L', 'name' => 'Engine oil 1L', 'item_type' => 'product',
        'unit_of_measure' => 'pack', 'currency' => 'PKR', 'track_inventory' => true, 'is_sellable' => true, 'avg_cost' => 1000, 'selling_price' => 1200, 'reorder_point' => 10]);
    StockLevel::create(['company_id' => $companyId, 'warehouse_id' => $shop->id, 'item_id' => $oil->id, 'quantity' => 4]);

    // Not shown: not for sale, and deleted.
    Item::create(['company_id' => $companyId, 'sku' => 'RAW', 'name' => 'Raw stock', 'item_type' => 'product', 'unit_of_measure' => 'unit', 'currency' => 'PKR', 'is_sellable' => false]);
    Item::create(['company_id' => $companyId, 'sku' => 'GONE', 'name' => 'Gone', 'item_type' => 'product', 'unit_of_measure' => 'unit', 'currency' => 'PKR', 'is_sellable' => true])->delete();

    test()->actingAs($f['user'])
        ->get("/{$f['company']->slug}/fuel/products")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FuelStation/Products/Index')
            ->where('company.slug', $f['company']->slug)
            ->has('rows', 2)
            ->where('summary.total', 2)
            ->where('summary.low', 1)
            ->where('rows', function ($rows) use ($petrol, $oil) {
                $rows = collect($rows)->keyBy('id');
                $p = $rows[$petrol->id];
                $o = $rows[$oil->id];

                return $p['type'] === 'fuel'
                    && abs($p['on_hand'] - 4000) < 0.001
                    && abs($p['percent_full'] - 40) < 0.001
                    && abs($p['margin'] - 50) < 0.001
                    && abs($p['value'] - 1000000) < 0.01
                    && $p['low'] === false
                    && $o['type'] === 'pack'
                    && abs($o['on_hand'] - 4) < 0.001
                    && $o['percent_full'] === null
                    && $o['low'] === true;
            }));
});

test('a tank below its low alert is flagged low', function () {
    $f = stationProductsFixture();
    $diesel = Item::create(['company_id' => $f['company']->id, 'sku' => 'DIESEL', 'name' => 'Diesel', 'item_type' => 'product', 'fuel_category' => 'diesel',
        'unit_of_measure' => 'liter', 'currency' => 'PKR', 'track_inventory' => true, 'is_sellable' => true, 'avg_cost' => 280, 'selling_price' => 310]);
    $tank = Warehouse::create(['company_id' => $f['company']->id, 'code' => 'T2', 'name' => 'Diesel tank', 'warehouse_type' => 'tank',
        'linked_item_id' => $diesel->id, 'capacity' => 10000, 'low_level_alert' => 500]);
    stationProductsClose($f['company'], '2026-09-15', [['tank_id' => $tank->id, 'item_id' => $diesel->id, 'physical_liters' => 300]]);

    test()->actingAs($f['user'])
        ->get("/{$f['company']->slug}/fuel/products")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.low', 1)
            ->where('rows.0.low', true)
            ->where('rows.0.low_level', fn ($v) => abs($v - 500) < 0.001));
});

test('products and stock replace stock overview in the fuel menu access', function () {
    $f = stationProductsFixture();

    $allowed = app(CompanyContextService::class)->withContext($f['company'], fn () => app(FuelNavigationAccess::class)->forUser($f['company'], $f['user'])['allowed']);

    expect($allowed)->toContain('products')->toContain('stock');
});
