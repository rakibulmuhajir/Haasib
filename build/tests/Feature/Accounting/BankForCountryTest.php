<?php

use App\Modules\Accounting\Models\Bank;

/**
 * The bank list offered on a company's bank account form is the banks of its own country.
 *
 * The list is shared across every country and held only Saudi banks, so a Pakistani station was
 * offered Riyad Bank and nothing it banks with. Pakistan's banks are now seeded by migration, and
 * the form filters by the company's country - keeping the bank an existing account already uses.
 */
test('Pakistan\'s banks are in the list', function () {
    $names = Bank::where('country_code', 'PK')->pluck('name');

    expect($names)->toContain('Habib Bank Limited (HBL)')
        ->and($names)->toContain('Meezan Bank Limited')
        ->and($names)->toContain('United Bank Limited (UBL)');
});

test('a Pakistani company is offered Pakistani banks only', function () {
    $countries = Bank::active()->forCountry('PK')->pluck('country_code')->unique()->values()->all();

    expect($countries)->toBe(['PK']);
});

test('a company with no country still sees every bank', function () {
    expect(Bank::active()->forCountry(null)->count())->toBe(Bank::active()->count());
});

test('editing an account keeps the bank it already uses, even from another country', function () {
    $saudi = Bank::where('country_code', 'SA')->value('id');

    expect(Bank::active()->forCountry('PK', $saudi)->pluck('id')->all())->toContain($saudi);
});
