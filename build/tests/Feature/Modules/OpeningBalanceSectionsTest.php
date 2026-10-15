<?php

use App\Models\Company;
use App\Modules\Accounting\Extensions\OpeningBalanceSections;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\FuelStation\OpeningBalances\AmanatOpeningSection;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Accounting/OpeningBalanceFixtures.php';

/**
 * Opening-balance sections are contributed by modules (amanat comes from FuelStation).
 * The shared fixture has an active 2200 account, so these tests show that the account
 * alone no longer gives a company the section: the module does.
 */
function sectionsView(array $f): array
{
    return app(CompanyContextService::class)->withContext(
        $f['company']->fresh(),
        fn () => app(CommandBus::class)->dispatch('opening_balance.view', [], $f['user'], true)
    );
}

test('a general company with a 2200 account gets no amanat opening section', function () {
    $f = openingBalanceHttpFixture();
    // 2200 is "Accrued Expenses" here, an ordinary liability that must never be treated as amanat.
    $f['accounts']['amanat']->update(['name' => 'Accrued Expenses']);

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/accounting/opening-balances")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounting/opening-balances/Index', false)
            ->where('opening.sections', [])
            ->missing('opening.rows.amanat')
            ->where('auth.openingBalance.sections', []));

    $depositor = openingCustomer($f, 'Haji Saab');
    $result = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'cash' => ['amount' => 1000],
        'amanat' => [['customer_id' => $depositor->id, 'amount' => 5000]],
    ]);

    $journal = Transaction::findOrFail($result['data']['journal_id']);
    expect($journal->journalEntries->where('account_id', $f['accounts']['amanat']->id))->toHaveCount(0)
        ->and(ledgerBalance($f['accounts']['amanat']))->toBe(0.0)
        ->and(AmanatTransaction::where('customer_id', $depositor->id)->count())->toBe(0)
        ->and(CustomerProfile::where('customer_id', $depositor->id)->exists())->toBeFalse();
});

test('quick-add with an advance is refused for a company without the amanat section', function () {
    $f = openingBalanceHttpFixture();

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/customers/quick-store", [
        'name' => 'Advance Customer',
        'opening_advance' => 15000,
        'opening_date' => '2026-08-01',
    ])->assertSessionHasErrors('opening_advance');

    expect(Customer::where('company_id', $f['company']->id)->where('name', 'Advance Customer')->exists())->toBeFalse();
});

test('the registry keeps every section for undo but offers only the company\'s own', function () {
    $general = openingBalanceHttpFixture();
    $fuel = openingBalanceHttpFixture();
    $fuel['company']->enableModule('fuel_station');

    $sections = app(OpeningBalanceSections::class);
    expect(array_keys($sections->all()))->toContain('amanat')
        ->and($sections->availableFor($general['company']))->toBe([])
        ->and(array_keys($sections->availableFor($fuel['company'])))->toBe(['amanat']);
});

test('a fuel company sees the amanat section, with the 2200 condition kept for Quick Add', function () {
    $f = openingBalanceHttpFixture();
    $f['company']->enableModule('fuel_station');

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/accounting/opening-balances")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('opening.sections.0.key', 'amanat')
            ->where('opening.sections.0.label', 'Amanat depositors')
            ->where('opening.sections.0.party', 'customer')
            ->where('opening.sections.0.side', 'liability')
            ->where('auth.openingBalance.sections.0.key', 'amanat'));

    expect((new AmanatOpeningSection)->ready($f['company']->fresh()))->toBeTrue();

    $f['accounts']['amanat']->update(['is_active' => false]);
    expect((new AmanatOpeningSection)->ready($f['company']->fresh()))->toBeFalse();
});

test('a fuel company still saves, views and re-enters opening amanat exactly as before', function () {
    // Mirrors OpeningBalancesTest "amanat, employee advance and partner capital openings ..." and
    // "re-saving every category across three generations ...", and QuickAddOpeningBalanceTest
    // "customer quick-add with an advance goes to amanat ...", with their assertions.
    $f = openingBalanceHttpFixture();
    $f['company']->enableModule('fuel_station');
    $depositor = openingCustomer($f, 'Haji Saab');

    $result = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'cash' => ['amount' => 10000],
        'amanat' => [['customer_id' => $depositor->id, 'amount' => 30000]],
    ]);

    $entries = Transaction::findOrFail($result['data']['journal_id'])->journalEntries;
    $line = $entries->first(fn ($e) => $e->account_id === $f['accounts']['amanat']->id && (float) $e->credit_amount === 30000.0);
    expect($line)->not->toBeNull()
        ->and($line->description)->toBe('Opening amanat — Haji Saab');

    $transaction = AmanatTransaction::where('customer_id', $depositor->id)->sole();
    expect($transaction->transaction_type)->toBe(AmanatTransaction::TYPE_DEPOSIT)
        ->and((float) $transaction->amount)->toBe(30000.0)
        ->and($transaction->reference)->toBe('OPENING')
        ->and($transaction->notes)->toBe('Opening balance')
        ->and($transaction->journal_entry_id)->toBe($line->id);
    $profile = CustomerProfile::where('customer_id', $depositor->id)->sole();
    expect($profile->is_amanat_holder)->toBeTrue()
        ->and((float) $profile->amanat_balance)->toBe(30000.0);

    // View: same payload shape, liabilities include the section.
    $view = sectionsView($f);
    expect($view['rows']['amanat'])->toBe([['customer_id' => $depositor->id, 'customer_name' => 'Haji Saab', 'amount' => 30000.0]])
        ->and($view['totals']['liabilities'])->toBe(30000.0)
        ->and($view['totals']['assets'])->toBe(10000.0);

    // Re-entry replaces the row: one transaction, balance follows, ledger shows the new figure.
    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'cash' => ['amount' => 10000],
        'amanat' => [['customer_id' => $depositor->id, 'amount' => 32000]],
    ]);
    expect(AmanatTransaction::where('customer_id', $depositor->id)->count())->toBe(1)
        ->and((float) $profile->fresh()->amanat_balance)->toBe(32000.0)
        ->and(ledgerBalance($f['accounts']['amanat']))->toBe(-32000.0);

    // A drawn-down balance refuses re-entry with the same message and key.
    $profile->fresh()->update(['amanat_balance' => 100]);
    try {
        dispatchOpeningBalance($f, [
            'as_of_date' => '2026-08-31',
            'cash' => ['amount' => 10000],
            'amanat' => [['customer_id' => $depositor->id, 'amount' => 32000]],
        ]);
        $this->fail('Expected the drawn-down refusal');
    } catch (ValidationException $e) {
        expect($e->errors())->toBe(['amanat' => ['An opening amanat balance has already been drawn down; cannot re-enter opening balances.']]);
    }
});

test('a fuel company without an active 2200 account still gets the set-up message', function () {
    $f = openingBalanceHttpFixture();
    $f['company']->enableModule('fuel_station');
    $f['accounts']['amanat']->update(['is_active' => false]);
    $depositor = openingCustomer($f, 'Haji Saab');

    try {
        dispatchOpeningBalance($f, [
            'as_of_date' => '2026-08-31',
            'amanat' => [['customer_id' => $depositor->id, 'amount' => 5000]],
        ]);
        $this->fail('Expected the missing-account refusal');
    } catch (ValidationException $e) {
        expect($e->errors())->toBe(['amanat' => ['Set up account 2200 (Customer Amanat Deposits) first.']]);
    }
});

test('set_party with the amanat section works for a fuel company and is rejected for a general one', function () {
    $fuel = openingBalanceHttpFixture();
    $fuel['company']->enableModule('fuel_station');
    $holder = openingCustomer($fuel, 'Quick Holder');

    $this->actingAs($fuel['user']);
    app(CompanyContextService::class)->withContext($fuel['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.set_party', [
        'section' => 'amanat', 'party_id' => $holder->id, 'amount' => 7000, 'as_of_date' => '2026-08-01',
    ], $fuel['user'], true));
    expect(collect(sectionsView($fuel)['rows']['amanat'])->firstWhere('customer_id', $holder->id)['amount'])->toBe(7000.0);

    $general = openingBalanceHttpFixture();
    $other = openingCustomer($general, 'Other Holder');
    $this->actingAs($general['user']);
    expect(fn () => app(CompanyContextService::class)->withContext($general['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.set_party', [
        'section' => 'amanat', 'party_id' => $other->id, 'amount' => 7000, 'as_of_date' => '2026-08-01',
    ], $general['user'], true)))->toThrow(ValidationException::class);
});
