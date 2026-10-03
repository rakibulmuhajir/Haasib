<?php

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Services\DailyCloseLockService;
use App\Modules\FuelStation\Services\MonthlyFuelProfitService;
use App\Modules\FuelStation\Services\StationPerformanceReportService;
use App\Modules\FuelStation\Services\StockStatementService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CreditCloseFixtures.php';

test('monthly purchase-rate fuel profit uses both boundary rates and leaves book profit alone', function () {
    $fixture = creditCloseFixture();
    $companyId = $fixture['company']->id;
    $item = Item::where('company_id', $companyId)->where('sku', 'PETROL')->sole();
    $item->update(['fuel_category' => 'petrol']);
    Warehouse::where('company_id', $companyId)->where('linked_item_id', $item->id)->update(['warehouse_type' => 'tank', 'capacity' => 40000, 'is_active' => true]);
    StationSettings::where('company_id', $companyId)->update(['month_end_stock_valuation' => 'next_month_purchase_rate']);

    RateChange::create(['company_id' => $companyId, 'item_id' => $item->id, 'effective_date' => '2026-09-01', 'purchase_rate' => 380, 'sale_rate' => 400]);
    RateChange::create(['company_id' => $companyId, 'item_id' => $item->id, 'effective_date' => '2026-10-01', 'purchase_rate' => 395.80, 'sale_rate' => 410]);

    $statement = Mockery::mock(StockStatementService::class);
    $statement->shouldReceive('run')->twice()->andReturn([
        'rows' => [['date' => '2026-09-30', 'dip' => 536, 'physical_reading_complete' => true]],
        'totals' => ['opening' => 100, 'closing' => 536, 'received' => 36531, 'sold' => 36095,
            'sale_amount' => 14415245, 'purchase_amount' => 14021475],
    ]);
    app()->instance(StockStatementService::class, $statement);

    $result = app(MonthlyFuelProfitService::class)->run($companyId, '2026-09', 'petrol');
    expect($result['complete'])->toBeTrue()
        ->and($result['totals']['opening_value'])->toBe(38000.0)
        ->and($result['totals']['closing_value'])->toBe(212148.8)
        ->and($result['totals']['fuel_profit'])->toBe(567918.8);

    $report = app(StationPerformanceReportService::class)->run($companyId, '2026-09-01', '2026-09-30', 'month', 'petrol');
    expect($report['monthEndStockValuation'])->toBe('next_month_purchase_rate')
        ->and($report['monthlyFuelProfit'][0]['totals']['fuel_profit'])->toBe(567918.8);
});

test('missing month-end reading suppresses the management total', function () {
    $fixture = creditCloseFixture();
    $companyId = $fixture['company']->id;
    $item = Item::where('company_id', $companyId)->where('sku', 'PETROL')->sole();
    $item->update(['fuel_category' => 'petrol']);
    Warehouse::where('company_id', $companyId)->where('linked_item_id', $item->id)->update(['warehouse_type' => 'tank', 'capacity' => 40000, 'is_active' => true]);
    RateChange::create(['company_id' => $companyId, 'item_id' => $item->id, 'effective_date' => '2026-09-01', 'purchase_rate' => 380, 'sale_rate' => 400]);

    $statement = Mockery::mock(StockStatementService::class);
    $statement->shouldReceive('run')->once()->andReturn([
        'rows' => [], 'totals' => ['opening' => 100, 'closing' => null, 'sale_amount' => 0, 'purchase_amount' => 0],
    ]);
    app()->instance(StockStatementService::class, $statement);

    $result = app(MonthlyFuelProfitService::class)->run($companyId, '2026-09', 'petrol');
    expect($result['complete'])->toBeFalse()->and($result['missing_products'])->toBe(['Petrol']);
});

test('locking preserves the rate method and carries its closing value into October exactly once', function () {
    $fixture = creditCloseFixture();
    $cid = $fixture['company']->id;
    $item = Item::where('company_id', $cid)->where('sku', 'PETROL')->sole();
    $item->update(['fuel_category' => 'petrol', 'income_account_id' => $fixture['accounts']['4100']->id, 'expense_account_id' => $fixture['accounts']['5100']->id]);
    Warehouse::where('company_id', $cid)->update(['warehouse_type' => 'tank', 'capacity' => 40000]);
    StationSettings::where('company_id', $cid)->update(['month_end_stock_valuation' => 'next_month_purchase_rate']);
    RateChange::create(['company_id' => $cid, 'item_id' => $item->id, 'effective_date' => '2026-09-01', 'purchase_rate' => 380, 'sale_rate' => 400]);
    $octRate = RateChange::create(['company_id' => $cid, 'item_id' => $item->id, 'effective_date' => '2026-10-01', 'purchase_rate' => 395.80, 'sale_rate' => 410]);
    RateChange::create(['company_id' => $cid, 'item_id' => $item->id, 'effective_date' => '2026-11-01', 'purchase_rate' => 410, 'sale_rate' => 420]);

    $statements = Mockery::mock(StockStatementService::class);
    $statements->shouldReceive('run')->with($cid, $item->id, '2026-09-01', '2026-09-30')->once()->andReturn([
        'rows' => [['date' => '2026-09-30', 'dip' => 536, 'physical_reading_complete' => true]],
        'totals' => ['opening' => 100, 'closing' => 536, 'received' => 36531, 'sold' => 36095, 'sale_amount' => 14415245, 'purchase_amount' => 14021475],
    ]);
    $statements->shouldReceive('run')->with($cid, $item->id, '2026-10-01', '2026-10-31')->once()->andReturn([
        'rows' => [['date' => '2026-10-31', 'dip' => 0, 'physical_reading_complete' => true]],
        'totals' => ['opening' => 536, 'closing' => 0, 'received' => 0, 'sold' => 536, 'sale_amount' => 225000, 'purchase_amount' => 0],
    ]);
    app()->instance(StockStatementService::class, $statements);

    $this->travelTo(\Carbon\Carbon::parse('2026-11-03'));
    app(DailyCloseLockService::class)->lockMonth($cid, 2026, 9, $fixture['user']->id);
    // A later rate edit and preference change cannot silently rewrite September.
    $octRate->update(['purchase_rate' => 400]);
    StationSettings::where('company_id', $cid)->update(['month_end_stock_valuation' => 'inventory_cost']);
    $september = app(MonthlyFuelProfitService::class)->run($cid, '2026-09');
    $october = app(MonthlyFuelProfitService::class)->run($cid, '2026-10');
    expect($september['finalized'])->toBeTrue()
        ->and($september['totals']['fuel_profit'])->toBe(567918.8)
        ->and($october['totals']['opening_value'])->toBe($september['totals']['closing_value'])
        ->and($october['totals']['fuel_profit'])->toBe(12851.2);

    $report = app(StationPerformanceReportService::class)->run($cid, '2026-09-01', '2026-09-30', 'month');
    expect($report['monthlyFuelProfit'][0]['finalized'])->toBeTrue();

    DB::transaction(fn () => app(MonthlyFuelProfitService::class)->reopen($cid, '2026-09'));
    expect(DB::table('fuel.month_profit_snapshots')->where('company_id', $cid)->whereNull('reopened_at')->count())->toBe(0)
        ->and(DB::table('fuel.month_profit_snapshots')->where('company_id', $cid)->count())->toBe(1);
});

test('a missing month-end calculation rolls back month locking', function () {
    $fixture = creditCloseFixture();
    $cid = $fixture['company']->id;
    $item = Item::where('company_id', $cid)->where('sku', 'PETROL')->sole();
    $item->update(['fuel_category' => 'petrol']);
    Warehouse::where('company_id', $cid)->update(['warehouse_type' => 'tank', 'capacity' => 40000]);
    StationSettings::where('company_id', $cid)->update(['month_end_stock_valuation' => 'next_month_purchase_rate']);
    $close = Transaction::create([
        'company_id' => $cid, ...fixtureYearAndPeriod($cid), 'transaction_number' => 'MONTH-LOCK',
        'transaction_type' => 'fuel_daily_close', 'transaction_date' => '2026-09-30', 'posting_date' => '2026-09-30',
        'description' => 'Month end', 'currency' => 'PKR', 'total_debit' => 0, 'total_credit' => 0,
        'status' => 'posted', 'is_locked' => false, 'metadata' => [],
    ]);
    $statement = Mockery::mock(StockStatementService::class);
    $statement->shouldReceive('run')->once()->andReturn(['rows' => [], 'totals' => ['opening' => null, 'closing' => null]]);
    app()->instance(StockStatementService::class, $statement);

    expect(fn () => app(DailyCloseLockService::class)->lockMonth($cid, 2026, 9, $fixture['user']->id))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($close->fresh()->is_locked)->toBeFalse()
        ->and(DB::table('fuel.month_profit_snapshots')->where('company_id', $cid)->count())->toBe(0);
});

test('station net profit replaces book fuel profit including its write-down without duplicating expenses', function () {
    $fixture = creditCloseFixture();
    $cid = $fixture['company']->id;
    $item = Item::where('company_id', $cid)->where('sku', 'PETROL')->sole();
    $item->update(['fuel_category' => 'petrol', 'income_account_id' => $fixture['accounts']['4100']->id, 'expense_account_id' => $fixture['accounts']['5100']->id]);
    Warehouse::where('company_id', $cid)->update(['warehouse_type' => 'tank', 'capacity' => 40000]);
    RateChange::create(['company_id' => $cid, 'item_id' => $item->id, 'effective_date' => '2026-09-01', 'purchase_rate' => 400, 'sale_rate' => 450]);
    RateChange::create(['company_id' => $cid, 'item_id' => $item->id, 'effective_date' => '2026-10-01', 'purchase_rate' => 395.80, 'sale_rate' => 440]);
    $expense = \App\Modules\Accounting\Models\Account::create(['company_id' => $cid, 'code' => '5600', 'name' => 'Station expenses', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit', 'is_active' => true]);
    $tx = Transaction::create([
        'company_id' => $cid, ...fixtureYearAndPeriod($cid), 'transaction_number' => 'NET-BRIDGE',
        'transaction_type' => 'fuel_daily_close', 'transaction_date' => '2026-09-30', 'posting_date' => '2026-09-30',
        'description' => 'Fuel sales, cost, stock write-down and operating expense', 'currency' => 'PKR',
        'total_debit' => 50000, 'total_credit' => 50000, 'status' => 'posted', 'metadata' => [],
    ]);
    foreach ([[$fixture['accounts']['4100']->id, 0, 50000], [$fixture['accounts']['5100']->id, 30200, 0], [$expense->id, 1000, 0], [$fixture['accounts']['1050']->id, 18800, 0]] as $i => [$accountId, $debit, $credit]) {
        DB::table('acct.journal_entries')->insert(['id' => (string) str()->uuid(), 'company_id' => $cid, 'transaction_id' => $tx->id,
            'account_id' => $accountId, 'line_number' => $i + 1, 'debit_amount' => $debit, 'credit_amount' => $credit, 'created_at' => now(), 'updated_at' => now()]);
    }
    $statement = Mockery::mock(StockStatementService::class);
    $statement->shouldReceive('run')->once()->andReturn([
        'rows' => [['date' => '2026-09-30', 'dip' => 20, 'physical_reading_complete' => true]],
        'totals' => ['opening' => 0, 'closing' => 20, 'received' => 95, 'sold' => 75, 'sale_amount' => 50000, 'purchase_amount' => 38000],
    ]);
    app()->instance(StockStatementService::class, $statement);

    $result = app(MonthlyFuelProfitService::class)->run($cid, '2026-09');
    expect($result['totals']['fuel_profit'])->toBe(19916.0)
        ->and($result['reconciliation']['book_fuel_profit'])->toBe(19800.0)
        ->and($result['reconciliation']['book_net_profit'])->toBe(18800.0)
        ->and($result['reconciliation']['adjustment'])->toBe(116.0)
        ->and($result['reconciliation']['net_station_profit'])->toBe(18916.0);
});

test('station settings persist the selected method and reject an unsupported method', function () {
    $fixture = creditCloseFixture();
    $company = $fixture['company'];
    $company->update(['settings' => ['modules' => ['fuel_station' => true]]]);
    $admin = \App\Models\User::factory()->create(['id' => '00000000-0000-0000-0000-'.bin2hex(random_bytes(6))]);
    $url = "/{$company->slug}/fuel/settings";

    $this->actingAs($admin)->put($url, ['month_end_stock_valuation' => 'next_month_purchase_rate'])
        ->assertRedirect()->assertSessionHas('success');
    expect(StationSettings::where('company_id', $company->id)->value('month_end_stock_valuation'))->toBe('next_month_purchase_rate');
    $this->actingAs($admin)->put($url, ['month_end_stock_valuation' => 'invent_a_profit'])
        ->assertSessionHasErrors('month_end_stock_valuation');
    expect(StationSettings::where('company_id', $company->id)->value('month_end_stock_valuation'))->toBe('next_month_purchase_rate');
});
