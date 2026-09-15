<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

function openingBalanceFixture(): array
{
    $user = User::factory()->create();

    $company = Company::create([
        'name' => 'Opening Balance Test',
        'slug' => 'opening-balance-test-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ]);

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

    $mk = fn (string $code, string $name, string $type, string $subtype, string $normal) => Account::create([
        'company_id' => $company->id,
        'code' => $code,
        'name' => $name,
        'type' => $type,
        'subtype' => $subtype,
        'normal_balance' => $normal,
        'currency' => 'PKR',
        'is_active' => true,
    ]);

    $accounts = [
        'cash' => $mk('1050', 'Cash on Hand', 'asset', 'cash', 'debit'),
        'bank' => $mk('1000', 'HBL Current', 'asset', 'bank', 'debit'),
        'bank2' => $mk('1010', 'UBL Card Settlement', 'asset', 'bank', 'debit'),
        'ar' => $mk('1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit'),
        'advances' => $mk('1150', 'Employee Advances', 'asset', 'other_current_asset', 'debit'),
        'ap' => $mk('2100', 'Accounts Payable', 'liability', 'accounts_payable', 'credit'),
        'amanat' => $mk('2200', 'Customer Amanat Deposits', 'liability', 'other_current_liability', 'credit'),
        'partner' => $mk('2210', 'Investor Deposits', 'liability', 'other_current_liability', 'credit'),
    ];

    return compact('company', 'user', 'accounts');
}

function dispatchOpeningBalance(array $fixture, array $params): array
{
    test()->actingAs($fixture['user']);

    return app(CompanyContextService::class)->withContext($fixture['company'], function () use ($fixture, $params) {
        return app(CommandBus::class)->dispatch('opening_balance.save', $params, $fixture['user'], true);
    });
}

function ledgerBalance(Account $account): float
{
    $rows = DB::table('acct.journal_entries')->where('account_id', $account->id)
        ->selectRaw('COALESCE(SUM(debit_amount),0) as d, COALESCE(SUM(credit_amount),0) as c')->first();
    return round((float) $rows->d - (float) $rows->c, 2);
}

test('saving cash and bank opening balances posts one balanced journal against opening balance equity', function () {
    $f = openingBalanceFixture();

    $result = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'cash' => ['amount' => 150000],
        'banks' => [
            ['account_id' => $f['accounts']['bank']->id, 'amount' => 900000],
            ['account_id' => $f['accounts']['bank2']->id, 'amount' => 50000],
        ],
    ]);

    $journal = Transaction::find($result['data']['journal_id']);
    expect($journal)->not->toBeNull()
        ->and($journal->transaction_type)->toBe('opening_balance')
        ->and($journal->reference_type)->toBe('acct.opening_balances')
        ->and($journal->transaction_date->toDateString())->toBe('2026-08-31');

    $entries = $journal->journalEntries;
    expect((float) $entries->sum('debit_amount'))->toBe((float) $entries->sum('credit_amount'));

    $equity = Account::where('company_id', $f['company']->id)->where('code', '3080')->first();
    expect($equity)->not->toBeNull()->and($equity->type)->toBe('equity');

    expect(ledgerBalance($f['accounts']['cash']))->toBe(150000.0)
        ->and(ledgerBalance($f['accounts']['bank']))->toBe(900000.0)
        ->and(ledgerBalance($f['accounts']['bank2']))->toBe(50000.0)
        ->and(ledgerBalance($equity))->toBe(-1100000.0);

    $settings = $f['company']->fresh()->settings;
    expect($settings['opening_balances']['as_of_date'])->toBe('2026-08-31')
        ->and($settings['opening_balances']['locked_at'])->toBeNull();
});
