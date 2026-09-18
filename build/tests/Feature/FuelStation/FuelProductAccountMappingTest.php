<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Services\FuelProductAccountMapper;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

function fuelAccountMapperCompany(): array
{
    $user = User::factory()->create();
    $company = Company::create([
        'name' => 'Mapper Station', 'slug' => 'mapper-station-'.str()->random(8),
        'owner_id' => $user->id, 'base_currency' => 'PKR', 'industry_code' => 'fuel_station',
        'is_active' => true,
    ]);
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);

    return compact('user', 'company');
}

/**
 * acct.accounts_currency_allowed_chk only lets monetary subtypes (bank, cash, AR, AP, ...)
 * carry a currency: a non-null currency means "denominated in a foreign currency and needs
 * revaluation". Fuel revenue, COGS and inventory accounts are measured in the company's base
 * currency, so all three must store NULL. Stamping the base currency on the inventory account
 * used to violate the constraint and broke product setup for every new fuel company.
 */
test('fuel product accounts are created with no currency so the check constraint holds', function () {
    ['user' => $user, 'company' => $company] = fuelAccountMapperCompany();

    $accounts = app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(FuelProductAccountMapper::class)->resolveAccounts($company->id, 'diesel', $user->id)
    );

    expect($accounts)->toHaveKeys(['income', 'expense', 'asset']);

    foreach (['income', 'expense', 'asset'] as $role) {
        expect($accounts[$role]->currency)->toBeNull("the {$role} account must not be currency-denominated");
    }

    expect($accounts['asset']->subtype)->toBe('inventory')
        ->and($accounts['expense']->subtype)->toBe('cogs')
        ->and($accounts['income']->subtype)->toBe('revenue');
});

test('every fuel category maps without violating the currency constraint', function (string $category) {
    ['user' => $user, 'company' => $company] = fuelAccountMapperCompany();

    $accounts = app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(FuelProductAccountMapper::class)->resolveAccounts($company->id, $category, $user->id)
    );

    expect(Account::whereIn('id', collect($accounts)->pluck('id'))->whereNotNull('currency')->count())->toBe(0);
})->with(['petrol', 'hi_octane', 'diesel', 'lubricant_open', 'lubricant_packaged']);

test('resolving the same category twice reuses the accounts instead of duplicating them', function () {
    ['user' => $user, 'company' => $company] = fuelAccountMapperCompany();

    $mapper = app(FuelProductAccountMapper::class);
    $first = app(CompanyContextService::class)->withContext($company, fn () => $mapper->resolveAccounts($company->id, 'petrol', $user->id));
    $second = app(CompanyContextService::class)->withContext($company, fn () => $mapper->resolveAccounts($company->id, 'petrol', $user->id));

    expect($second['income']->id)->toBe($first['income']->id)
        ->and($second['expense']->id)->toBe($first['expense']->id)
        ->and($second['asset']->id)->toBe($first['asset']->id);
});
