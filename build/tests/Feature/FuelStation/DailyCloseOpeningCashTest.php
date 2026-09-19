<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

/**
 * Minimal copy of Accounting\OpeningBalancesTest's fixture — kept local rather than shared
 * so this file doesn't depend on load order pulling in the Accounting test's globals.
 */
function dailyCloseOpeningCashFixture(): array
{
    $user = User::factory()->create();

    $company = Company::create([
        'name' => 'Daily Close Opening Cash Test',
        'slug' => 'daily-close-opening-cash-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ]);

    enterCompany($company);

    if (! DB::table('public.currencies')->where('code', 'PKR')->exists()) {
        DB::table('public.currencies')->insert(['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs']);
    }

    $fy = FiscalYear::create([
        'company_id' => $company->id,
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'open',
    ]);
    foreach ([8 => ['2026-08-01', '2026-08-31'], 9 => ['2026-09-01', '2026-09-30']] as $n => [$start, $end]) {
        AccountingPeriod::create([
            'company_id' => $company->id,
            'fiscal_year_id' => $fy->id,
            'name' => "P{$n} 2026",
            'period_number' => $n,
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    $cash = Account::create([
        'company_id' => $company->id,
        'code' => '1050',
        'name' => 'Cash on Hand',
        'type' => 'asset',
        'subtype' => 'cash',
        'normal_balance' => 'debit',
        'currency' => 'PKR',
        'is_active' => true,
    ]);

    return compact('company', 'user', 'cash');
}

test('opening cash saved via opening balances reaches the first daily close as a ledger balance', function () {
    $f = dailyCloseOpeningCashFixture();

    test()->actingAs($f['user']);
    app(CompanyContextService::class)->withContext($f['company'], function () use ($f) {
        app(CommandBus::class)->dispatch('opening_balance.save', [
            'as_of_date' => '2026-08-31',
            'cash' => ['amount' => 150000],
        ], $f['user'], true);
    });

    $result = app(DailyCloseService::class)->getPreviousDayClosing($f['company']->id, '2026-09-01');

    expect($result['closing_cash'])->toBe(150000.0)
        ->and($result['exists'])->toBeFalse()
        ->and($result['source'])->toBe('ledger');
});
