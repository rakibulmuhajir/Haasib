<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function stockMovementsPageFixture(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Movements Page '.str()->random(8),
        'slug' => 'movements-page-'.str()->lower(str()->random(10)),
        'base_currency' => 'PKR',
        'settings' => ['modules' => ['inventory' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    test()->actingAs($user);
    app(CurrentCompany::class)->set($company);

    $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'MAIN', 'name' => 'Main', 'is_primary' => true, 'is_active' => true]);
    $item = Item::create([
        'company_id' => $company->id, 'sku' => 'MOV-1', 'name' => 'Mover', 'unit_of_measure' => 'unit',
        'currency' => 'PKR', 'track_inventory' => true, 'cost_price' => 10, 'avg_cost' => 10,
        'selling_price' => 15, 'reorder_point' => 1, 'is_active' => true,
    ]);

    return [$user, $company, $item, $warehouse];
}

test('the stock movements page renders each movement with its source', function () {
    [$user, $company, $item, $warehouse] = stockMovementsPageFixture();
    $billId = (string) str()->uuid();
    $base = ['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'movement_date' => '2026-09-10', 'created_by_user_id' => $user->id];

    StockMovement::create($base + ['movement_type' => 'opening', 'quantity' => 12, 'unit_cost' => 10]);
    StockMovement::create($base + ['movement_type' => 'purchase', 'quantity' => 5, 'unit_cost' => 10, 'reference_type' => 'acct.bills', 'reference_id' => $billId]);
    StockMovement::create($base + ['movement_type' => 'revaluation', 'quantity' => 0, 'total_cost' => -250, 'reference_type' => 'fuel.stock_writedown']);

    $this->actingAs($user)
        ->get("/{$company->slug}/stock/movements")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/stock/Movements', false)
            ->has('movements.data', 3)
            ->where('movements.data', fn ($rows) => collect($rows)->contains(
                fn ($r) => $r['movement_type'] === 'purchase' && $r['source']['href'] === "/{$company->slug}/bills/{$billId}"
            ) && collect($rows)->contains(
                fn ($r) => $r['movement_type'] === 'opening' && $r['source']['label'] === 'Opening entry'
            ))
        );
});
