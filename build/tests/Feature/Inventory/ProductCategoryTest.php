<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Actions\Product\SetupAction;
use App\Modules\FuelStation\Services\Calculator\CalculatorContext;
use App\Modules\FuelStation\Services\Calculator\FormulaEvaluator;
use App\Modules\FuelStation\Services\ProductProfitabilityReportService;
use App\Modules\FuelStation\Services\StationProductCategories;
use App\Modules\FuelStation\Services\StockStatementService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../FuelStation/CreditCloseFixtures.php';
require_once __DIR__.'/../FuelStation/CustomerFuelDiscountFixtures.php';

function catNewCategory(string $companyId, string $name): ItemCategory
{
    return ItemCategory::create(['company_id' => $companyId, 'code' => strtoupper($name), 'name' => $name, 'is_active' => true]);
}

/** Petrol (100 L) and Diesel (100 L) both sold on a posted September close; petrol filed apart from diesel. */
function catClosedFixture(): array
{
    $f = discountedCustomerFixture();
    $f['payload']['nozzle_readings'][] = [
        'nozzle_id' => $f['dieselNozzle']->id, 'item_id' => $f['diesel']->id,
        'opening_electronic' => 0, 'closing_electronic' => 100, 'liters_sold' => 100, 'sale_rate' => 300,
    ];
    creditClosePost($f);

    $f['fuelCategory'] = catNewCategory($f['company']->id, 'Diesels');
    $f['otherCategory'] = catNewCategory($f['company']->id, 'Petrols');
    $f['diesel']->update(['category_id' => $f['fuelCategory']->id]);
    $f['petrol']->update(['category_id' => $f['otherCategory']->id]);

    return $f;
}

/** Make the fixture's user a signed-in owner member of its company, so its pages open. */
function catPageFixture(array $f): User
{
    $user = $f['user'];
    $company = $f['company'];
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

    return $user;
}

test('the backfill files fuels under Fuel and lubricants under Lubricant, and leaves a categorised product alone', function () {
    $f = discountedCustomerFixture();
    $company = $f['company'];
    $company->update(['industry_code' => 'fuel_station']);

    $packs = Account::create([
        'company_id' => $company->id, 'code' => '1251', 'name' => 'Lubricant Inventory - Sealed Packs',
        'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit', 'is_active' => true,
    ]);
    $open = Item::create(['company_id' => $company->id, 'sku' => 'LUB-OPEN', 'name' => 'Engine oil drum', 'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR', 'fuel_category' => 'lubricant']);
    $pack = Item::create(['company_id' => $company->id, 'sku' => 'LUB-1L', 'name' => 'Oil 1L', 'item_type' => 'product', 'unit_of_measure' => 'pack', 'currency' => 'PKR', 'asset_account_id' => $packs->id]);
    $named = Item::create(['company_id' => $company->id, 'sku' => 'LUB-2', 'name' => 'Gear lubricant', 'item_type' => 'product', 'unit_of_measure' => 'pack', 'currency' => 'PKR']);
    $shop = Item::create(['company_id' => $company->id, 'sku' => 'SHOP-1', 'name' => 'Biscuits', 'item_type' => 'product', 'unit_of_measure' => 'unit', 'currency' => 'PKR']);
    $mine = catNewCategory($company->id, 'Mine');
    $kept = Item::create(['company_id' => $company->id, 'sku' => 'KEEP', 'name' => 'Kept diesel', 'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR', 'fuel_category' => 'diesel', 'category_id' => $mine->id]);

    $run = fn () => (require base_path('database/migrations/2026_10_05_000002_add_fuel_and_lubricant_categories_to_fuel_stations.php'))->up();
    $run();
    enterCompany($company);

    $fuel = ItemCategory::where('company_id', $company->id)->where('name', 'Fuel')->sole();
    $lubricant = ItemCategory::where('company_id', $company->id)->where('name', 'Lubricant')->sole();

    expect($f['petrol']->fresh()->category_id)->toBe($fuel->id)     // has a tank
        ->and($f['diesel']->fresh()->category_id)->toBe($fuel->id)  // fuel category
        ->and($open->fresh()->category_id)->toBe($lubricant->id)
        ->and($pack->fresh()->category_id)->toBe($lubricant->id)    // lubricant stock account
        ->and($named->fresh()->category_id)->toBe($lubricant->id)   // named so
        ->and($shop->fresh()->category_id)->toBeNull()
        ->and($kept->fresh()->category_id)->toBe($mine->id);

    // Running it again changes nothing and adds no second pair.
    $run();
    enterCompany($company);
    expect(ItemCategory::where('company_id', $company->id)->whereIn('name', ['Fuel', 'Lubricant'])->count())->toBe(2);
});

test('product quick add files a fuel under Fuel and a lubricant under Lubricant when no category is given', function () {
    $user = User::factory()->create();
    test()->actingAs($user);
    $company = Company::create([
        'name' => 'Quick Add Station', 'slug' => 'quick-add-'.str()->random(8),
        'owner_id' => $user->id, 'base_currency' => 'PKR', 'industry_code' => 'fuel_station', 'is_active' => true,
    ]);
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    enterCompany($company);

    app(CompanyContextService::class)->withContext($company, fn () => app(SetupAction::class)->handle([
        'effective_date' => '2026-09-01',
        'products' => [
            ['type' => 'fuel', 'name' => 'Petrol', 'fuel_category' => 'petrol', 'purchase_rate' => 250, 'sale_rate' => 300],
            ['type' => 'lubricant', 'name' => 'Oil 1L', 'lubricant_format' => 'packaged', 'purchase_rate' => 800, 'sale_rate' => 1000],
            ['type' => 'other', 'name' => 'Biscuits', 'packaging' => 'packaged', 'purchase_rate' => 10, 'sale_rate' => 20],
            ['type' => 'other', 'name' => 'Wipers', 'packaging' => 'packaged', 'category_name' => 'Accessories', 'purchase_rate' => 10, 'sale_rate' => 20],
        ],
    ]));
    enterCompany($company);

    $category = fn (string $name) => Item::where('company_id', $company->id)->where('name', $name)->sole()->category?->name;
    expect($category('Petrol'))->toBe('Fuel')
        ->and($category('Oil 1L'))->toBe('Lubricant')
        ->and($category('Biscuits'))->toBeNull()
        ->and($category('Wipers'))->toBe('Accessories');
});

test('a fuel station company gets Fuel and Lubricant, once', function () {
    $user = User::factory()->create();
    $company = Company::create([
        'name' => 'Defaults Station', 'slug' => 'defaults-'.str()->random(8),
        'owner_id' => $user->id, 'base_currency' => 'PKR', 'industry_code' => 'fuel_station', 'is_active' => true,
    ]);
    enterCompany($company);

    app(StationProductCategories::class)->ensureDefaults($company->id);
    app(StationProductCategories::class)->ensureDefaults($company->id);

    expect(ItemCategory::where('company_id', $company->id)->orderBy('name')->pluck('name')->all())->toBe(['Fuel', 'Lubricant']);
});

test('fuel profit for a category lists only its products and totals only them', function () {
    $f = catClosedFixture();
    $cid = $f['company']->id;
    $service = app(ProductProfitabilityReportService::class);

    $all = $service->run($cid, '2026-09-01', '2026-09-30');
    $one = $service->run($cid, '2026-09-01', '2026-09-30', 'day', 'all', $f['fuelCategory']->id);

    expect(count($all['productRows']))->toBeGreaterThan(1);
    expect($one['productRows'])->toHaveCount(1)
        ->and($one['productRows'][0]['name'])->toBe('Diesel')
        ->and($one['category'])->toBe(['id' => $f['fuelCategory']->id, 'name' => 'Diesels'])
        ->and($one['totals']['revenue'])->toBe((float) $one['productRows'][0]['revenue'])
        ->and($one['totals']['revenue'])->toBeLessThan($all['totals']['revenue'])
        ->and($one['totals']['product_count'])->toBe(1)
        ->and($one['filters']['category_id'])->toBe($f['fuelCategory']->id)
        ->and(collect($one['categoryOptions'])->pluck('name')->sort()->values()->all())->toBe(['Diesels', 'Petrols']);

    // The trend rows follow the category too: one product's 100 L, not both.
    expect((float) array_sum(array_column($one['periodRows'], 'quantity')))->toBe((float) $one['productRows'][0]['quantity']);
});

test('the fuel profit page takes a category', function () {
    $f = catClosedFixture();
    $user = catPageFixture($f);

    $this->actingAs($user)
        ->get("/{$f['company']->slug}/fuel/reports/product-profitability?start_date=2026-09-01&end_date=2026-09-30&category_id={$f['fuelCategory']->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FuelStation/Reports/ProductProfitability')
            ->where('category.name', 'Diesels')
            ->has('productRows', 1)
            ->has('categoryOptions', 2));
});

test('the stock statement can run over every product in a category', function () {
    $f = catClosedFixture();
    $cid = $f['company']->id;
    $user = catPageFixture($f);

    $diesel = app(StockStatementService::class)->runMany($cid, [$f['diesel']->id], '2026-09-01', '2026-09-30');

    $this->actingAs($user)
        ->get("/{$f['company']->slug}/fuel/reports/stock-statement?category={$f['fuelCategory']->id}&start_date=2026-09-01&end_date=2026-09-30")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FuelStation/Reports/StockStatement')
            ->where('combined', true)
            ->where('filters.item', 'cat:'.$f['fuelCategory']->id)
            ->where('item.name', '1 products')
            ->where('totals.sold', fn ($sold) => (float) $sold === (float) $diesel['totals']['sold'])
            ->has('categories', 2)
            ->where('categories.0.name', 'Diesels'));
});

test('the calculator reads a product category as the sum of its products', function () {
    $f = catClosedFixture();
    $cid = $f['company']->id;
    $range = ['from' => '2026-09-01', 'to' => '2026-09-30'];
    $eval = fn (array $node) => app(FormulaEvaluator::class)->evaluate(new CalculatorContext($cid, 'slug'), $node);
    $value = fn (string $metric, array $collection, array $when) => ['type' => 'value', 'metric' => $metric, 'collection' => $collection, 'when' => $when];
    $product = fn (Item $i) => ['type' => 'product', 'id' => $i->id];
    $category = ['type' => 'category', 'id' => $f['fuelCategory']->id];

    // The one-product category equals that product.
    expect($eval($value('sales', $category, $range))['result'])->toBe($eval($value('sales', $product($f['diesel']), $range))['result'])
        ->and($eval($value('litres_sold', $category, $range))['result'])->toBe(100.0)
        ->and($eval($value('litres_sold', $category, $range))['unit'])->toBe('L');

    // A category holding both fuels is their sum.
    $f['petrol']->update(['category_id' => $f['fuelCategory']->id]);
    $both = $eval($value('sales', $category, $range))['result'];
    expect($both)->toBe(round($eval($value('sales', $product($f['diesel']), $range))['result'] + $eval($value('sales', $product($f['petrol']), $range))['result'], 4));

    $stockSum = $eval($value('stock_qty', $category, ['on' => '2026-09-15']));
    $each = collect([$f['diesel'], $f['petrol']])->sum(fn ($i) => $eval($value('stock_qty', $product($i), ['on' => '2026-09-15']))['result'] ?? 0);
    expect($stockSum['result'])->toBe(round((float) $each, 4));

    // A category with nothing in it has no sales.
    $unknown = ['type' => 'category', 'id' => (string) str()->uuid()];
    expect($eval($value('sales', $unknown, $range))['result'])->toBe(0.0);

    // A metric that cannot be read for a category is refused.
    expect(fn () => $eval($value('sale_rate', $category, $range)))->toThrow(\InvalidArgumentException::class);
});
