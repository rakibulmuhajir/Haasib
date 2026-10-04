<?php

use App\Modules\FuelStation\Services\StockStatementService;
use Illuminate\Support\Facades\DB;

/**
 * The stock statement's profit working for one tank fuel: sales + closing stock - opening stock -
 * bought, every money figure from the books, and the litres showing the tank gain or loss.
 * The report itself is given (a statement run is tested elsewhere); the books are real entries.
 */
test('a tank fuel with its own stock account works out its profit from the books', function () {
    $f = creditCloseFixture();
    $cid = $f['company']->id;
    $stock = $f['accounts']['1200']->id;
    DB::table('inv.items')->where('id', $f['payload']['nozzle_readings'][0]['item_id'])->update(['asset_account_id' => $stock]);

    // Opening 1,000 L worth 300,000 (31 Aug); a bill of 2,000 L for 620,000; the month's close
    // takes out what was sold, leaving the stock account at 500 L x 320 = 160,000 by 30 Sep.
    stockBooksEntry($cid, '2026-08-31', 'opening_balance', [[$stock, 300000, 'debit'], [$f['accounts']['4100']->id, 300000, 'credit']]);
    stockBooksEntry($cid, '2026-09-10', 'bill', [[$stock, 620000, 'debit'], [$f['accounts']['1050']->id, 620000, 'credit']]);
    stockBooksEntry($cid, '2026-09-30', 'fuel_daily_close', [[$f['accounts']['5100']->id, 760000, 'debit'], [$stock, 760000, 'credit']]);

    $report = ['has_tank' => true, 'totals' => [
        'opening' => 1000, 'received' => 2000, 'sold' => 2520, 'closing' => 500,
        'sale_amount' => 850000, 'purchase_amount' => 620000,
    ]];
    $w = app(StockStatementService::class)->profitWorking($cid, $f['payload']['nozzle_readings'][0]['item_id'], '2026-09-01', '2026-09-30', $report);

    // 1,000 + 2,000 - 2,520 = 480 should be left; the dip found 500 -> +20 L.
    expect($w['should_be_left'])->toBe(480.0)
        ->and($w['variance_litres'])->toBe(20.0)
        ->and($w['opening_value'])->toBe(300000.0)
        ->and($w['closing_value'])->toBe(160000.0)
        ->and($w['closing_rate'])->toBe(320.0)
        // 850,000 + 160,000 - 300,000 - 620,000
        ->and($w['profit'])->toBe(90000.0)
        ->and($w['variance_value'])->toBe(6400.0)
        ->and($w['unvalued'])->toBeFalse();
});

test('no working when the fuel shares its stock account or is not in a tank', function () {
    $f = creditCloseFixture();
    $cid = $f['company']->id;
    $stock = $f['accounts']['1200']->id;
    DB::table('inv.items')->where('id', $f['payload']['nozzle_readings'][0]['item_id'])->update(['asset_account_id' => $stock]);
    $other = \App\Modules\Inventory\Models\Item::create(['company_id' => $cid, 'sku' => 'OIL', 'name' => 'Oil', 'item_type' => 'product', 'unit_of_measure' => 'pack', 'currency' => 'PKR', 'asset_account_id' => $stock]);

    $svc = app(StockStatementService::class);
    expect($svc->profitWorking($cid, $f['payload']['nozzle_readings'][0]['item_id'], '2026-09-01', '2026-09-30', ['has_tank' => true, 'totals' => []]))->toBeNull()
        ->and($svc->profitWorking($cid, $other->id, '2026-09-01', '2026-09-30', ['has_tank' => false, 'totals' => []]))->toBeNull();
});
