<?php

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContextService;

/**
 * God mode holds every permission in every company.
 *
 * The rest of the application already assumed this: HandleInertiaRequests hands these users
 * the 'super_admin' role, and CompanyController::show lets them past the membership check.
 * userHasPermission() disagreed - it waived membership and then asked Spatie for a role
 * assignment the user does not have in that company, so every permission came back false.
 *
 * The symptom was silent absence, not a refusal. A platform admin opening a company got the
 * page, with every gated section simply missing and nothing saying why. That is how the
 * financial position panel came to be invisible to the person who asked for it.
 */
function godModeUser(): User
{
    // God mode is identified by the id prefix, which is how the rest of the app tests for it.
    // Created with that id rather than renamed afterwards, the way CompanyHttpAuthorizationTest
    // does it; the suffix is hex because the column is a uuid.
    return User::factory()->withoutTwoFactor()->create([
        'id' => '00000000-0000-0000-0000-'.bin2hex(random_bytes(6)),
    ]);
}

function plainCompany(string $name): Company
{
    return Company::create([
        'name' => $name,
        'slug' => str()->slug($name).'-'.str()->lower(str()->random(6)),
        'base_currency' => 'PKR',
    ]);
}

test('god mode holds a permission in a company it is not a member of', function () {
    $god = godModeUser();
    $company = plainCompany('Someone Elses Co');

    $allowed = app(CompanyContextService::class)->withContext(
        $company,
        fn () => $god->hasCompanyPermission('financial_position.view')
    );

    expect($allowed)->toBeTrue();
});

test('god mode is not special-cased per permission', function (string $permission) {
    $god = godModeUser();
    $company = plainCompany('Any Co');

    expect(app(CompanyContextService::class)->withContext(
        $company,
        fn () => $god->hasCompanyPermission($permission)
    ))->toBeTrue();
})->with([
    'financial_position.view',
    'report.view',
    'opening_balance.manage',
]);

test('an ordinary user with no membership still holds nothing', function () {
    $stranger = User::factory()->withoutTwoFactor()->create();
    $company = plainCompany('Not Their Co');

    // The membership check is what god mode waives. It must still hold for everyone else.
    expect(app(CompanyContextService::class)->withContext(
        $company,
        fn () => $stranger->hasCompanyPermission('financial_position.view')
    ))->toBeFalse();
});

test('permission is still refused when there is no company in context', function () {
    $god = godModeUser();

    // Nothing to be an admin of. This guard runs before the god-mode check and must stay.
    expect($god->hasCompanyPermission('financial_position.view'))->toBeFalse();
});
