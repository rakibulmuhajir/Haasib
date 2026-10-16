<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Extensions\PaymentReceiptModes;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An owner of a company with a cash account and a 2200 account named like the
 * general "other" pack's Accrued Expenses. $fuelStation turns the module on.
 */
function receiptModesFixture(bool $fuelStation): array
{
    $user = User::factory()->create();

    $company = Company::create([
        'name' => 'Receipt Modes Test',
        'slug' => 'receipt-modes-'.str()->lower(str()->random(8)),
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
    AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        'name' => 'P9 2026',
        'period_number' => 9,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]);

    $cash = Account::create([
        'company_id' => $company->id,
        'code' => '1000',
        'name' => 'Cash',
        'type' => 'asset',
        'subtype' => 'cash',
        'normal_balance' => 'debit',
        'currency' => 'PKR',
        'is_active' => true,
    ]);

    // Today the "other" pack's 2200 is Accrued Expenses; it must not switch amanat on.
    Account::create([
        'company_id' => $company->id,
        'code' => '2200',
        'name' => 'Accrued Expenses',
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

    if ($fuelStation) {
        $company->enableModule('fuel_station');
    }

    $customer = Customer::create([
        'company_id' => $company->id,
        'customer_number' => 'CUST-'.str()->upper(str()->random(5)),
        'name' => 'Haji Saab',
        'customer_type' => 'business',
        'base_currency' => 'PKR',
    ]);

    return compact('company', 'user', 'cash', 'customer');
}

test('a general company with account 2200 is offered no amanat mode', function () {
    $f = receiptModesFixture(fuelStation: false);

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/payments/create")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounting/payments/Create', false)
            ->where('receiptModes', []));
});

test('a fuel company user with the amanat permission gets the amanat mode', function () {
    $f = receiptModesFixture(fuelStation: true);

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/payments/create")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounting/payments/Create', false)
            ->has('receiptModes', 1)
            ->where('receiptModes.0.key', 'amanat')
            ->where('receiptModes.0.label', 'Amanat (advance held for the customer)')
            ->where('receiptModes.0.hint', 'Held for the customer and used up against fuel. Shows on their amanat page.'));
});

test('posting received_as=amanat for a general company is rejected', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = receiptModesFixture(fuelStation: false);

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'amount' => 5000,
        'payment_method' => 'cash',
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
        'received_as' => 'amanat',
    ])->assertSessionHasErrors('received_as');
});

test('posting an unknown received_as is rejected, not silently treated as invoices', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = receiptModesFixture(fuelStation: true);

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'amount' => 5000,
        'payment_method' => 'cash',
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
        'received_as' => 'nonsense',
    ])->assertSessionHasErrors(['received_as' => 'This option is not available.']);
});

test('a registered mode is offered with its hint and handles the post', function () {
    $f = receiptModesFixture(fuelStation: false);

    PaymentReceiptModes::register(
        'test_mode',
        'Test mode',
        fn ($company, $user) => $user !== null,
        fn ($request, $company, array $validated) => redirect('/'.$company->slug.'/payments')->with('success', 'handled'),
        'A hint',
    );

    try {
        $modes = app(PaymentReceiptModes::class)->availableFor($f['company'], $f['user']);
        expect(collect($modes)->firstWhere('key', 'test_mode'))
            ->toBe(['key' => 'test_mode', 'label' => 'Test mode', 'hint' => 'A hint']);

        $this->actingAs($f['user'])->post("/{$f['company']->slug}/payments", [
            'customer_id' => $f['customer']->id,
            'amount' => 100,
            'payment_method' => 'cash',
            'payment_date' => '2026-09-15',
            'deposit_account_id' => $f['cash']->id,
            'received_as' => 'test_mode',
        ])->assertRedirect("/{$f['company']->slug}/payments")->assertSessionHas('success', 'handled');
    } finally {
        // Static registry: drop the test mode and put fuel's back for later tests.
        PaymentReceiptModes::flush();
        $amanat = new \App\Modules\FuelStation\Http\AmanatReceiptMode;
        PaymentReceiptModes::register('amanat', 'Amanat (advance held for the customer)', [$amanat, 'available'], [$amanat, 'store'], 'Held for the customer and used up against fuel. Shows on their amanat page.');
    }
});
