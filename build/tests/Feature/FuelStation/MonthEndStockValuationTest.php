<?php

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\FuelCostService;
use App\Modules\FuelStation\Services\MonthEndStockValuationService;
use App\Modules\FuelStation\Services\ProductProfitabilityReportService;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/PendingDeliveryFixtures.php';

/*
 * Month-end "lower of cost or new purchase rate": the litres in the tank on a month's last day
 * are written down to the purchase rate in force on the 1st of the next month when that is
 * below their cost; never written up. See MonthEndStockValuationService.
 */

/** 725 L of petrol at 407.4456 in a tank on 30 Sep, the 30 Sep close posted, and a purchase rate on 1 Oct. */
function monthEndFixture(float $octoberRate = 395.80): array
{
    $f = pendingDeliveryFixture();
    $f['company']->enableModule('fuel_station');
    $f['item']->update(['fuel_category' => 'petrol']);
    $cid = $f['company']->id;

    // The fixture's own 14 Sep opening stock would blend into the cost: start from a clean tank.
    StockMovement::where('company_id', $cid)->delete();
    $tank = $f['tank'];
    $tank->update(['warehouse_type' => 'tank', 'is_active' => true, 'capacity' => 20000]);
    StockMovement::create(['company_id' => $cid, 'warehouse_id' => $tank->id, 'item_id' => $f['item']->id, 'movement_date' => '2026-09-01',
        'movement_type' => 'opening', 'quantity' => 1000, 'unit_cost' => 407.4456, 'total_cost' => round(1000 * 407.4456, 2)]);
    TankReading::create(['company_id' => $cid, 'tank_id' => $tank->id, 'item_id' => $f['item']->id, 'reading_date' => '2026-09-30',
        'reading_type' => 'closing', 'dip_measurement_liters' => 725, 'system_calculated_liters' => 725]);
    Transaction::create([
        'company_id' => $cid, ...fixtureYearAndPeriod($cid),
        'transaction_number' => 'DC-2026-09-30-'.str()->random(4), 'transaction_type' => 'fuel_daily_close',
        'transaction_date' => '2026-09-30', 'posting_date' => '2026-09-30', 'description' => 'Daily close 2026-09-30',
        'currency' => 'PKR', 'total_debit' => 0, 'total_credit' => 0, 'status' => 'posted', 'metadata' => ['fuel_sales' => []],
    ]);
    RateChange::create(['company_id' => $cid, 'item_id' => $f['item']->id, 'effective_date' => '2026-10-01', 'purchase_rate' => $octoberRate, 'sale_rate' => 450]);

    return $f + ['tank' => $tank];
}

function monthEndLive(string $companyId)
{
    return app(MonthEndStockValuationService::class)->liveWritedowns($companyId, '2026-09');
}

test('a lower rate on the 1st writes the stock down to it', function () {
    $f = monthEndFixture(395.80);
    $cid = $f['company']->id;
    $expected = round(725 * (407.4456 - 395.80), 2);

    $result = app(MonthEndStockValuationService::class)->sync($cid, '2026-09');

    $live = monthEndLive($cid);
    expect($live)->toHaveCount(1)
        ->and($result[0]['action'])->toBe('posted');
    $tx = $live->first();
    expect($tx->transaction_date->toDateString())->toBe('2026-09-30')
        ->and((float) $tx->metadata['amount'])->toBe($expected)
        ->and((float) $tx->metadata['new_rate'])->toBe(395.8);

    $lines = DB::table('acct.journal_entries')->where('transaction_id', $tx->id)->get();
    $debit = $lines->firstWhere('account_id', $f['accounts']['5100']->id);
    $credit = $lines->firstWhere('account_id', $f['accounts']['1200']->id);
    expect((float) $debit->debit_amount)->toBe($expected)
        ->and((float) $credit->credit_amount)->toBe($expected);

    $costs = new FuelCostService();
    expect($costs->costForDay($cid, $f['item']->id, '2026-09-30'))->toBe(407.4456) // its own day: not yet written down
        ->and($costs->costForDay($cid, $f['item']->id, '2026-10-02'))->toBe(395.8);
});

test('a rate at or above cost writes nothing', function () {
    $f = monthEndFixture(450);
    $cid = $f['company']->id;

    app(MonthEndStockValuationService::class)->sync($cid, '2026-09');

    expect(monthEndLive($cid))->toHaveCount(0)
        ->and(Transaction::where('company_id', $cid)->where('transaction_type', 'fuel_stock_writedown')->count())->toBe(0)
        ->and((new FuelCostService())->costForDay($cid, $f['item']->id, '2026-10-02'))->toBe(407.4456);
});

test('running it again with nothing changed does nothing', function () {
    $f = monthEndFixture(395.80);
    $cid = $f['company']->id;
    $service = app(MonthEndStockValuationService::class);

    $service->sync($cid, '2026-09');
    $again = $service->sync($cid, '2026-09');

    expect($again[0]['action'])->toBe('none')
        ->and(Transaction::where('company_id', $cid)->where('transaction_type', 'fuel_stock_writedown')->count())->toBe(1)
        ->and(StockMovement::where('company_id', $cid)->where('movement_type', 'revaluation')->count())->toBe(1);
});

test('changing the rate on the 1st reverses the old write-down and posts the new one', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-10-02 09:00:00'));
    $f = monthEndFixture(395.80);
    $cid = $f['company']->id;
    $service = app(MonthEndStockValuationService::class);
    $service->sync($cid, '2026-09');
    $first = monthEndLive($cid)->first();

    test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/rates", [
        'item_id' => $f['item']->id, 'effective_date' => '2026-10-01', 'purchase_rate' => 390, 'sale_rate' => 450,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $live = monthEndLive($cid);
    expect($live)->toHaveCount(1)
        ->and($live->first()->id)->not->toBe($first->id)
        ->and((float) $live->first()->metadata['amount'])->toBe(round(725 * (407.4456 - 390), 2))
        ->and($first->fresh()->reversed_by_id)->not->toBeNull();

    $reversal = Transaction::find($first->fresh()->reversed_by_id);
    expect($reversal->transaction_date->toDateString())->toBe('2026-09-30');

    // A rate back at or above cost takes the write-down away again.
    test()->post("/{$f['company']->slug}/fuel/rates", [
        'item_id' => $f['item']->id, 'effective_date' => '2026-10-01', 'purchase_rate' => 420, 'sale_rate' => 450,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(monthEndLive($cid))->toHaveCount(0)
        ->and(StockMovement::where('company_id', $cid)->where('movement_type', 'revaluation')->count())->toBe(0)
        ->and((new FuelCostService())->costForDay($cid, $f['item']->id, '2026-10-02'))->toBe(407.4456);
});

test('a month with no close on its last day has no write-down', function () {
    $f = monthEndFixture(395.80);
    $cid = $f['company']->id;
    $service = app(MonthEndStockValuationService::class);
    $service->sync($cid, '2026-09');

    Transaction::where('company_id', $cid)->where('transaction_type', 'fuel_daily_close')->delete();
    $service->sync($cid, '2026-09');

    expect(monthEndLive($cid))->toHaveCount(0);
});

test('the profitability report counts the write-down in the product cost and its month', function () {
    $f = monthEndFixture(395.80);
    $cid = $f['company']->id;
    $expected = round(725 * (407.4456 - 395.80), 2);
    app(MonthEndStockValuationService::class)->sync($cid, '2026-09');

    $report = app(ProductProfitabilityReportService::class)->run($cid, '2026-09-01', '2026-09-30', 'month');

    $petrol = collect($report['productRows'])->firstWhere('key', 'petrol');
    expect($petrol['writedown'])->toBe($expected)
        ->and(round($petrol['cogs'], 2))->toBe($expected)
        ->and(round($petrol['gross_profit'], 2))->toBe(-$expected)
        ->and(round($report['periodRows'][0]['cogs'], 2))->toBe($expected)
        ->and(round($report['totals']['cogs'], 2))->toBe($expected);
});
