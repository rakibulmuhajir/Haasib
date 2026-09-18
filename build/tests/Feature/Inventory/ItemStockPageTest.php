<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * `GET /{company}/stock/items/{item}` rendered 'inventory/stock/ItemStock',
 * which had no .vue file -- every "see this item's stock" link in the stock
 * pages landed on a blank screen. The page name is lowercase where the Payroll
 * pages are capitalised, so it is also the one most exposed to the
 * case-sensitivity bug the Inertia guard exists to catch.
 */
function itemStockFixture(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Item Stock '.str()->random(8),
        'slug' => 'item-stock-'.str()->lower(str()->random(10)),
        'base_currency' => 'PKR',
        'settings' => ['modules' => ['inventory' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($user, 'owner'),
    );
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    test()->actingAs($user);
    app(CurrentCompany::class)->set($company);

    $warehouse = Warehouse::create([
        'company_id' => $company->id,
        'code' => 'MAIN',
        'name' => 'Main warehouse',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $item = Item::create([
        'company_id' => $company->id,
        'sku' => 'WIDGET-1',
        'name' => 'Widget',
        'unit_of_measure' => 'unit',
        'currency' => 'PKR',
        'track_inventory' => true,
        'cost_price' => 100,
        'avg_cost' => 100,
        'selling_price' => 150,
        'reorder_point' => 5,
        'is_active' => true,
    ]);

    StockLevel::create([
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 12,
        'reserved_quantity' => 2,
    ]);

    StockMovement::create([
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'movement_date' => '2026-09-10',
        'movement_type' => 'opening',
        'quantity' => 12,
        'unit_cost' => 100,
        'created_by_user_id' => $user->id,
    ]);

    return [$user, $company, $item, $warehouse];
}

test('the item stock page renders with the item, its levels and its movements', function () {
    [$user, $company, $item] = itemStockFixture();

    $this->actingAs($user)
        ->get("/{$company->slug}/stock/items/{$item->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/stock/ItemStock', false)
            ->where('item.id', $item->id)
            ->where('item.sku', 'WIDGET-1')
            ->has('stockLevels', 1)
            ->where('stockLevels.0.warehouse.code', 'MAIN')
            ->has('movements', 1)
            ->where('movements.0.movement_type', 'opening')
            ->where('company.slug', $company->slug));
});

test('another company item is not reachable through the item stock page', function () {
    [$user, $company] = itemStockFixture();

    // A second company's item must not resolve under this company's slug --
    // the controller scopes findOrFail by company_id and this pins that.
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    $otherCompany = Company::create([
        'name' => 'Other '.str()->random(8),
        'slug' => 'other-'.str()->lower(str()->random(10)),
        'base_currency' => 'PKR',
        'settings' => ['modules' => ['inventory' => true]],
    ]);
    $otherItem = Item::create([
        'company_id' => $otherCompany->id,
        'sku' => 'OTHER-1',
        'name' => 'Other widget',
        'currency' => 'PKR',
    ]);
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    $this->actingAs($user)
        ->get("/{$company->slug}/stock/items/{$otherItem->id}")
        ->assertNotFound();
});
