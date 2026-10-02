<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\StockStatementService;
use Illuminate\Support\Facades\DB;

/**
 * The stock statement reads posted closes only (not soft-deleted, not reversed). Bought comes
 * from the bills by bill date, including litres sold straight off the tanker, which are counted
 * as sold too; the balance is the dip, and the variance is dip - (opening + bought - sold).
 * Metadata is written by hand so every figure can be checked against an input.
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

function statementBill(Company $company, string $itemId, string $tankId, string $date, float $quantity, float $direct = 0): string
{
    $vendorId = DB::table('acct.vendors')->where('company_id', $company->id)->value('id');
    if (! $vendorId) {
        $vendorId = (string) str()->uuid();
        DB::table('acct.vendors')->insert(['id' => $vendorId, 'company_id' => $company->id, 'vendor_number' => 'V-'.str()->random(5), 'name' => 'Supplier', 'base_currency' => 'PKR']);
    }
    $billId = (string) str()->uuid();
    DB::table('acct.bills')->insert([
        'id' => $billId, 'company_id' => $company->id, 'vendor_id' => $vendorId, 'bill_number' => 'BILL-'.str()->random(5),
        'bill_date' => $date, 'due_date' => $date, 'status' => 'received', 'currency' => 'PKR', 'base_currency' => 'PKR',
        'subtotal' => $quantity * 380, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => $quantity * 380,
        'paid_amount' => 0, 'balance' => $quantity * 380, 'base_amount' => $quantity * 380, 'payment_terms' => 30,
    ]);
    DB::table('acct.bill_line_items')->insert([
        'id' => (string) str()->uuid(), 'company_id' => $company->id, 'bill_id' => $billId, 'line_number' => 1,
        'description' => 'Petrol', 'quantity' => $quantity, 'unit_price' => 380, 'tax_rate' => 0, 'discount_rate' => 0,
        'line_total' => $quantity * 380, 'tax_amount' => 0, 'total' => $quantity * 380,
        'item_id' => $itemId, 'warehouse_id' => $tankId, 'direct_quantity' => $direct,
    ]);

    return $billId;
}

test('opening comes from the close before the range; bought comes from the bills', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-05'));
    [$company, $itemId, $tankId] = statementFixture();

    statementClose($company, $itemId, $tankId, '2026-08-31', 5000, 5000, 0, 400);
    statementClose($company, $itemId, $tankId, '2026-09-01', 5000, 4950, 1000, 400);   // 1000 bought into the tank
    statementClose($company, $itemId, $tankId, '2026-09-02', 4150, 4150, 800, 406);
    $bill = statementBill($company, $itemId, $tankId, '2026-09-01', 1000);
    // 2 Sep: 300 bought and sold straight off the tanker -- in both columns, the tank untouched.
    statementBill($company, $itemId, $tankId, '2026-09-02', 300, 300);
    // A draft bill is not a purchase.
    DB::table('acct.bills')->where('id', statementBill($company, $itemId, $tankId, '2026-09-02', 9999))->update(['status' => 'draft']);

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
        ->and($a['bills'])->toHaveCount(1)
        ->and($a['bills'][0]['id'])->toBe($bill)
        ->and($a['bills'][0]['quantity'])->toBe(1000.0);

    // The next row opens with the previous row's dip.
    expect($b['opening'])->toBe(4950.0)
        ->and($b['received'])->toBe(300.0)
        ->and($b['received_direct'])->toBe(300.0)
        ->and($b['sold'])->toBe(1100.0)
        ->and($b['sold_pumps'])->toBe(800.0)
        ->and($b['sold_direct'])->toBe(300.0)
        ->and($b['expected'])->toBe(4150.0)
        ->and($b['variance'])->toBe(0.0)
        ->and($b['sale_amount'])->toBe(324800.0)
        ->and($b['rates'])->toBe([406.0]);

    // The reversed close leaves 3 Sep as a visible gap.
    expect($c)->toBe(['date' => '2026-09-03', 'missing' => true]);

    expect($r['totals']['opening'])->toBe(5000.0)
        ->and($r['totals']['received'])->toBe(1300.0)
        ->and($r['totals']['sold'])->toBe(2100.0)
        ->and($r['totals']['sale_amount'])->toBe(724800.0)
        ->and($r['totals']['rate'])->toBe(345.14)
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

test('with no earlier close and no opening stock, the opening is unknown', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-05'));
    [$company, $itemId, $tankId] = statementFixture();
    statementClose($company, $itemId, $tankId, '2026-09-01', 5000, 5000, 1000, 400);

    $r = app(StockStatementService::class)->run($company->id, $itemId, '2026-09-01', '2026-09-01');

    expect($r['rows'][0]['opening'])->toBeNull()
        ->and($r['rows'][0]['received'])->toBe(0.0)
        ->and($r['totals']['opening'])->toBeNull()
        ->and($r['totals']['received'])->toBe(0.0)
        ->and($r['totals']['sold'])->toBe(1000.0);
});
