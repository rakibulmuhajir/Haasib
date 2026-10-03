<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A fuel station's company home is its own page (Today / History); the old product and tank setup
 * page it used to open on became Products & stock at /fuel/products. Other companies keep company/Show.
 */
function fuelHomeFixture(bool $fuelStation = true): array
{
    $user = User::factory()->withoutTwoFactor()->create();

    $company = Company::create([
        'name' => $fuelStation ? 'Fuel Home Station' : 'Plain Trading Co',
        'slug' => 'fuel-home-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ]);

    if (! DB::table('public.currencies')->where('code', 'PKR')->exists()) {
        DB::table('public.currencies')->insert(['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs']);
    }

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    enterCompany($company);

    if ($fuelStation) {
        $company->enableModule('fuel_station');
    }

    return compact('company', 'user');
}

/** The fiscal year and the month's period a close on $date must sit in (made once, reused). */
function fuelHomePeriodFor(Company|string $company, string $date): array
{
    $companyId = is_string($company) ? $company : $company->id;
    $day = \Carbon\Carbon::parse($date);
    $fy = \App\Modules\Accounting\Models\FiscalYear::firstOrCreate(
        ['company_id' => $companyId, 'name' => '2026'],
        ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']
    );
    $period = \App\Modules\Accounting\Models\AccountingPeriod::firstOrCreate(
        ['company_id' => $companyId, 'fiscal_year_id' => $fy->id, 'period_number' => $day->month],
        ['name' => $day->format('F'), 'start_date' => $day->copy()->startOfMonth()->toDateString(), 'end_date' => $day->copy()->endOfMonth()->toDateString()]
    );

    return ['fiscal_year_id' => $fy->id, 'period_id' => $period->id, 'base_currency' => 'PKR'];
}

function fuelHomeClose(Company $company, string $date): Transaction
{
    return Transaction::create([
        'company_id' => $company->id,
        ...fuelHomePeriodFor($company, $date),
        'transaction_number' => 'DC-'.$date.'-'.str()->random(4),
        'transaction_type' => 'fuel_daily_close',
        'transaction_date' => $date,
        'posting_date' => $date,
        'description' => 'Daily close '.$date,
        'currency' => 'PKR',
        'total_debit' => 0,
        'total_credit' => 0,
        'status' => 'posted',
        'metadata' => [
            'total_revenue' => 0,
            'posting_snapshot' => ['totals' => ['opening_cash' => 0, 'money_in' => 0, 'money_out' => 0, 'closing_cash' => 0, 'variance' => 0, 'total_revenue' => 0]],
        ],
    ]);
}

test('a fuel station home renders the station home with today props', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = fuelHomeFixture();
    fuelHomeClose($f['company'], '2026-09-22');

    test()->actingAs($f['user'])
        ->get("/{$f['company']->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FuelStation/Home/Index')
            ->where('company.slug', $f['company']->slug)
            ->where('tab', 'today')
            ->where('today.close.last_date', '2026-09-22')
            ->where('today.close.next_date', '2026-09-23')
            ->has('today.money')
            ->has('today.tanks')
            ->has('today.month')
            ->has('today.rates')
            ->has('today.attention')
            ->missing('history'));
});

test('products and stock live at fuel products', function () {
    $f = fuelHomeFixture();

    test()->actingAs($f['user'])
        ->get("/{$f['company']->slug}/fuel/products")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FuelStation/Products/Index')
            ->has('rows')
            ->has('summary'));
});

test('a company that is not a fuel station still opens company show', function () {
    $f = fuelHomeFixture(false);

    test()->actingAs($f['user'])
        ->get("/{$f['company']->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('company/Show')
            ->where('isFuelStation', false));
});

test('the history tab adds the chosen range', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-10-05 10:00:00'));
    $f = fuelHomeFixture();
    fuelHomeClose($f['company'], '2026-09-01');

    test()->actingAs($f['user'])
        ->get("/{$f['company']->slug}?tab=history&from=2026-09-01&to=2026-09-30")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FuelStation/Home/Index')
            ->where('tab', 'history')
            ->where('range.from', '2026-09-01')
            ->where('range.to', '2026-09-30')
            ->where('history.from', '2026-09-01')
            ->where('history.to', '2026-09-30')
            ->where('history.closes.count', 1)
            ->where('history.closes.days', 30)
            ->where('history.profit', fn ($v) => abs((float) $v - (float) app(\App\Modules\FuelStation\Services\ProductProfitabilityReportService::class)->run($f['company']->id, '2026-09-01', '2026-09-30')['totals']['profit']) < 0.01)
            ->has('history.stock')
            ->has('history.money')
            ->has('history.links.stock_statement'));
});
