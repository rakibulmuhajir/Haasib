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
