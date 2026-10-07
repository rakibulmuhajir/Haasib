<?php

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\MonthEndStockValuationService;
use App\Modules\FuelStation\Services\ProductProfitabilityReportService;
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

    $report = app(ProductProfitabilityReportService::class)->run($cid, '2026-09-01', '2026-09-30', 'month', 'all', null, true);
    $petrol = collect($report['productRows'])->firstWhere('key', 'petrol');

    expect($petrol['revenue'])->toBe(200000.0)
        ->and($petrol['book_profit'])->toBe(round($petrol['revenue'] + 217500 - 400000 - 120000, 2))
        ->and($petrol['book_profit'])->toBe(-102500.0)
        ->and($report['totals']['book_profit'])->toBe(-102500.0);

    $trail = $report['valueTrail'];
    $valuationNode = collect($trail['nodes'])->firstWhere('label', 'Month-end stock valuation');
    expect($valuationNode['value'])->toBe(112500.0)
        ->and($valuationNode['source']['kind'])->toBe('journal');
    $costNode = $trail['nodes'][$trail['roots']['product:petrol:cogs']];
    expect(round(array_sum(array_map(fn ($id) => $trail['nodes'][$id]['value'], $costNode['children'])), 2))->toBe(round($petrol['cogs'], 2));

    $book = $trail['nodes'][$trail['roots']['product:petrol:book_profit']];
    $values = array_map(fn ($id) => $trail['nodes'][$id]['value'], $book['children']);
    expect($book['value'])->toBe(-102500.0)
        ->and($book['formula'])->toBe('Sales + closing stock − opening stock − purchases')
        ->and($values[0] + $values[1] - $values[2] - $values[3])->toBe($book['value']);
    foreach (['Closing stock value', 'Opening stock value', 'Stock purchases'] as $label) {
        $node = collect($trail['nodes'])->firstWhere('label', $label);
        expect(round(array_sum(array_map(fn ($id) => $trail['nodes'][$id]['value'], $node['children'])), 2))->toBe($node['value']);
        expect($node['children'])->not->toBeEmpty();
    }
    foreach (['product:petrol', 'total'] as $scope) {
        $margin = $trail['nodes'][$trail['roots'][$scope.':margin_per_unit']];
        expect($margin['value'])->toBe($scope === 'total' ? $report['totals']['margin_per_unit'] : $petrol['margin_per_unit']);
        $basis = $trail['nodes'][$margin['children'][0]];
        $quantity = $trail['nodes'][$margin['children'][1]];
        expect($basis['value'] / $quantity['value'])->toBe($margin['value']);
    }
});

test('stock variance trails follow tank readings and mark current valuation costs as estimates', function () {
    $f = bookProfitFixture();
    TankReading::where('company_id', $f['company']->id)->delete();
    $reading = TankReading::create(['company_id' => $f['company']->id, 'tank_id' => $f['tank']->id, 'item_id' => $f['item']->id,
        'reading_date' => '2026-09-30', 'reading_type' => 'closing', 'dip_measurement_liters' => 715, 'system_calculated_liters' => 725,
        'variance_liters' => -10, 'variance_type' => 'loss']);
    $report = app(ProductProfitabilityReportService::class)->run($f['company']->id, '2026-09-01', '2026-09-30', 'month', 'all', null, true);
    $row = collect($report['productRows'])->firstWhere('key', 'petrol');
    $trail = $report['valueTrail'];
    $loss = $trail['nodes'][$trail['roots']['product:petrol:stock_loss_value']];
    expect($row['stock_loss_quantity'])->toBe(10.0)
        ->and($loss['value'])->toBe($row['stock_loss_value'])
        ->and($loss['estimated'])->toBeTrue()
        ->and($trail['nodes'][$trail['roots']['total:stock_variance_value']]['estimated'])->toBeTrue()
        ->and(collect($trail['nodes'])->firstWhere('label', 'Recorded dip')['source']['id'])->toBe($reading->id);
    $valuation = collect($trail['nodes'])->firstWhere('label', 'Stock variance valuation');
    expect(round($trail['nodes'][$valuation['children'][0]]['value'] * $trail['nodes'][$valuation['children'][1]]['value'], 2))->toBe($valuation['value']);
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
