<?php

use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Services\CommandBus;
use App\Services\CompanyContextService;

require_once __DIR__.'/../Accounting/OpeningBalanceFixtures.php';

/**
 * Task C: adding an Amanat holder from Fuel > Amanat can carry an optional opening balance,
 * either way - "Station holds for them" (amanat) or "They owe the station" (credit) - going
 * through opening_balance.set_party exactly like Quick Add does (see
 * QuickAddOpeningBalanceTest.php, which this mirrors), inside the same transaction as the
 * holder's own creation so a refused opening rolls the holder back too.
 *
 * Reuses openingBalanceHttpFixture() from Accounting/OpeningBalanceFixtures.php (cash/bank/AR/
 * amanat/partner accounts, an owner user with every permission) and turns the fuel_station
 * module on for it, since AmanatController's routes sit behind require.module:fuel_station.
 */
function amanatHolderFixture(): array
{
    $f = openingBalanceHttpFixture();
    $f['company']->settings = array_merge((array) $f['company']->settings, ['modules' => ['fuel_station' => true]]);
    $f['company']->save();

    return $f;
}

function openingViewFor(array $f): array
{
    return app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(CommandBus::class)->dispatch('opening_balance.view', [], $f['user'], true)
    );
}

test('a holder created with "station holds for them" gets an opening amanat row', function () {
    $f = amanatHolderFixture();

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/amanat", [
        'name' => 'Amanat Depositor',
        'opening_kind' => 'holds',
        'opening_amount' => 8000,
        'opening_date' => '2026-08-01',
    ]);

    $response->assertSessionHasNoErrors();
    $customer = Customer::where('company_id', $f['company']->id)->where('name', 'Amanat Depositor')->firstOrFail();
    expect(CustomerProfile::where('company_id', $f['company']->id)->where('customer_id', $customer->id)->value('is_amanat_holder'))->toBeTrue();

    $view = openingViewFor($f);
    expect(collect($view['rows']['amanat']))
        ->first(fn ($r) => $r['customer_id'] === $customer->id)
        ->amount->toBe(8000.0);
    expect(collect($view['rows']['credit_customers'])->firstWhere('customer_id', $customer->id))->toBeNull();
});

test('a holder created with "they owe the station" gets an opening credit_customers row', function () {
    $f = amanatHolderFixture();

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/amanat", [
        'name' => 'Credit Holder',
        'opening_kind' => 'owes',
        'opening_amount' => 12000,
        'opening_date' => '2026-08-01',
    ]);

    $response->assertSessionHasNoErrors();
    $customer = Customer::where('company_id', $f['company']->id)->where('name', 'Credit Holder')->firstOrFail();
    expect(CustomerProfile::where('company_id', $f['company']->id)->where('customer_id', $customer->id)->value('is_credit_customer'))->toBeTrue();

    $view = openingViewFor($f);
    expect(collect($view['rows']['credit_customers']))
        ->first(fn ($r) => $r['customer_id'] === $customer->id)
        ->amount->toBe(12000.0);
    expect(collect($view['rows']['amanat'])->firstWhere('customer_id', $customer->id))->toBeNull();
});

test('an amount with no date and no existing opening position refuses, and the holder is not created', function () {
    $f = amanatHolderFixture();

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/amanat", [
        'name' => 'Undated Holder',
        'opening_kind' => 'holds',
        'opening_amount' => 5000,
    ]);

    $response->assertSessionHasErrors('opening_date');
    expect(Customer::where('company_id', $f['company']->id)->where('name', 'Undated Holder')->exists())->toBeFalse();
});

test('a holder with no opening amount is created as plain as before', function () {
    $f = amanatHolderFixture();

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/amanat", [
        'name' => 'Plain Holder',
    ]);

    $response->assertSessionHasNoErrors();
    $customer = Customer::where('company_id', $f['company']->id)->where('name', 'Plain Holder')->firstOrFail();
    expect(CustomerProfile::where('company_id', $f['company']->id)->where('customer_id', $customer->id)->value('is_amanat_holder'))->toBeTrue();

    $view = openingViewFor($f);
    expect(collect($view['rows']['amanat'])->firstWhere('customer_id', $customer->id))->toBeNull();
    expect(collect($view['rows']['credit_customers'])->firstWhere('customer_id', $customer->id))->toBeNull();
});
