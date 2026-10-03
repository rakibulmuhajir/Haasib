<?php

use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\MonthEndStockValuationService;
use App\Modules\FuelStation\Services\ProductProfitabilityReportService;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;

require_once __DIR__.'/PendingDeliveryFixtures.php';
require_once __DIR__.'/StockBooksFixtures.php';

/*
 * "Profit (books)": sales + closing stock - opening stock - purchases, the stock being the fuel's
 * own inventory account. Blank when that account is shared with another product.
 */

/** 400,000 of petrol in the stock account on 31 Aug, a 120,000 bill, a 200,000 sale at a cost of 190,000, 725 L left, new rate 300. */
function bookProfitFixture(): array
{
    $f = pendingDeliveryFixture();
    $f['company']->enableModule('fuel_station');
    $cid = $f['company']->id;
    $stock = $f['accounts']['1200']->id;
    $other = $f['accounts']['4100']->id;

    StockMovement::where('company_id', $cid)->delete();
    $f['tank']->update(['warehouse_type' => 'tank', 'is_active' => true, 'capacity' => 20000]);
    StockMovement::create(['company_id' => $cid, 'warehouse_id' => $f['tank']->id, 'item_id' => $f['item']->id, 'movement_date' => '2026-09-01',
        'movement_type' => 'opening', 'quantity' => 1000, 'unit_cost' => 400, 'total_cost' => 400000]);

    stockBooksEntry($cid, '2026-08-31', 'opening_balance', [[$stock, 400000, 'debit'], [$other, 400000, 'credit']]);
    pendingDeliveryBill($f, '2026-09-10', 500, 500, $f['tank']->id, 'BILL-BOOK'); // 500 L at 240 = 120,000
    stockBooksEntry($cid, '2026-09-10', 'bill', [[$stock, 120000, 'debit'], [$other, 120000, 'credit']]);

    TankReading::create(['company_id' => $cid, 'tank_id' => $f['tank']->id, 'item_id' => $f['item']->id, 'reading_date' => '2026-09-30',
        'reading_type' => 'closing', 'dip_measurement_liters' => 725, 'system_calculated_liters' => 725]);
    $close = stockBooksEntry($cid, '2026-09-30', 'fuel_daily_close', [
        [$f['accounts']['1050']->id, 200000, 'debit'], [$other, 200000, 'credit'],
        [$f['accounts']['5100']->id, 190000, 'debit'], [$stock, 190000, 'credit'],
    ]);
    Transaction::where('id', $close)->update(['metadata' => json_encode(['fuel_sales' => ['petrol' => ['liters' => 775, 'revenue' => 200000, 'cogs' => 190000]]])]);
    RateChange::create(['company_id' => $cid, 'item_id' => $f['item']->id, 'effective_date' => '2026-10-01', 'purchase_rate' => 300, 'sale_rate' => 450]);

    return $f;
}

test('book profit is sales plus closing stock less opening stock and purchases', function () {
    $f = bookProfitFixture();
    $cid = $f['company']->id;
    $valuation = app(MonthEndStockValuationService::class);

    $valuation->sync($cid, '2026-09');

    // 400,000 + 120,000 - 190,000 = 330,000 before the write-down; 725 L at 300 = 217,500 after.
    expect($valuation->accountBalance($cid, $f['accounts']['1200']->id, '2026-09-30'))->toBe(217500.0);

    $report = app(ProductProfitabilityReportService::class)->run($cid, '2026-09-01', '2026-09-30', 'month');
    $petrol = collect($report['productRows'])->firstWhere('key', 'petrol');

    expect($petrol['revenue'])->toBe(200000.0)
        ->and($petrol['book_profit'])->toBe(round($petrol['revenue'] + 217500 - 400000 - 120000, 2))
        ->and($petrol['book_profit'])->toBe(-102500.0)
        ->and($report['totals']['book_profit'])->toBe(-102500.0);
});

test('book profit is blank when the stock account is shared with another product', function () {
    $f = bookProfitFixture();
    $cid = $f['company']->id;
    Item::create(['company_id' => $cid, 'sku' => 'SHARED', 'name' => 'Shared stock', 'item_type' => 'product', 'unit_of_measure' => 'liter',
        'currency' => 'PKR', 'asset_account_id' => $f['accounts']['1200']->id]);

    $report = app(ProductProfitabilityReportService::class)->run($cid, '2026-09-01', '2026-09-30', 'month');
    $petrol = collect($report['productRows'])->firstWhere('key', 'petrol');

    expect($petrol['book_profit'])->toBeNull()
        ->and($report['totals']['book_profit'])->toBeNull();
});
