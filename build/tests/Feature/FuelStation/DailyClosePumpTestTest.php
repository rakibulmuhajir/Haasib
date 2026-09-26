<?php

use App\Modules\FuelStation\Models\NozzleReading;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * Pump calibration: fuel is run through a nozzle for a test and poured straight back into the
 * tank. The meter genuinely advances - it must be kept exactly as read, for tomorrow's opening
 * - but nothing was sold. Before this, those litres were indistinguishable from a sale: they
 * inflated revenue and COGS, and made the tank's expected stock (and so the dip variance) look
 * like a loss that was never really there.
 *
 * Built on creditCloseFixture() (one nozzle/tank/item, avg_cost 250, sale_rate 300), with an
 * opening tank baseline added so the tank-variance half of the calculation can be exercised too.
 */
function pumpTestFixture(): array
{
    $f = creditCloseFixture();
    $f['item'] = Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();
    $f['tank'] = Warehouse::where('company_id', $f['company']->id)->where('code', 'T1')->sole();
    $f['nozzle_id'] = $f['payload']['nozzle_readings'][0]['nozzle_id'];

    // One day before the close date, so openingBaselineForTank() has something to read.
    StockMovement::create([
        'company_id' => $f['company']->id, 'warehouse_id' => $f['tank']->id, 'item_id' => $f['item']->id,
        'movement_date' => '2026-09-14', 'movement_type' => 'opening', 'quantity' => 5000,
        'unit_cost' => 250, 'total_cost' => 1250000, 'created_by_user_id' => $f['user']->id,
    ]);

    return $f;
}

test('a returned litre posts no revenue, no COGS, and no drop in the tank for itself', function () {
    $f = pumpTestFixture();
    $f['payload']['payment_receipts'] = [];
    $f['payload']['credit_sales'] = [];
    $f['payload']['nozzle_readings'][0] = array_replace($f['payload']['nozzle_readings'][0], [
        'opening_electronic' => 0,
        'closing_electronic' => 950,
        'liters_sold' => 950,
        'returned_liters' => 20,
        'sale_rate' => 300,
    ]);
    // 950 metered - 20 returned = 930 sold. Opening 5000 - 930 sold = 4070: dip that exactly
    // matches, so the pump test cannot be read as a stock loss either.
    $f['payload']['tank_readings'] = [[
        'tank_id' => $f['tank']->id,
        'stick_reading' => 100,
        'liters' => 4070,
    ]];
    $f['payload']['closing_cash'] = 10000 + 930 * 300;

    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    expect((float) $posted['metadata']['total_revenue'])->toBe(930 * 300.0)
        ->and((float) $posted['metadata']['total_cogs'])->toBe(930 * 250.0)
        ->and($posted['metadata']['tank_variances'])->toBe([]);

    $tankReading = TankReading::where('company_id', $f['company']->id)
        ->where('tank_id', $f['tank']->id)->where('reading_date', '2026-09-15')->sole();
    expect((float) $tankReading->system_calculated_liters)->toBe(4070.0)
        ->and((float) $tankReading->variance_liters)->toBe(0.0);

    // The metadata records the test explicitly, and the meter reading exactly as typed.
    expect($posted['metadata']['pump_tests'])->toHaveCount(1);
    $pumpTest = $posted['metadata']['pump_tests'][0];
    expect($pumpTest['nozzle_id'])->toBe($f['nozzle_id'])
        ->and((float) $pumpTest['liters'])->toBe(20.0);

    $nozzleReading = NozzleReading::where('company_id', $f['company']->id)
        ->where('nozzle_id', $f['nozzle_id'])->where('reading_date', '2026-09-15')->sole();
    expect((float) $nozzleReading->closing_electronic)->toBe(950.0)
        ->and((float) $nozzleReading->opening_electronic)->toBe(0.0)
        ->and((float) $nozzleReading->liters_dispensed)->toBe(930.0);
});

test('returned litres cannot exceed what the meter moved', function () {
    $f = pumpTestFixture();
    $f['payload']['nozzle_readings'][0] = array_replace($f['payload']['nozzle_readings'][0], [
        'opening_electronic' => 0,
        'closing_electronic' => 950,
        'liters_sold' => 950,
        'returned_liters' => 960,
    ]);

    expect(fn () => app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']))
        ->toThrow(ValidationException::class, 'cannot exceed');
});

test('a nozzle without returned litres behaves exactly as before', function () {
    $f = pumpTestFixture();
    $f['payload']['payment_receipts'] = [];
    $f['payload']['credit_sales'] = [];
    $f['payload']['nozzle_readings'][0] = array_replace($f['payload']['nozzle_readings'][0], [
        'opening_electronic' => 0,
        'closing_electronic' => 100,
        'liters_sold' => 100,
        'sale_rate' => 300,
    ]);
    $f['payload']['closing_cash'] = 10000 + 100 * 300;

    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    expect((float) $posted['metadata']['total_revenue'])->toBe(30000.0)
        ->and($posted['metadata']['pump_tests'])->toBe([]);

    $nozzleReading = NozzleReading::where('company_id', $f['company']->id)
        ->where('nozzle_id', $f['nozzle_id'])->where('reading_date', '2026-09-15')->sole();
    expect((float) $nozzleReading->liters_dispensed)->toBe(100.0);
});
