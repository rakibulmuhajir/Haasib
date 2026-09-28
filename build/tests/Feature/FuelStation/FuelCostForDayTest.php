<?php

use App\Models\Company;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\FuelCostService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * A close is costed at its own day's weighted-average cost: opening stock, deliveries up to
 * that day, and the previous day's dip for what was left. A mistyped "current" cost on the
 * item (462.72 for 362.72) no longer decides every later day's profit.
 */
test('each day carries the weighted cost of the stock in the tank that day', function () {
    $company = Company::create(['name' => 'Cost Co', 'slug' => 'cost-'.str()->lower(str()->random(8)), 'base_currency' => 'PKR']);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $item = Item::create(['company_id' => $company->id, 'sku' => 'PMG', 'name' => 'Petrol', 'item_type' => 'product',
        'unit_of_measure' => 'liter', 'currency' => 'PKR', 'fuel_category' => 'petrol', 'avg_cost' => 462.72]);
    $tank = Warehouse::create(['company_id' => $company->id, 'code' => 'T1', 'name' => 'Tank', 'linked_item_id' => $item->id]);

    $move = fn (string $type, string $day, float $qty, float $unit) => StockMovement::create([
        'company_id' => $company->id, 'warehouse_id' => $tank->id, 'item_id' => $item->id, 'movement_date' => $day,
        'movement_type' => $type, 'quantity' => $qty, 'unit_cost' => $unit, 'total_cost' => round($qty * $unit, 2),
    ]);
    $dip = fn (string $day, float $liters) => TankReading::create([
        'company_id' => $company->id, 'tank_id' => $tank->id, 'item_id' => $item->id, 'reading_date' => $day,
        'reading_type' => 'closing', 'dip_measurement_liters' => $liters, 'system_calculated_liters' => $liters,
    ]);

    $move('opening', '2026-09-01', 1000, 340);
    $dip('2026-09-01', 600);                    // sold 400 on the 1st
    $move('purchase', '2026-09-02', 400, 360);  // 600 @ 340 + 400 @ 360
    $dip('2026-09-02', 500);
    $move('purchase', '2026-09-04', 500, 380);  // 500 @ 348 + 500 @ 380

    $costs = new FuelCostService();

    expect($costs->costForDay($company->id, $item->id, '2026-09-01'))->toBe(340.0)
        ->and($costs->costForDay($company->id, $item->id, '2026-09-02'))->toBe(348.0)
        ->and($costs->costForDay($company->id, $item->id, '2026-09-03'))->toBe(348.0)
        ->and($costs->costForDay($company->id, $item->id, '2026-09-04'))->toBe(364.0);
});
