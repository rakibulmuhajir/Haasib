<?php

use App\Models\User;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Vendor;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/OpeningBalanceFixtures.php';

/**
 * Quick Add's optional opening-balance fields (customer: "Owes us" / "Paid in advance",
 * vendor: "We owe them") must never write an invoice, bill or journal on their own. They go
 * through opening_balance.set_party, the same command-bus door the Opening Balances page and
 * the bank-account page use, so every guard SaveAction carries (the lock, the advisory lock,
 * the date bound) applies to a party created in passing exactly as it does anywhere else.
 *
 * Fixtures come from OpeningBalanceFixtures.php, required above so this file also runs on its
 * own rather than only as part of a whole-directory run.
 */
function openingView(array $f): array
{
    return app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(CommandBus::class)->dispatch('opening_balance.view', [], $f['user'], true)
    );
}

/**
 * A second user in the same company, with customer/vendor create rights (the 'manager' role
 * has both 'customer.create' and 'opening_balance.manage' out of the box) but with
 * opening_balance.manage revoked, so only that one permission is missing.
 */
function userWithoutOpeningPermission(array $f): User
{
    $user = User::factory()->withoutTwoFactor()->create();

    DB::table('auth.company_user')->insert([
        'company_id' => $f['company']->id,
        'user_id' => $user->id,
        'role' => 'manager',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CompanyContextService::class)->withContext($f['company'], function () use ($f, $user) {
        app(CompanyContextService::class)->assignRole($user, 'manager');
        app(CommandBus::class)->dispatch('role.revoke', [
            'role' => 'manager',
            'permission' => \App\Constants\Permissions::OPENING_BALANCE_MANAGE,
        ], $f['user'], true);
    });

    return $user;
}

test('customer quick-add with an owed amount and a date creates an opening receivable through the opening set', function () {
    $f = openingBalanceHttpFixture();

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/customers/quick-store", [
        'name' => 'Haji Traders',
        'opening_owed' => 50000,
        'opening_date' => '2026-08-01',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect()->assertSessionMissing('error');
    $customer = Customer::where('company_id', $f['company']->id)->where('name', 'Haji Traders')->firstOrFail();

    $view = openingView($f);
    expect($view['as_of_date'])->toBe('2026-08-01');
    expect(collect($view['rows']['credit_customers']))
        ->first(fn ($r) => $r['customer_id'] === $customer->id)
        ->amount->toBe(50000.0);
});

test('customer quick-add with an advance goes to amanat, not credit_customers', function () {
    $f = openingBalanceHttpFixture();

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/customers/quick-store", [
        'name' => 'Amanat Holder',
        'opening_advance' => 15000,
        'opening_date' => '2026-08-01',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect()->assertSessionMissing('error');
    $customer = Customer::where('company_id', $f['company']->id)->where('name', 'Amanat Holder')->firstOrFail();

    $view = openingView($f);
    expect(collect($view['rows']['amanat']))
        ->first(fn ($r) => $r['customer_id'] === $customer->id)
        ->amount->toBe(15000.0);
    expect(collect($view['rows']['credit_customers'])->firstWhere('customer_id', $customer->id))->toBeNull();
});

test('vendor quick-add with an owed amount lands in suppliers', function () {
    $f = openingBalanceHttpFixture();

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/vendors/quick-store", [
        'name' => 'Fuel Supplier Co',
        'opening_owed' => 75000,
        'opening_date' => '2026-08-01',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect()->assertSessionMissing('error');
    $vendor = Vendor::where('company_id', $f['company']->id)->where('name', 'Fuel Supplier Co')->firstOrFail();

    $view = openingView($f);
    expect(collect($view['rows']['suppliers']))
        ->first(fn ($r) => $r['vendor_id'] === $vendor->id)
        ->amount->toBe(75000.0);
});

test('an existing opening line for another customer is preserved', function () {
    $f = openingBalanceHttpFixture();
    $existing = openingCustomer($f, 'Existing Customer');

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-01',
        'credit_customers' => [['customer_id' => $existing->id, 'amount' => 20000]],
    ]);

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/customers/quick-store", [
        'name' => 'New Customer',
        'opening_owed' => 30000,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect()->assertSessionMissing('error');
    $newCustomer = Customer::where('company_id', $f['company']->id)->where('name', 'New Customer')->firstOrFail();

    $view = openingView($f);
    $rows = collect($view['rows']['credit_customers']);
    expect($rows->firstWhere('customer_id', $existing->id))->amount->toBe(20000.0);
    expect($rows->firstWhere('customer_id', $newCustomer->id))->amount->toBe(30000.0);
});

test('no date and no existing position refuses, and the customer is not created', function () {
    $f = openingBalanceHttpFixture();

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/customers/quick-store", [
        'name' => 'Undated Customer',
        'opening_owed' => 10000,
    ]);

    $response->assertSessionHasErrors('opening_date');
    expect(Customer::where('company_id', $f['company']->id)->where('name', 'Undated Customer')->exists())->toBeFalse();
});

test('a locked opening position refuses quick-add, and the customer is not created', function () {
    $f = openingBalanceHttpFixture();

    dispatchOpeningBalance($f, ['as_of_date' => '2026-08-01']);
    app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(CommandBus::class)->dispatch('opening_balance.lock', [], $f['user'], true)
    );

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/customers/quick-store", [
        'name' => 'Locked Out Customer',
        'opening_owed' => 10000,
    ]);

    $response->assertSessionHasErrors('opening_owed');
    expect(Customer::where('company_id', $f['company']->id)->where('name', 'Locked Out Customer')->exists())->toBeFalse();
});

test('a user without opening_balance.manage sending an amount is refused, and the customer is not created', function () {
    $f = openingBalanceHttpFixture();
    $limited = userWithoutOpeningPermission($f);

    $response = test()->actingAs($limited)->post("/{$f['company']->slug}/customers/quick-store", [
        'name' => 'No Permission Customer',
        'opening_owed' => 10000,
        'opening_date' => '2026-08-01',
    ]);

    $response->assertSessionHasErrors('opening_owed');
    expect(Customer::where('company_id', $f['company']->id)->where('name', 'No Permission Customer')->exists())->toBeFalse();
});

test('a customer with no opening amounts is created as plain as before', function () {
    $f = openingBalanceHttpFixture();

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/customers/quick-store", [
        'name' => 'Plain Customer',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect()->assertSessionMissing('error');
    expect(Customer::where('company_id', $f['company']->id)->where('name', 'Plain Customer')->exists())->toBeTrue();
});
