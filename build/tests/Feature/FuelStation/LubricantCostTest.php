<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\DailyCloseReopenService;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\FuelStation\Services\LubricantCostService;
use App\Modules\FuelStation\Services\ProductProfitabilityReportService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CreditCloseFixtures.php';

/*
 * The cost of other sales (lubricant packs, shop items, open drums). The close used to book the
 * revenue only: no cost of sales in the ledger and packaged items never left stock. Now the close
 * costs each line (DR the item's cost account, CR its stock account, a 'sale' stock movement for
 * packaged items), and fuel:lubricant-cost books the same for months posted before that.
 */

/** A packaged lubricant (20 on the shelf at avg_cost 1,000) with its own sales, cost and stock accounts. */
function lubeCostFixture(float $avgCost = 1000.0, bool $shelfStock = true): array
{
    $f = creditCloseFixture();
    $cid = $f['company']->id;
    $mk = fn (string $code, string $type, string $subtype, string $normal) => Account::create([
        'company_id' => $cid, 'code' => $code, 'name' => 'Lube '.$code, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal, 'is_active' => true,
    ]);
    $f['lube'] = [
        'income' => $mk('4210', 'revenue', 'other_income', 'credit'),
        'cost' => $mk('5210', 'cogs', 'cost_of_goods_sold', 'debit'),
        'stock' => $mk('1210', 'asset', 'inventory', 'debit'),
    ];
    $f['item'] = Item::create([
        'company_id' => $cid, 'sku' => 'MOBIL-4L', 'name' => 'Mobil Super 4L', 'item_type' => 'product', 'unit_of_measure' => 'unit',
        'currency' => 'PKR', 'avg_cost' => $avgCost, 'income_account_id' => $f['lube']['income']->id,
        'expense_account_id' => $f['lube']['cost']->id, 'asset_account_id' => $f['lube']['stock']->id,
    ]);
    $f['shop'] = Warehouse::create(['company_id' => $cid, 'code' => 'SHOP', 'name' => 'Shop', 'warehouse_type' => 'standard', 'is_active' => true]);
    if ($shelfStock) {
        StockMovement::create(['company_id' => $cid, 'warehouse_id' => $f['shop']->id, 'item_id' => $f['item']->id, 'movement_date' => '2026-09-01',
            'movement_type' => 'opening', 'quantity' => 20, 'unit_cost' => 1000, 'total_cost' => 20000]);
    }

    return $f;
}

/** The fixture's 100 L cash day plus these other-sale lines; closing cash = 10,000 + 30,000 + their revenue. */
function lubeCostClose(array $f, array $sales): array
{
    $revenue = array_sum(array_map(fn ($s) => $s['quantity'] * $s['unit_price'], $sales));
    $f['payload']['payment_receipts'] = [];
    $f['payload']['credit_sales'] = [];
    $f['payload']['closing_cash'] = 40000 + $revenue;
    $f['payload']['other_sales'] = $sales;

    return app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
}

function lubeCostLine(Item $item, float $quantity, float $unitPrice): array
{
    return ['item_id' => $item->id, 'item_name' => $item->name, 'quantity' => $quantity, 'unit_price' => $unitPrice];
}

/** Debit and credit totals an account carries on a transaction. */
function lubeCostSides(string $transactionId, string $accountId): array
{
    $row = DB::table('acct.journal_entries')->where('transaction_id', $transactionId)->where('account_id', $accountId)
        ->selectRaw('COALESCE(SUM(debit_amount),0) as d, COALESCE(SUM(credit_amount),0) as c')->first();

    return [(float) $row->d, (float) $row->c];
}

function lubeCostLevel(array $f): float
{
    return (float) StockLevel::where('company_id', $f['company']->id)->where('item_id', $f['item']->id)->sum('quantity');
}

/** The company's net balance (debit less credit) on an account over live transactions. */
function lubeCostBalance(string $companyId, string $accountId): float
{
    $row = DB::table('acct.journal_entries as je')->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
        ->where('t.company_id', $companyId)->where('je.account_id', $accountId)->whereNull('t.deleted_at')
        ->selectRaw('COALESCE(SUM(je.debit_amount),0) - COALESCE(SUM(je.credit_amount),0) as b')->first();

    return round((float) $row->b, 2);
}

function lubeCostRun(array $f, string $month, array $prices, bool $dry = false): array
{
    $args = ['month' => $month, '--company' => $f['company']->slug, '--prices' => json_encode($prices)];
    if ($dry) {
        $args['--dry-run'] = true;
    }
    $code = Artisan::call('fuel:lubricant-cost', $args);

    return [$code, Artisan::output()];
}

test('a packaged lubricant other sale posts its cost, takes the units off stock and records the cost on the line', function () {
    $f = lubeCostFixture();
    $posted = lubeCostClose($f, [lubeCostLine($f['item'], 4, 2400)]);
    $closeId = $posted['transaction_id'];

    [$debit] = lubeCostSides($closeId, $f['lube']['cost']->id);
    [, $credit] = lubeCostSides($closeId, $f['lube']['stock']->id);
    [, $revenue] = lubeCostSides($closeId, $f['lube']['income']->id);
    expect($debit)->toBe(4000.0)->and($credit)->toBe(4000.0)->and($revenue)->toBe(9600.0);

    $movement = StockMovement::where('company_id', $f['company']->id)->where('movement_type', 'sale')->sole();
    expect((float) $movement->quantity)->toBe(-4.0)
        ->and($movement->warehouse_id)->toBe($f['shop']->id)
        ->and($movement->gl_transaction_id)->toBe($closeId)
        ->and($movement->movement_date->toDateString())->toBe('2026-09-15')
        ->and(lubeCostLevel($f))->toBe(16.0);

    $line = Transaction::findOrFail($closeId)->metadata['other_sales_details'][0];
    expect((float) $line['unit_cost'])->toBe(1000.0)->and((float) $line['cost'])->toBe(4000.0);
});

test('an item with no cost or no cost account is left uncosted and the close still posts', function (string $case) {
    $f = lubeCostFixture($case === 'no cost' ? 0.0 : 1000.0);
    if ($case === 'no account') {
        $f['item']->update(['expense_account_id' => null]);
    }
    $posted = lubeCostClose($f, [lubeCostLine($f['item'], 2, 2400)]);

    expect(StockMovement::where('company_id', $f['company']->id)->where('movement_type', 'sale')->count())->toBe(0)
        ->and(lubeCostLevel($f))->toBe(20.0)
        ->and(lubeCostBalance($f['company']->id, $f['lube']['stock']->id))->toBe(0.0);
    $line = Transaction::findOrFail($posted['transaction_id'])->metadata['other_sales_details'][0];
    expect((float) $line['cost'])->toBe(0.0)->and((float) $line['unit_cost'])->toBe(0.0);
})->with(['no cost', 'no account']);

test('editing the day and posting again does not take the cost or the stock twice', function () {
    $f = lubeCostFixture();
    $sales = [lubeCostLine($f['item'], 4, 2400)];
    $posted = lubeCostClose($f, $sales);
    expect(lubeCostLevel($f))->toBe(16.0);

    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(DailyCloseReopenService::class)->reopen(Transaction::findOrFail($posted['transaction_id']), $f['user'], 'Quantity was entered wrong.');
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    expect(lubeCostLevel($f))->toBe(20.0)
        ->and(StockMovement::where('company_id', $f['company']->id)->where('movement_type', 'sale')->count())->toBe(0)
        ->and(lubeCostBalance($f['company']->id, $f['lube']['cost']->id))->toBe(0.0);

    $again = lubeCostClose($f, [lubeCostLine($f['item'], 3, 2400)]);
    expect(lubeCostLevel($f))->toBe(17.0)
        ->and(StockMovement::where('company_id', $f['company']->id)->where('movement_type', 'sale')->count())->toBe(1)
        ->and(lubeCostBalance($f['company']->id, $f['lube']['cost']->id))->toBe(3000.0)
        ->and(lubeCostBalance($f['company']->id, $f['lube']['stock']->id))->toBe(-3000.0);
    expect((float) Transaction::findOrFail($again['transaction_id'])->metadata['other_sales_details'][0]['cost'])->toBe(3000.0);
});

test('an open drum sold as an other sale is costed at the fuel cost for the day and moves no stock', function () {
    $f = lubeCostFixture(500.0, false); // item avg_cost 500: only the fallback; the drum is the item's only stock
    $drum = Warehouse::create(['company_id' => $f['company']->id, 'code' => 'DRUM', 'name' => 'Lube drum', 'warehouse_type' => 'tank',
        'linked_item_id' => $f['item']->id, 'is_active' => true, 'capacity' => 200]);
    StockMovement::create(['company_id' => $f['company']->id, 'warehouse_id' => $drum->id, 'item_id' => $f['item']->id, 'movement_date' => '2026-09-01',
        'movement_type' => 'opening', 'quantity' => 100, 'unit_cost' => 700, 'total_cost' => 70000]);
    $levelBefore = lubeCostLevel($f);

    $rate = app(\App\Modules\FuelStation\Services\FuelCostService::class)->costForDay($f['company']->id, $f['item']->id, '2026-09-15', 500.0);
    expect($rate)->toBe(700.0);

    $posted = lubeCostClose($f, [lubeCostLine($f['item'], 5, 1200)]);

    [$debit] = lubeCostSides($posted['transaction_id'], $f['lube']['cost']->id);
    expect($debit)->toBe(3500.0)
        ->and(StockMovement::where('company_id', $f['company']->id)->where('movement_type', 'sale')->count())->toBe(0)
        ->and(lubeCostLevel($f))->toBe($levelBefore);
    $line = Transaction::findOrFail($posted['transaction_id'])->metadata['other_sales_details'][0];
    expect((float) $line['unit_cost'])->toBe(700.0)->and((float) $line['cost'])->toBe(3500.0);
});

/** A close posted as before the change: the item had no cost on it, so the line carries none. */
function lubeCostLegacyClose(array $f, float $quantity = 4, float $price = 2400): array
{
    $f['item']->update(['avg_cost' => 0]);
    $posted = lubeCostClose($f, [lubeCostLine($f['item'], $quantity, $price)]);
    $f['item']->update(['avg_cost' => 1000]);

    return $posted;
}

test('the command posts one month correction from the prices and moves the stock', function () {
    $f = lubeCostFixture();
    lubeCostLegacyClose($f);
    expect(lubeCostLevel($f))->toBe(20.0);

    [$code, $output] = lubeCostRun($f, '2026-09', ['MOBIL-4L' => 900]);
    expect($code)->toBe(0)->and($output)->toContain('Mobil Super 4L')->and($output)->toContain('3,600.00')->and($output)->toContain('6,000.00');

    $tx = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_lubricant_cost')->sole();
    expect($tx->transaction_date->toDateString())->toBe('2026-09-30')
        ->and($tx->metadata['month'])->toBe('2026-09')
        ->and($tx->metadata['lines'][0]['item_id'])->toBe($f['item']->id)
        ->and((float) $tx->metadata['lines'][0]['quantity'])->toBe(4.0)
        ->and((float) $tx->metadata['lines'][0]['unit_cost'])->toBe(900.0)
        ->and((float) $tx->metadata['lines'][0]['cost'])->toBe(3600.0);
    expect(lubeCostSides($tx->id, $f['lube']['cost']->id)[0])->toBe(3600.0)
        ->and(lubeCostSides($tx->id, $f['lube']['stock']->id)[1])->toBe(3600.0);

    $movement = StockMovement::where('company_id', $f['company']->id)->where('movement_type', 'sale')->sole();
    expect($movement->gl_transaction_id)->toBe($tx->id)
        ->and((float) $movement->quantity)->toBe(-4.0)
        ->and($movement->movement_date->toDateString())->toBe('2026-09-30')
        ->and(lubeCostLevel($f))->toBe(16.0);
});

test('the command refuses when a sold item has no price, and a dry run writes nothing', function () {
    $f = lubeCostFixture();
    lubeCostLegacyClose($f);

    [$code, $output] = lubeCostRun($f, '2026-09', ['Some other oil' => 100]);
    expect($code)->not->toBe(0)->and($output)->toContain('Mobil Super 4L');
    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_lubricant_cost')->count())->toBe(0);

    [$code, $output] = lubeCostRun($f, '2026-09', ['Mobil Super 4L' => 900], true);
    expect($code)->toBe(0)->and($output)->toContain('3,600.00')->and($output)->toContain('Dry run');
    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_lubricant_cost')->count())->toBe(0)
        ->and(StockMovement::where('company_id', $f['company']->id)->where('movement_type', 'sale')->count())->toBe(0)
        ->and(lubeCostLevel($f))->toBe(20.0);
});

test('running the command again replaces the month correction instead of adding to it', function () {
    $f = lubeCostFixture();
    lubeCostLegacyClose($f);
    $cid = $f['company']->id;

    lubeCostRun($f, '2026-09', ['MOBIL-4L' => 900]);
    [$code] = lubeCostRun($f, '2026-09', [$f['item']->id => 950]);
    expect($code)->toBe(0);

    $live = app(LubricantCostService::class)->liveCorrections($cid, '2026-09');
    expect($live)->toHaveCount(1)
        ->and((float) $live->first()->metadata['lines'][0]['cost'])->toBe(3800.0)
        ->and(Transaction::where('company_id', $cid)->where('transaction_type', 'fuel_lubricant_cost')->whereNotNull('reversed_by_id')->count())->toBe(1)
        ->and(lubeCostBalance($cid, $f['lube']['cost']->id))->toBe(3800.0)
        ->and(StockMovement::where('company_id', $cid)->where('movement_type', 'sale')->count())->toBe(1)
        ->and(lubeCostLevel($f))->toBe(16.0);
});

function lubeCostReportRow(array $f): array
{
    $report = app(ProductProfitabilityReportService::class)->run($f['company']->id, '2026-09-01', '2026-09-30', 'day', 'all');

    return collect($report['productRows'])->firstWhere('name', 'Mobil Super 4L');
}

test('the profitability report uses the recorded cost, then the month correction, then the estimate', function () {
    // Recorded on the close.
    $f = lubeCostFixture();
    lubeCostClose($f, [lubeCostLine($f['item'], 4, 2400)]);
    $f['item']->update(['avg_cost' => 1500]); // today's cost no longer matters
    $row = lubeCostReportRow($f);
    expect((float) $row['cogs'])->toBe(4000.0)->and($row['estimated_cogs'])->toBeFalse();

    // Posted without a cost: estimate, then the correction's unit cost.
    $g = lubeCostFixture();
    lubeCostLegacyClose($g);
    $g['item']->update(['avg_cost' => 1500]);
    $row = lubeCostReportRow($g);
    expect((float) $row['cogs'])->toBe(6000.0)->and($row['estimated_cogs'])->toBeTrue();

    lubeCostRun($g, '2026-09', ['MOBIL-4L' => 900]);
    $row = lubeCostReportRow($g);
    expect((float) $row['cogs'])->toBe(3600.0)->and($row['estimated_cogs'])->toBeFalse();
});

test('editing a day in a corrected month and re-posting it keeps the month cost the same and drops that day from the correction', function () {
    $f = lubeCostFixture();
    $cid = $f['company']->id;
    $first = lubeCostLegacyClose($f, 4, 2400);

    // A second day, also posted without a cost.
    $f['item']->update(['avg_cost' => 0]);
    $day2 = $f['payload'];
    $day2['date'] = '2026-09-16';
    $day2['opening_cash'] = 49600;
    $day2['closing_cash'] = 49600 + 30000 + 3 * 2400;
    $day2['nozzle_readings'][0]['opening_electronic'] = 100;
    $day2['nozzle_readings'][0]['closing_electronic'] = 200;
    $day2['payment_receipts'] = [];
    $day2['credit_sales'] = [];
    $day2['other_sales'] = [lubeCostLine($f['item'], 3, 2400)];
    app(DailyCloseService::class)->processDailyClose($cid, $day2, $f['user']);
    $f['item']->update(['avg_cost' => 900]);

    lubeCostRun($f, '2026-09', ['MOBIL-4L' => 900]);
    expect(lubeCostBalance($cid, $f['lube']['cost']->id))->toBe(6300.0)
        ->and(lubeCostLevel($f))->toBe(13.0);

    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(DailyCloseReopenService::class)->reopen(Transaction::findOrFail($first['transaction_id']), $f['user'], 'Quantity was entered wrong.');
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    // Day one is out of the books; the correction already shrank to the other day's 3 units.
    $live = app(LubricantCostService::class)->liveCorrections($cid, '2026-09');
    expect($live)->toHaveCount(1)
        ->and((float) $live->first()->metadata['lines'][0]['quantity'])->toBe(3.0);

    lubeCostClose($f, [lubeCostLine($f['item'], 4, 2400)]); // re-post day one, now at its own cost

    $live = app(LubricantCostService::class)->liveCorrections($cid, '2026-09');
    expect($live)->toHaveCount(1)
        ->and((float) $live->first()->metadata['lines'][0]['quantity'])->toBe(3.0)
        ->and(lubeCostBalance($cid, $f['lube']['cost']->id))->toBe(6300.0) // 4 x 900 on the close + 3 x 900 corrected
        ->and(lubeCostBalance($cid, $f['lube']['stock']->id))->toBe(-6300.0)
        ->and(lubeCostLevel($f))->toBe(13.0);
});
