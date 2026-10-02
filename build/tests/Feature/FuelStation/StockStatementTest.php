<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\StockStatementService;
use Illuminate\Support\Facades\DB;

/**
 * The stock statement reads posted closes only (not soft-deleted, not reversed) and derives
 * received as expected - opening + sold. Metadata is written by hand so every figure can be
 * checked against an input.
 */
function statementFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Statement', 'slug' => 'stmt-'.str()->lower(str()->random(10)), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    enterCompany($company);

    $itemId = (string) str()->uuid();
    DB::table('inv.items')->insert([
        'id' => $itemId, 'company_id' => $company->id, 'sku' => 'PET-'.str()->random(5), 'name' => 'Petrol',
        'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR', 'fuel_category' => 'petrol',
    ]);
    $tankId = (string) str()->uuid();
    DB::table('inv.warehouses')->insert([
        'id' => $tankId, 'company_id' => $company->id, 'code' => 'T-'.str()->random(5), 'name' => 'Tank 1',
        'warehouse_type' => 'tank', 'capacity' => 20000, 'linked_item_id' => $itemId,
    ]);

    return [$company, $itemId, $tankId];
}

function statementClose(Company $company, string $itemId, string $tankId, string $date, float $expected, float $dip, float $sold, float $rate, string $suffix = ''): Transaction
{
    return Transaction::create([
        'company_id' => $company->id,
        'transaction_number' => 'DC-'.$date.$suffix.'-'.str()->random(4),
        'transaction_type' => 'fuel_daily_close',
        'transaction_date' => $date,
        'posting_date' => $date,
        'description' => 'Daily close '.$date,
        'currency' => 'PKR',
        'total_debit' => 0,
        'total_credit' => 0,
        'status' => 'posted',
        'metadata' => [
            'fuel_sales' => ['petrol' => ['liters' => $sold, 'revenue' => $sold * $rate, 'cogs' => 0]],
            'posting_snapshot' => [
                'tanks' => [[
                    'tank_id' => $tankId, 'tank_name' => 'Tank 1', 'item_id' => $itemId,
                    'expected_liters' => $expected, 'physical_liters' => $dip, 'variance_liters' => $dip - $expected,
                ]],
                'nozzles' => [[
                    'nozzle_id' => (string) str()->uuid(), 'tank_id' => $tankId, 'item_id' => $itemId,
                    'liters_dispensed' => $sold, 'sale_rate' => $rate,
                ]],
            ],
        ],
    ]);
}

test('opening comes from the close before the range; received is expected - opening + sold', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-05'));
    [$company, $itemId, $tankId] = statementFixture();

    statementClose($company, $itemId, $tankId, '2026-08-31', 5000, 5000, 0, 400);
    statementClose($company, $itemId, $tankId, '2026-09-01', 5000, 4950, 1000, 400);   // 1000 received
    statementClose($company, $itemId, $tankId, '2026-09-02', 4150, 4150, 800, 406);

    // Soft-deleted older version of 1 Sep, and a reversed close on 3 Sep: neither counts.
    statementClose($company, $itemId, $tankId, '2026-09-01', 1, 1, 9999, 1, 'old')->delete();
    $live = Transaction::where('company_id', $company->id)->whereDate('transaction_date', '2026-09-02')->first();
    $reversed = statementClose($company, $itemId, $tankId, '2026-09-03', 1, 1, 7777, 1);
    $reversed->reversed_by_id = $live->id;
    $reversed->save();

    $r = app(StockStatementService::class)->run($company->id, $itemId, '2026-09-01', '2026-09-03');

    expect($r['item'])->toBe(['id' => $itemId, 'name' => 'Petrol'])
        ->and($r['products'])->toBe([['id' => $itemId, 'name' => 'Petrol']])
        ->and($r['rows'])->toHaveCount(3);

    [$a, $b, $c] = $r['rows'];
    expect($a['date'])->toBe('2026-09-01')
        ->and($a['opening'])->toBe(5000.0)
        ->and($a['sold'])->toBe(1000.0)
        ->and($a['expected'])->toBe(5000.0)
        ->and($a['dip'])->toBe(4950.0)
        ->and($a['received'])->toBe(1000.0)
        ->and($a['variance'])->toBe(-50.0)
        ->and($a['sale_amount'])->toBe(400000.0)
        ->and($a['rates'])->toBe([400.0])
        ->and($a['bills'])->toBe([]);

    // The next row opens with the previous row's dip.
    expect($b['opening'])->toBe(4950.0)
        ->and($b['received'])->toBe(0.0)
        ->and($b['sale_amount'])->toBe(324800.0)
        ->and($b['rates'])->toBe([406.0]);

    // The reversed close leaves 3 Sep as a visible gap.
    expect($c)->toBe(['date' => '2026-09-03', 'missing' => true]);

    expect($r['totals']['opening'])->toBe(5000.0)
        ->and($r['totals']['received'])->toBe(1000.0)
        ->and($r['totals']['sold'])->toBe(1800.0)
        ->and($r['totals']['sale_amount'])->toBe(724800.0)
        ->and($r['totals']['rate'])->toBe(402.67)
        ->and($r['totals']['closing'])->toBe(4150.0)
        ->and($r['totals']['variance'])->toBe(-50.0);
});

test('with no earlier close the first row opens on the opening stock', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-05'));
    [$company, $itemId, $tankId] = statementFixture();

    DB::table('inv.stock_movements')->insert([
        'company_id' => $company->id, 'warehouse_id' => $tankId, 'item_id' => $itemId,
        'movement_date' => '2026-08-01', 'movement_type' => 'opening', 'quantity' => 6000,
    ]);
    statementClose($company, $itemId, $tankId, '2026-09-01', 5000, 5000, 1000, 400);

    $r = app(StockStatementService::class)->run($company->id, $itemId, '2026-09-01', '2026-09-01');

    expect($r['rows'][0]['opening'])->toBe(6000.0)
        ->and($r['rows'][0]['received'])->toBe(0.0)
        ->and($r['totals']['opening'])->toBe(6000.0);
});

test('with no earlier close and no opening stock, opening and received are unknown', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-05'));
    [$company, $itemId, $tankId] = statementFixture();
    statementClose($company, $itemId, $tankId, '2026-09-01', 5000, 5000, 1000, 400);

    $r = app(StockStatementService::class)->run($company->id, $itemId, '2026-09-01', '2026-09-01');

    expect($r['rows'][0]['opening'])->toBeNull()
        ->and($r['rows'][0]['received'])->toBeNull()
        ->and($r['totals']['opening'])->toBeNull()
        ->and($r['totals']['received'])->toBeNull()
        ->and($r['totals']['sold'])->toBe(1000.0);
});
