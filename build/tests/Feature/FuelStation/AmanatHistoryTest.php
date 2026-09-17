<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * Minimal local copy of Accounting\OpeningBalancesTest's HTTP fixture — kept local rather
 * than shared so this file doesn't depend on load order pulling in the Accounting test's
 * globals.
 */
function amanatHistoryHttpFixture(): array
{
    $user = User::factory()->create();

    $company = Company::create([
        'name' => 'Amanat History Test',
        'slug' => 'amanat-history-test-'.str()->lower(str()->random(8)),
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

    Account::create([
        'company_id' => $company->id,
        'code' => '2200',
        'name' => 'Customer Amanat Deposits',
        'type' => 'liability',
        'subtype' => 'other_current_liability',
        'normal_balance' => 'credit',
        'currency' => 'PKR',
        'is_active' => true,
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");

    app(CompanyRbacBootstrapper::class)->bootstrap($company);

    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($user, 'owner'),
    );

    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    $company->enableModule('fuel_station');

    return compact('company', 'user');
}

test('opening amanat history shows the entry date, not the created_at date', function () {
    $f = amanatHistoryHttpFixture();
    $company = $f['company'];
    $user = $f['user'];

    $customer = Customer::create([
        'company_id' => $company->id,
        'customer_number' => 'CUST-'.str()->upper(str()->random(5)),
        'name' => 'Haji Saab',
        'customer_type' => 'business',
        'base_currency' => 'PKR',
    ]);

    test()->actingAs($user);
    app(CompanyContextService::class)->withContext($company, function () use ($customer, $user) {
        app(CommandBus::class)->dispatch('opening_balance.save', [
            'as_of_date' => '2026-08-31',
            'amanat' => [['customer_id' => $customer->id, 'amount' => 30000]],
        ], $user, true);
    });

    $response = test()->actingAs($user)->get("/{$company->slug}/fuel/amanat/{$customer->id}");
    $response->assertOk();

    $transactions = $response->viewData('page')['props']['transactions']['data'];
    expect($transactions)->not->toBeEmpty();
    expect($transactions[0]['transaction_date'])->toBe('2026-08-31');
});


test('amanat business-date ordering happens before pagination with deterministic ties', function () {
    $f = amanatHistoryHttpFixture();
    $customer = Customer::create(['company_id' => $f['company']->id, 'customer_number' => 'PAGING', 'name' => 'Paging', 'base_currency' => 'PKR']);
    test()->actingAs($f['user']);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.save', [
        'as_of_date' => '2026-08-31', 'amanat' => [['customer_id' => $customer->id, 'amount' => 100]],
    ], $f['user'], true));
    $opening = \App\Modules\FuelStation\Models\AmanatTransaction::where('customer_id', $customer->id)->firstOrFail();
    $opening->update(['created_at' => '2026-09-20 12:00:00']);
    $ids = [];
    for ($i = 0; $i < 51; $i++) {
        $ids[] = \App\Modules\FuelStation\Models\AmanatTransaction::create([
            'company_id' => $f['company']->id, 'customer_id' => $customer->id,
            'transaction_type' => 'deposit', 'amount' => 1, 'recorded_by_user_id' => $f['user']->id,
            'created_at' => '2026-09-10 12:00:00',
        ])->id;
    }
    rsort($ids);
    $url = "/{$f['company']->slug}/fuel/amanat/{$customer->id}";
    $first = test()->get($url)->assertOk()->viewData('page')['props']['transactions'];
    $second = test()->get($url.'?page=2')->assertOk()->viewData('page')['props']['transactions'];
    expect(array_column($first['data'], 'id'))->toBe(array_slice($ids, 0, 50));
    expect(array_column($second['data'], 'id'))->toBe([$ids[50], $opening->id]);
    expect($second['data'][1]['transaction_date'])->toBe('2026-08-31');
});
