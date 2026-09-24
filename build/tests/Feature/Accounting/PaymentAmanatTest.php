<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Payment;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * Local HTTP fixture, modelled on AmanatHistoryTest's amanatHistoryHttpFixture() - kept
 * local rather than shared so this file doesn't depend on load order. Builds a company
 * with a cash account, an AR account, and (when $fuelStation) the fuel_station module
 * enabled, so PaymentController::store() can take either the 'invoices' or 'amanat' path.
 */
function paymentAmanatHttpFixture(bool $fuelStation = true): array
{
    $user = User::factory()->create();

    $company = Company::create([
        'name' => 'Payment Amanat Test',
        'slug' => 'payment-amanat-test-'.str()->lower(str()->random(8)),
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
        'code' => '1000',
        'name' => 'Cash',
        'type' => 'asset',
        'subtype' => 'cash',
        'normal_balance' => 'debit',
        'currency' => 'PKR',
        'is_active' => true,
    ]);

    Account::create([
        'company_id' => $company->id,
        'code' => '1100',
        'name' => 'Accounts Receivable',
        'type' => 'asset',
        'subtype' => 'accounts_receivable',
        'normal_balance' => 'debit',
        'currency' => 'PKR',
        'is_active' => true,
    ]);

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

test('a fuel company can receive a payment as amanat instead of applying it to invoices', function () {
    travelTo('2026-09-15');
    $f = paymentAmanatHttpFixture(fuelStation: true);

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'amount' => 5000,
        'payment_method' => 'cash',
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
        'reference_number' => 'AMN-1',
        'notes' => 'Held for later fuel',
        'received_as' => 'amanat',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $transaction = AmanatTransaction::where('customer_id', $f['customer']->id)->sole();
    expect($transaction->transaction_type)->toBe(AmanatTransaction::TYPE_DEPOSIT)
        ->and((float) $transaction->amount)->toBe(5000.0)
        ->and($transaction->payment_account_id)->toBe($f['cash']->id);

    $profile = CustomerProfile::where('company_id', $f['company']->id)
        ->where('customer_id', $f['customer']->id)
        ->sole();
    expect($profile->is_amanat_holder)->toBeTrue();

    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('a non-fuel company cannot receive a payment as amanat', function () {
    travelTo('2026-09-15');
    $f = paymentAmanatHttpFixture(fuelStation: false);

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'amount' => 5000,
        'payment_method' => 'cash',
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
        'received_as' => 'amanat',
    ]);

    $response->assertSessionHasErrors('received_as');

    expect(AmanatTransaction::where('customer_id', $f['customer']->id)->count())->toBe(0);
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('a payment with no received_as still applies to the customer account as before', function () {
    travelTo('2026-09-15');
    $f = paymentAmanatHttpFixture(fuelStation: true);

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'amount' => 1500,
        'currency' => 'PKR',
        'payment_method' => 'cash',
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
    ]);

    $response->assertSessionHasNoErrors();

    expect(Payment::where('company_id', $f['company']->id)->where('customer_id', $f['customer']->id)->exists())->toBeTrue();
});
