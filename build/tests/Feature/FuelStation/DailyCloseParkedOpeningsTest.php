<?php

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;

require_once __DIR__.'/DailyCloseWorkflowFixtures.php';

/**
 * closeWorkflowFixture() has no nozzles or tanks configured (every post there is a genuine
 * zero-sales day). This file's tests need real ones, so this extends it with a single
 * pump/nozzle/tank, mirroring CreditCloseFixtures::creditCloseFixture().
 */
function parkedOpeningsFixture(): array
{
    $f = closeWorkflowFixture();

    $item = Item::create(['company_id' => $f['company']->id, 'sku' => 'PETROL', 'name' => 'Petrol', 'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR', 'avg_cost' => 250]);
    $tank = Warehouse::create(['company_id' => $f['company']->id, 'code' => 'T1', 'name' => 'Tank 1', 'linked_item_id' => $item->id, 'warehouse_type' => 'tank', 'is_active' => true, 'capacity' => 10000]);
    $pump = Pump::create(['company_id' => $f['company']->id, 'name' => 'Pump 1', 'tank_id' => $tank->id, 'is_active' => true]);
    $nozzle = Nozzle::create(['company_id' => $f['company']->id, 'pump_id' => $pump->id, 'tank_id' => $tank->id, 'item_id' => $item->id, 'code' => 'N1', 'label' => 'Front', 'is_active' => true]);

    // An opening baseline for the tank, one day before the first close date this fixture
    // dips - see DailyCloseService::openingBaselineForTank, which refuses to post a tank
    // variance with no baseline at all.
    \App\Modules\Inventory\Models\StockMovement::create([
        'company_id' => $f['company']->id, 'warehouse_id' => $tank->id, 'item_id' => $item->id,
        'movement_date' => '2026-09-15', 'movement_type' => 'opening', 'quantity' => 5000,
        'unit_cost' => 250, 'total_cost' => 1250000, 'created_by_user_id' => $f['user']->id,
    ]);

    $f['item'] = $item;
    $f['tank'] = $tank;
    $f['pump'] = $pump;
    $f['nozzle'] = $nozzle;

    // Business date 2026-09-16, opening 0, closing 100 -> 100L sold. tank dips to 4800L.
    $f['payload'] = [
        'date' => '2026-09-16',
        'opening_cash' => 420000,
        'closing_cash' => 425000,
        'nozzle_readings' => [[
            'nozzle_id' => $nozzle->id, 'item_id' => $item->id,
            'opening_electronic' => 0, 'closing_electronic' => 100,
            'liters_sold' => 100, 'sale_rate' => 300,
        ]],
        'tank_readings' => [[
            'tank_id' => $tank->id, 'stick_reading' => 120, 'liters' => 4800,
        ]],
    ];

    return $f;
}

test('a parked but unposted previous day feeds nozzle opening, tank baseline and previous close cash into the next day', function () {
    $f = parkedOpeningsFixture();
    enableCloseHttp($f);
    $url = "/{$f['company']->slug}/fuel/daily-close";
    test()->travelTo(\Carbon\Carbon::parse('2026-09-16 09:00:00'));

    // Park 2026-09-16 (do not post it).
    test()->post($url, $f['payload'] + ['intent' => 'park'])
        ->assertSessionHasNoErrors();
    expect(session('error'))->toBeNull();
    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->count())->toBe(0);

    // GET the close page for the next business date, 2026-09-17.
    $response = test()->get($url.'?date=2026-09-17')->assertOk();
    $page = $response->viewData('page')['props'];

    expect($page['openingsFromParked'])->toBe('2026-09-16');

    $nozzleRow = collect($page['nozzles'])->firstWhere('id', $f['nozzle']->id);
    expect($nozzleRow['opening_reading'])->toEqual(100.0);

    $tankBaseline = collect($page['previousTankReadings'])->firstWhere('tank_id', $f['tank']->id);
    expect($tankBaseline['liters'])->toEqual(4800.0);
    expect($tankBaseline['source'])->toBe('parked_close');
    expect($tankBaseline['as_of'])->toBe('2026-09-16');

    expect($page['previousClose']['date'])->toBe('2026-09-16');
    expect((float) $page['previousClose']['closing_cash'])->toBe(425000.0);
    expect($page['previousClose']['source'])->toBe('parked');

    test()->travelBack();
});

test('posting the day after an unposted parked day is refused, but parking that day is allowed', function () {
    $f = parkedOpeningsFixture();
    enableCloseHttp($f);
    $url = "/{$f['company']->slug}/fuel/daily-close";
    test()->travelTo(\Carbon\Carbon::parse('2026-09-16 09:00:00'));

    test()->post($url, $f['payload'] + ['intent' => 'park'])->assertSessionHasNoErrors();

    $nextDayPayload = [
        'date' => '2026-09-17',
        'opening_cash' => 425000,
        'closing_cash' => 425000,
        'nozzle_readings' => [],
        'zero_sales_confirmed' => true,
        'zero_sales_reason' => 'Test day',
    ];

    // Posting 2026-09-17 must be refused: 2026-09-16 is parked but not posted. The
    // ValidationException thrown by DailyCloseService::processDailyClose is caught by
    // DailyCloseController::store() and surfaced as a flash error, not a form error bag -
    // see its catch (\Throwable $e) block.
    $response = test()->post($url, $nextDayPayload);
    $response->assertSessionHas('error');
    expect(session('error'))->toContain('Post 2026-09-16 first');
    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->count())->toBe(0);

    expect(fn () => app(DailyCloseService::class)->processDailyClose($f['company']->id, $nextDayPayload, $f['user']))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    // Parking 2026-09-17 stays allowed.
    test()->post($url, $nextDayPayload + ['intent' => 'park'])->assertSessionHasNoErrors();
    expect(session('error'))->toBeNull();

    test()->travelBack();
});

test('once the previous day is posted, openings and posting both use it normally', function () {
    $f = parkedOpeningsFixture();
    enableCloseHttp($f);
    $url = "/{$f['company']->slug}/fuel/daily-close";
    test()->travelTo(\Carbon\Carbon::parse('2026-09-16 09:00:00'));

    // Post 2026-09-16 for real this time.
    test()->post($url, $f['payload'])->assertSessionHasNoErrors();
    expect(session('error'))->toBeNull();
    $posted = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->firstOrFail();
    expect($posted->transaction_date->toDateString())->toBe('2026-09-16');

    $page = test()->get($url.'?date=2026-09-17')->assertOk()->viewData('page')['props'];
    expect($page['openingsFromParked'])->toBeNull();
    $nozzleRow = collect($page['nozzles'])->firstWhere('id', $f['nozzle']->id);
    expect($nozzleRow['opening_reading'])->toEqual(100.0);

    $nextDayPayload = [
        'date' => '2026-09-17',
        'opening_cash' => 425000,
        'closing_cash' => 425000,
        'nozzle_readings' => [],
        'zero_sales_confirmed' => true,
        'zero_sales_reason' => 'Test day',
    ];

    test()->post($url, $nextDayPayload)->assertSessionHasNoErrors();
    expect(session('error'))->toBeNull();
    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->count())->toBe(2);

    test()->travelBack();
});
