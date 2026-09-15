<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Str;

function tankBaselineFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create([
        'name' => 'Tank Baseline Test',
        'slug' => 'tank-baseline-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ]);

    $item = Item::create([
        'company_id' => $company->id,
        'sku' => 'DIESEL-'.str()->lower(str()->random(6)),
        'name' => 'Diesel',
        'item_type' => 'product',
        'unit_of_measure' => 'liter',
        'currency' => 'PKR',
    ]);

    $warehouse = Warehouse::create([
        'company_id' => $company->id,
        'code' => 'TANK-'.str()->lower(str()->random(6)),
        'name' => 'Tank 1',
        'linked_item_id' => $item->id,
    ]);

    return [
        'company' => $company,
        'user' => $user,
        'tank_id' => $warehouse->id,
        'item_id' => $item->id,
    ];
}

test('a tank with neither a dip nor opening stock has no baseline', function () {
    $f = tankBaselineFixture();
    $baseline = app(DailyCloseService::class)->openingBaselineForTank($f['company']->id, $f['tank_id'], $f['item_id'], '2026-09-01');
    expect($baseline['has_baseline'])->toBeFalse()->and($baseline['liters'])->toBe(0.0);
});

test("yesterday's close dip is today's opening baseline", function () {
    $f = tankBaselineFixture();
    TankReading::create([
        'company_id' => $f['company']->id,
        'tank_id' => $f['tank_id'],
        'item_id' => $f['item_id'],
        'reading_date' => '2026-09-01',
        'reading_type' => 'closing',
        'stick_reading' => 120,
        'dip_measurement_liters' => 8400,
        'system_calculated_liters' => 8400,
        'variance_liters' => 0,
        'variance_type' => 'none',
        'status' => 'posted',
        'recorded_by_user_id' => $f['user']->id,
    ]);

    $baseline = app(DailyCloseService::class)->openingBaselineForTank($f['company']->id, $f['tank_id'], $f['item_id'], '2026-09-02');
    expect($baseline['has_baseline'])->toBeTrue()
        ->and($baseline['liters'])->toBe(8400.0)
        ->and($baseline['date'])->toBe('2026-09-01');
});

test('opening stock from setup is the baseline for the first close', function () {
    $f = tankBaselineFixture();
    StockMovement::create([
        'company_id' => $f['company']->id,
        'warehouse_id' => $f['tank_id'],
        'item_id' => $f['item_id'],
        'movement_date' => '2026-08-31',
        'movement_type' => 'opening',
        'quantity' => 10000,
        'unit_cost' => 250,
        'total_cost' => 2500000,
        'created_by_user_id' => $f['user']->id,
    ]);

    $baseline = app(DailyCloseService::class)->openingBaselineForTank($f['company']->id, $f['tank_id'], $f['item_id'], '2026-09-01');
    expect($baseline['has_baseline'])->toBeTrue()->and($baseline['liters'])->toBe(10000.0);
});
