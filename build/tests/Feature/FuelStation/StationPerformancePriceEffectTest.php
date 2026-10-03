<?php

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\StationPerformanceReportService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CreditCloseFixtures.php';

/*
 * Price effect on Profit by day: informational, never booked. E(D) = U(D) - U(previous close),
 * U(D) = closing dip litres x (purchase rate on D+1 - book cost carried out of D).
 */

/** Petrol 1000 L @ 400 and diesel 500 L @ 300 from 1 Sep; closes on 10 and 12 Sep; a petrol rate of 410 from 11 Sep. */
function priceEffectFixture(): array
{
    $f = creditCloseFixture();
    $cid = $f['company']->id;
    $petrol = Item::where('company_id', $cid)->where('sku', 'PETROL')->sole();
    $petrol->update(['fuel_category' => 'petrol', 'income_account_id' => $f['accounts']['4100']->id, 'expense_account_id' => $f['accounts']['5100']->id]);
    $diesel = Item::create(['company_id' => $cid, 'sku' => 'DIESEL', 'name' => 'Diesel', 'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR', 'fuel_category' => 'diesel']);
    $petrolTank = Warehouse::where('company_id', $cid)->where('code', 'T1')->sole();
    $dieselTank = Warehouse::create(['company_id' => $cid, 'code' => 'T2', 'name' => 'Tank 2', 'linked_item_id' => $diesel->id]);
    foreach ([$petrolTank, $dieselTank] as $tank) {
        $tank->update(['warehouse_type' => 'tank', 'is_active' => true, 'capacity' => 20000]);
    }

    foreach ([[$petrolTank, $petrol, 1000, 400], [$dieselTank, $diesel, 500, 300]] as [$tank, $item, $qty, $cost]) {
        StockMovement::create(['company_id' => $cid, 'warehouse_id' => $tank->id, 'item_id' => $item->id, 'movement_date' => '2026-09-01',
            'movement_type' => 'opening', 'quantity' => $qty, 'unit_cost' => $cost, 'total_cost' => $qty * $cost]);
    }
    foreach (['2026-09-10' => [800, 500], '2026-09-12' => [600, 500]] as $date => [$petrolDip, $dieselDip]) {
        foreach ([[$petrolTank, $petrol, $petrolDip], [$dieselTank, $diesel, $dieselDip]] as [$tank, $item, $dip]) {
            TankReading::create(['company_id' => $cid, 'tank_id' => $tank->id, 'item_id' => $item->id, 'reading_date' => $date,
                'reading_type' => 'closing', 'dip_measurement_liters' => $dip, 'system_calculated_liters' => $dip]);
        }
        $tx = Transaction::create([
            'company_id' => $cid, ...fixtureYearAndPeriod($cid),
            'transaction_number' => 'DC-'.$date.'-'.str()->random(4), 'transaction_type' => 'fuel_daily_close',
            'transaction_date' => $date, 'posting_date' => $date, 'description' => 'Daily close '.$date,
            'currency' => 'PKR', 'total_debit' => 5000, 'total_credit' => 5000, 'status' => 'posted', 'metadata' => ['fuel_sales' => []],
        ]);
        // Trading: sales 5000 against a cost of 4000, from the books.
        foreach ([['1050', 5000, 0], ['4100', 0, 5000], ['5100', 4000, 0], ['1200', 0, 4000]] as $i => [$code, $debit, $credit]) {
            DB::table('acct.journal_entries')->insert([
                'id' => (string) str()->uuid(), 'company_id' => $cid, 'transaction_id' => $tx->id, 'account_id' => $f['accounts'][$code]->id,
                'line_number' => $i + 1, 'debit_amount' => $debit, 'credit_amount' => $credit, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
    RateChange::create(['company_id' => $cid, 'item_id' => $petrol->id, 'effective_date' => '2026-09-11', 'purchase_rate' => 410, 'sale_rate' => 450]);
    RateChange::create(['company_id' => $cid, 'item_id' => $diesel->id, 'effective_date' => '2026-09-11', 'purchase_rate' => 305, 'sale_rate' => 350]);

    return $f;
}

function priceEffectRun(string $companyId, string $from, string $to, string $group = 'day', string $product = 'all'): array
{
    return app(StationPerformanceReportService::class)->run($companyId, $from, $to, $group, $product);
}

test('each day carries the change in stock value at the next rate over cost', function () {
    $f = priceEffectFixture();
    $rows = collect(priceEffectRun($f['company']->id, '2026-09-01', '2026-09-30')['rows'])->keyBy('key');

    // 10 Sep: petrol 800 x (410 - 400) = 8000, diesel 500 x 5 = 2500; no earlier close.
    // 12 Sep: petrol 600 x 10 = 6000 (-2000), diesel unchanged.
    expect($rows['2026-09-10']['price_effect'])->toBe(10500.0)
        ->and($rows['2026-09-12']['price_effect'])->toBe(-2000.0);

    $line = collect($rows['2026-09-12']['price_effect_lines'])->firstWhere('name', 'Petrol');
    expect($line['quantity'])->toBe(600.0)->and($line['cost'])->toBe(400.0)->and($line['rate'])->toBe(410.0)
        ->and($line['value'])->toBe(6000.0)->and($line['effect'])->toBe(-2000.0);
});

test('a range starting later compares with the last close before it, so ranges telescope', function () {
    $f = priceEffectFixture();
    $cid = $f['company']->id;

    $late = priceEffectRun($cid, '2026-09-12', '2026-09-30');
    $month = priceEffectRun($cid, '2026-09-01', '2026-09-30', 'month');

    expect($late['rows'][0]['price_effect'])->toBe(-2000.0)
        ->and($month['rows'][0]['price_effect'])->toBe(8500.0)
        ->and($month['totals']['price_effect'])->toBe(8500.0);
});

test('the product filter keeps only that fuel', function () {
    $f = priceEffectFixture();
    $cid = $f['company']->id;

    $petrol = priceEffectRun($cid, '2026-09-01', '2026-09-30', 'day', 'petrol')['rows'];
    $diesel = priceEffectRun($cid, '2026-09-01', '2026-09-30', 'day', 'diesel')['rows'];

    expect(collect($petrol)->pluck('price_effect')->all())->toBe([8000.0, -2000.0])
        ->and(collect($diesel)->pluck('price_effect')->all())->toBe([2500.0, 0.0]);
});

test('the price effect never touches net profit or the other columns', function () {
    $f = priceEffectFixture();
    $cid = $f['company']->id;

    $with = priceEffectRun($cid, '2026-09-01', '2026-09-30');
    RateChange::where('company_id', $cid)->delete();
    $without = priceEffectRun($cid, '2026-09-01', '2026-09-30');

    expect($without['totals']['price_effect'])->toBe(0.0)
        ->and($with['totals']['net_station_profit'])->toBe(2000.0)
        ->and($with['totals']['net_station_profit'])->toBe($without['totals']['net_station_profit']);
    foreach (['revenue', 'cogs', 'gross_profit', 'expenses', 'other'] as $column) {
        expect($with['totals'][$column])->toBe($without['totals'][$column]);
    }
});
