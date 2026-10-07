<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Services\BalanceSheetReportService;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\TrialBalanceReportService;
use Illuminate\Support\Facades\DB;

/**
 * The only accounting report the app had was a profit and loss. A P&L covers a period and
 * says nothing about what the business owns or owes — at a pump most of the money is fuel in
 * the tanks, cash owed by credit buyers and cash owed to the supplier, none of which appear
 * there. And nothing proved the ledger was internally consistent in the first place.
 *
 * The books below are deliberately small enough to check by hand:
 *
 *   Capital introduced   Dr Cash      100,000  Cr Capital     100,000
 *   Fuel bought on account  Dr Stock   60,000  Cr Payables     60,000
 *   Fuel sold for cash   Dr Cash       50,000  Cr Revenue      50,000
 *                        Dr COGS       30,000  Cr Stock        30,000
 *   Discount given       Dr Discounts   2,000  Cr Cash          2,000
 *
 *   Cash 148,000 · Stock 30,000 · Payables 60,000 · Capital 100,000
 *   Revenue 50,000 · Discounts 2,000 · COGS 30,000
 */
function ledgerFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Ledger Co', 'slug' => 'ledger-co-'.str()->random(8), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);

    $a = [];
    foreach ([
        ['1050', 'Cash on Hand', 'asset', 'cash', 'debit'],
        ['1200', 'Fuel Inventory', 'asset', 'inventory', 'debit'],
        ['2100', 'Accounts Payable', 'liability', 'accounts_payable', 'credit'],
        ['3100', 'Owner Capital', 'equity', 'equity', 'credit'],
        ['4100', 'Fuel Sales', 'revenue', 'sales', 'credit'],
        ['4210', 'Sales Discounts', 'revenue', 'sales', 'debit'],
        ['5100', 'Cost of Fuel Sold', 'cogs', 'cogs', 'debit'],
    ] as [$code, $name, $type, $subtype, $normal]) {
        $a[$code] = Account::create([
            'company_id' => $company->id, 'code' => $code, 'name' => $name, 'type' => $type,
            'subtype' => $subtype, 'normal_balance' => $normal, 'is_active' => true,
            'is_contra' => $code === '4210',
        ]);
    }

    $post = function (string $description, array $lines, string $date = '2026-09-10') use ($company) {
        app(GlPostingService::class)->postBalancedTransaction([
            'company_id' => $company->id, 'transaction_type' => 'journal', 'date' => $date,
            'currency' => 'PKR', 'description' => $description,
        ], $lines);
    };

    $post('Capital introduced', [
        ['account_id' => $a['1050']->id, 'type' => 'debit', 'amount' => 100000],
        ['account_id' => $a['3100']->id, 'type' => 'credit', 'amount' => 100000],
    ]);
    $post('Fuel bought on account', [
        ['account_id' => $a['1200']->id, 'type' => 'debit', 'amount' => 60000],
        ['account_id' => $a['2100']->id, 'type' => 'credit', 'amount' => 60000],
    ]);
    $post('Fuel sold for cash', [
        ['account_id' => $a['1050']->id, 'type' => 'debit', 'amount' => 50000],
        ['account_id' => $a['4100']->id, 'type' => 'credit', 'amount' => 50000],
        ['account_id' => $a['5100']->id, 'type' => 'debit', 'amount' => 30000],
        ['account_id' => $a['1200']->id, 'type' => 'credit', 'amount' => 30000],
    ]);
    $post('Discount given', [
        ['account_id' => $a['4210']->id, 'type' => 'debit', 'amount' => 2000],
        ['account_id' => $a['1050']->id, 'type' => 'credit', 'amount' => 2000],
    ]);

    return ['user' => $user, 'company' => $company, 'accounts' => $a];
}

test('statement value trails reconcile ledger accounts and preserve report figures', function () {
    $f = ledgerFixture();
    $company = $f['company'];
    $trial = app(TrialBalanceReportService::class)->run($company->id, '2026-09-30', true);
    foreach (['debit', 'credit'] as $side) {
        $node = $trial['valueTrail']['nodes']['total:'.$side];
        expect(array_sum(array_map(fn ($id) => $trial['valueTrail']['nodes'][$id]['value'], $node['children'])))->toBe($trial['totals'][$side]);
    }
    unset($trial['valueTrail']);
    expect($trial)->toBe(app(TrialBalanceReportService::class)->run($company->id, '2026-09-30'));
    $balance = app(BalanceSheetReportService::class)->run($company->id, '2026-09-30', true);
    $graph = $balance['valueTrail'];
    unset($balance['valueTrail']);
    expect($balance)->toBe(app(BalanceSheetReportService::class)->run($company->id, '2026-09-30'));
    foreach (['assets', 'liabilities', 'equity'] as $section) {
        $root = $graph['nodes']['total:'.$section];
        expect(array_sum(array_map(fn ($id) => $graph['nodes'][$id]['value'], $root['children'])))->toBe($root['value']);
    }
    $profit = app(\App\Modules\Accounting\Services\ProfitLossReportService::class)->run($company->id, '2026-09-01', '2026-09-30', true);
    expect($profit['valueTrail']['nodes']['total:profit']['value'])->toBe(18000.0);
    $discount = $profit['valueTrail']['nodes']['account:'.$f['accounts']['4210']->id];
    expect($discount['value'])->toBe(-2000.0);
    $statement = app(\App\Modules\Accounting\Services\AccountStatementService::class)->statement($f['accounts']['1050'], '2026-09-01', '2026-09-30', false, true);
    $graph = $statement['valueTrail'];
    $presenter = app(\App\Modules\Accounting\Services\StatementValueTrail::class);
    expect($presenter->present(fn () => $graph, $f['user'], $company->slug))->toBeNull();
    $root = $graph['nodes']['statement:closing'];
    expect($root['value'])->toBe(148000.0)
        ->and(array_sum(array_map(fn ($id) => $graph['nodes'][$id]['value'], $root['children'])))->toBe(148000.0);
});

test('statement evidence honours document permissions and the personal off switch', function () {
    $user = \Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isGodMode')->andReturn(false);
    $user->shouldReceive('showsValueTrails')->andReturn(true);
    $user->shouldReceive('hasCompanyPermission')->andReturnUsing(fn ($permission) => $permission === \App\Constants\Permissions::REPORT_VIEW);
    $service = app(\App\Modules\Accounting\Services\StatementValueTrail::class);
    $graph = ['nodes' => [
        'journal' => ['source' => ['transaction_id' => 'private-journal', 'date' => '2026-09-01']],
        'invoice' => ['source' => ['document_link' => 'invoices/private-invoice', 'label' => 'Private invoice', 'date' => '2026-09-01', 'recorded_at' => null]],
    ], 'roots' => []];
    $presented = $service->present(fn () => $graph, $user, 'company');
    expect($presented['nodes']['journal']['source'])->toBe(['restricted' => true])
        ->and($presented['nodes']['invoice']['source'])->toBe(['restricted' => true]);
    $off = \Mockery::mock(User::class)->makePartial();
    $off->shouldReceive('showsValueTrails')->andReturn(false);
    $called = false;
    expect($service->present(function () use (&$called) {
        $called = true;

        return [];
    }, $off, 'company'))->toBeNull()
        ->and($called)->toBeFalse();
});

test('the trial balance puts every account on its own side and the two columns agree', function () {
    $f = ledgerFixture();

    $report = app(TrialBalanceReportService::class)->run($f['company']->id, '2026-09-30');
    $rows = collect($report['rows'])->keyBy('code');

    expect($rows['1050']['debit'])->toBe(148000.0)
        ->and($rows['1200']['debit'])->toBe(30000.0)
        ->and($rows['2100']['credit'])->toBe(60000.0)
        ->and($rows['3100']['credit'])->toBe(100000.0)
        ->and($rows['4100']['credit'])->toBe(50000.0)
        ->and($rows['4210']['debit'])->toBe(2000.0)
        ->and($rows['5100']['debit'])->toBe(30000.0);

    expect($report['totals']['debit'])->toBe(210000.0)
        ->and($report['totals']['credit'])->toBe(210000.0)
        ->and($report['is_balanced'])->toBeTrue();
});

test('the trial balance reports each account on one side only', function () {
    $f = ledgerFixture();

    $rows = collect(app(TrialBalanceReportService::class)->run($f['company']->id, '2026-09-30')['rows']);

    $rows->each(function ($row) {
        expect($row['debit'] === 0.0 || $row['credit'] === 0.0)->toBeTrue(
            "Account {$row['code']} was reported on both sides at once."
        );
    });
});

test('the trial balance stops at the as-of date', function () {
    $f = ledgerFixture();

    $report = app(TrialBalanceReportService::class)->run($f['company']->id, '2026-09-09');

    expect($report['rows'])->toBe([])
        ->and($report['totals']['debit'])->toBe(0.0)
        ->and($report['is_balanced'])->toBeTrue();
});

test('the balance sheet balances, carrying the period result into equity', function () {
    $f = ledgerFixture();

    $sheet = app(BalanceSheetReportService::class)->run($f['company']->id, '2026-09-30');

    expect($sheet['totals']['assets'])->toBe(178000.0)
        ->and($sheet['totals']['liabilities'])->toBe(60000.0)
        // Capital 100,000 plus the 18,000 earned so far (50,000 - 2,000 - 30,000).
        ->and($sheet['totals']['equity'])->toBe(118000.0)
        ->and($sheet['totals']['liabilities_and_equity'])->toBe(178000.0)
        ->and($sheet['is_balanced'])->toBeTrue();

    expect($sheet['retained_earnings'])->toBe(18000.0);
});

test('the balance sheet keeps profit and loss accounts out of the asset and liability sections', function () {
    $f = ledgerFixture();

    $sheet = app(BalanceSheetReportService::class)->run($f['company']->id, '2026-09-30');
    $codes = collect($sheet['assets'])->pluck('code')
        ->merge(collect($sheet['liabilities'])->pluck('code'))
        ->merge(collect($sheet['equity'])->pluck('code'))
        ->all();

    expect($codes)->not->toContain('4100')
        ->and($codes)->not->toContain('4210')
        ->and($codes)->not->toContain('5100');
});
