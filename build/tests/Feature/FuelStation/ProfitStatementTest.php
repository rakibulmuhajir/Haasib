<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Services\ProfitStatementService;
use App\Modules\FuelStation\Services\StationPerformanceReportService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;

require_once __DIR__.'/PendingDeliveryFixtures.php';
require_once __DIR__.'/StockBooksFixtures.php';

/*
 * The profit statement is built only from the ledger, and its Net profit is the ledger's own
 * profit for the range -- also the figure Profit by day shows.
 */

/** A station with every kind of line in September 2026. Returns [fixture, accounts by role]. */
function profitStatementFixture(): array
{
    $f = pendingDeliveryFixture();
    $f['company']->enableModule('fuel_station');
    $cid = $f['company']->id;

    $make = fn (string $code, string $name, string $type, string $subtype, string $normal) => Account::create([
        'company_id' => $cid, 'code' => $code, 'name' => $name, 'type' => $type, 'subtype' => $subtype,
        'normal_balance' => $normal, 'is_active' => true,
    ])->id;
    $a = [
        'cash' => $f['accounts']['1050']->id,
        'stock' => $f['accounts']['1200']->id,
        'sales' => $f['accounts']['4100']->id,
        'cost' => $f['accounts']['5100']->id,
        'loss' => $make('5900', 'Fuel Shrinkage', 'expense', 'operating_expense', 'debit'),
        'gain' => $make('4900', 'Fuel Variance Gain', 'revenue', 'other_income', 'credit'),
        'power' => $make('6110', 'Electricity', 'expense', 'operating_expense', 'debit'),
        'salary' => $make('6150', 'Salaries & Wages', 'expense', 'operating_expense', 'debit'),
        'short' => $make('6180', 'Cash Over/Short', 'expense', 'operating_expense', 'debit'),
        'rent' => $make('4300', 'Shop Rent', 'revenue', 'other_income', 'credit'),
        'equipment' => $make('1520', 'Equipment & Machinery', 'asset', 'fixed_asset', 'debit'),
        'equity' => $make('3000', 'Opening Equity', 'equity', 'equity', 'credit'),
    ];

    Item::where('id', $f['item']->id)->update(['income_account_id' => $a['sales']]);
    $f['item'] = $f['item']->fresh();

    // Petrol: 400,000 of stock on 31 Aug, a 120,000 bill, a 100,000 sale costing 70,000.
    StockMovement::where('company_id', $cid)->delete();
    $f['tank']->update(['warehouse_type' => 'tank', 'is_active' => true, 'capacity' => 20000]);
    StockMovement::create(['company_id' => $cid, 'warehouse_id' => $f['tank']->id, 'item_id' => $f['item']->id, 'movement_date' => '2026-09-01',
        'movement_type' => 'opening', 'quantity' => 1000, 'unit_cost' => 400, 'total_cost' => 400000]);
    stockBooksEntry($cid, '2026-08-31', 'opening_balance', [[$a['stock'], 400000, 'debit'], [$a['equity'], 400000, 'credit']]);
    pendingDeliveryBill($f, '2026-09-10', 500, 500, $f['tank']->id, 'BILL-PS'); // 500 L at 240 = 120,000
    stockBooksEntry($cid, '2026-09-10', 'bill', [[$a['stock'], 120000, 'debit'], [$a['equity'], 120000, 'credit']]);

    stockBooksEntry($cid, '2026-09-10', 'fuel_daily_close', [
        [$a['cash'], 100000, 'debit'], [$a['sales'], 100000, 'credit'],
        [$a['cost'], 70000, 'debit'], [$a['stock'], 70000, 'credit'],
    ]);
    stockBooksEntry($cid, '2026-09-10', 'fuel_daily_close', [[$a['loss'], 800, 'debit'], [$a['stock'], 800, 'credit']]);
    stockBooksEntry($cid, '2026-09-10', 'fuel_daily_close', [[$a['stock'], 300, 'debit'], [$a['gain'], 300, 'credit']]);
    stockBooksEntry($cid, '2026-09-30', 'fuel_stock_writedown', [[$a['cost'], 1000, 'debit'], [$a['stock'], 1000, 'credit']]);
    stockBooksEntry($cid, '2026-09-11', 'expense', [[$a['power'], 2000, 'debit'], [$a['cash'], 2000, 'credit']]);
    // An expense entered and then reversed: the pair nets to nothing.
    stockBooksEntry($cid, '2026-09-12', 'expense', [[$a['power'], 700, 'debit'], [$a['cash'], 700, 'credit']]);
    stockBooksEntry($cid, '2026-09-13', 'expense', [[$a['power'], 700, 'credit'], [$a['cash'], 700, 'debit']]);
    // Equipment entered as an expense is an asset, not a cost.
    stockBooksEntry($cid, '2026-09-11', 'expense', [[$a['equipment'], 5000, 'debit'], [$a['cash'], 5000, 'credit']]);
    stockBooksEntry($cid, '2026-09-25', 'payroll_accrual', [[$a['salary'], 1500, 'debit'], [$a['cash'], 1500, 'credit']]);
    stockBooksEntry($cid, '2026-09-20', 'journal', [[$a['cash'], 400, 'debit'], [$a['rent'], 400, 'credit']]);
    stockBooksEntry($cid, '2026-09-21', 'journal', [[$a['short'], 120, 'debit'], [$a['cash'], 120, 'credit']]);

    return [$f, $a];
}

function profitStatementLine(array $statement, string $key): array
{
    return collect($statement['lines'])->firstWhere('key', $key);
}

test('every line comes from the ledger and net profit is the ledger profit', function () {
    [$f, $a] = profitStatementFixture();
    $slug = $f['company']->slug;
    $s = app(ProfitStatementService::class)->run($f['company']->id, '2026-09-01', '2026-09-30', $slug);
    $line = fn (string $k) => profitStatementLine($s, $k);

    expect($line('sales')['amount'])->toBe(100000.0)
        // 70,000 sold + 1,000 write-down + 800 tank loss - 300 tank gain.
        ->and($line('cost_of_sales')['amount'])->toBe(71500.0)
        ->and($line('gross_profit')['amount'])->toBe(28500.0)
        ->and($line('expenses')['amount'])->toBe(2000.0)
        ->and($line('salaries')['amount'])->toBe(1500.0)
        ->and($line('other_income')['amount'])->toBe(400.0)
        ->and($line('other_costs')['amount'])->toBe(120.0)
        ->and($line('net_profit')['amount'])->toBe(25280.0)
        ->and($s['net_profit'])->toBe(25280.0)
        ->and($s['ledger_total'])->toBe(25280.0)
        ->and($s['not_in_profit'])->toBe(['stock_bought' => 120000.0, 'equipment_bought' => 5000.0]);

    // Petrol has its own stock account, so it takes the station's formula: opening 400,000 +
    // bought 120,000 - closing 448,500 = 71,500, tank loss and gain inside it. Nothing is left over.
    $cost = collect($line('cost_of_sales')['details']);
    expect($cost)->toHaveCount(1)
        ->and($cost[0]['name'])->toBe('Petrol')
        ->and($cost[0]['amount'])->toBe(71500.0)
        ->and($cost[0]['working'])->toBe(['opening' => 400000.0, 'bought' => 120000.0, 'closing' => 448500.0, 'used' => 71500.0])
        ->and($cost[0]['href'])->toContain("/{$slug}/fuel/reports/stock-statement?item={$f['item']->id}");

    $sales = $line('sales')['details'];
    expect($sales)->toHaveCount(1)->and($sales[0]['name'])->toBe('Petrol')->and($sales[0]['amount'])->toBe(100000.0);

    $gross = collect($line('gross_profit')['details']);
    expect($gross)->toHaveCount(1)
        ->and($gross[0]['name'])->toBe('Petrol')
        ->and($gross[0]['amount'])->toBe(28500.0)
        ->and($gross[0]['working']['used'])->toBe(71500.0);

    $expense = $line('expenses')['details'];
    expect($expense)->toHaveCount(1)
        ->and($expense[0]['name'])->toBe('Electricity')
        ->and($expense[0]['account_id'])->toBe($a['power'])
        ->and($expense[0]['href'])->toBe("/{$slug}/reports/statements?kind=expense&id={$a['power']}&from=2026-09-01&to=2026-09-30");
    expect($line('salaries')['details'][0]['name'])->toBe('Salaries & Wages');
    $rent = $line('other_income')['details'][0];
    expect($rent['name'])->toBe('Shop Rent')->and($rent['href'])->toBe("/{$slug}/accounts/{$a['rent']}");
    expect($line('other_costs')['details'][0]['name'])->toBe('Cash Over/Short');
});

test('profit by day agrees with the statement', function () {
    [$f] = profitStatementFixture();
    $cid = $f['company']->id;
    $statement = app(ProfitStatementService::class)->run($cid, '2026-09-01', '2026-09-30');
    $row = app(StationPerformanceReportService::class)->run($cid, '2026-09-01', '2026-09-30', 'month', 'all')['rows'][0];

    expect($row['revenue'])->toBe(100000.0)
        ->and($row['cogs'])->toBe(71500.0)
        ->and($row['expenses'])->toBe(2000.0)
        // Salaries -1,500, rent +400, cash short -120.
        ->and($row['other'])->toBe(-1220.0)
        ->and($row['net_station_profit'])->toBe($statement['net_profit']);
});

test('products sharing an account show per account, and what no product holds stays as one row', function () {
    [$f, $a] = profitStatementFixture();
    Item::create(['company_id' => $f['company']->id, 'sku' => 'SHARED', 'name' => 'Diesel', 'item_type' => 'product', 'unit_of_measure' => 'liter',
        'currency' => 'PKR', 'income_account_id' => $a['sales'], 'expense_account_id' => $a['cost'], 'asset_account_id' => $a['stock']]);

    $s = app(ProfitStatementService::class)->run($f['company']->id, '2026-09-01', '2026-09-30', $f['company']->slug);

    $sales = profitStatementLine($s, 'sales')['details'];
    expect($sales)->toHaveCount(1)->and($sales[0]['name'])->toBe('4100')->and($sales[0]['item_id'])->toBeNull()
        ->and($sales[0]['href'])->toBe("/{$f['company']->slug}/accounts/{$a['sales']}");

    // No formula (the stock account is shared): the cost account's 71,000, and the tank loss 800
    // less gain 300 is the residual that makes the details add up to the line.
    $cost = collect(profitStatementLine($s, 'cost_of_sales')['details']);
    expect($cost->sum('amount'))->toBe(71500.0)
        ->and($cost->firstWhere('name', '5100')['amount'])->toBe(71000.0)
        ->and($cost->firstWhere('name', 'Tank losses and gains not in a product')['amount'])->toBe(500.0);

    $gross = collect(profitStatementLine($s, 'gross_profit')['details']);
    expect($gross->sum('amount'))->toBe(28500.0)
        ->and($gross->whereNotNull('working'))->toBeEmpty()
        ->and($gross->firstWhere('name', 'Tank losses and gains not in a product')['amount'])->toBe(-500.0)
        ->and($s['net_profit'])->toBe(25280.0);
});

test('discounts given reduce sales instead of landing in other income', function () {
    [$f, $a] = profitStatementFixture();
    $cid = $f['company']->id;
    $discounts = Account::create(['company_id' => $cid, 'code' => '4150', 'name' => 'Sales Discounts', 'type' => 'revenue', 'subtype' => 'other_income',
        'normal_balance' => 'debit', 'is_active' => true])->id;
    stockBooksEntry($cid, '2026-09-15', 'fuel_daily_close', [[$discounts, 2000, 'debit'], [$a['cash'], 2000, 'credit']]);

    $s = app(ProfitStatementService::class)->run($cid, '2026-09-01', '2026-09-30', $f['company']->slug);
    $line = fn (string $k) => profitStatementLine($s, $k);

    expect($line('sales')['amount'])->toBe(98000.0)
        ->and(collect($line('sales')['details'])->firstWhere('name', 'Discounts given')['amount'])->toBe(-2000.0)
        ->and($line('other_income')['amount'])->toBe(400.0)
        ->and($line('gross_profit')['amount'])->toBe(26500.0)
        ->and(collect($line('gross_profit')['details'])->sum('amount'))->toBe(26500.0)
        ->and($s['net_profit'])->toBe(23280.0)
        ->and($s['ledger_total'])->toBe(23280.0);

    // Profit by day counts them in sales too.
    $row = app(StationPerformanceReportService::class)->run($cid, '2026-09-01', '2026-09-30', 'month', 'all')['rows'][0];
    expect($row['revenue'])->toBe(98000.0)->and($row['net_station_profit'])->toBe(23280.0);
});
