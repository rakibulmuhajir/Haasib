<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Company;
use App\Models\User;
use App\Services\SettingsMenu;
use App\Services\UserPermissionPresenter;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Http\Request;

function shellCompany(array $attrs = []): Company
{
    $user = User::factory()->create();

    return Company::create(array_merge([
        'name' => 'Shell Co',
        'slug' => 'shell-co-'.str()->random(8),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ], $attrs));
}

function godUser(): User
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isGodMode')->andReturn(true);

    return $user;
}

test('a general company has no module home and no module landing page', function () {
    $company = (new Company)->forceFill(['industry_code' => 'other', 'slug' => 'gen']);
    $registry = app(ModuleRegistry::class);

    expect($registry->enabledFor($company))->toBe([]);
});

test('a fuel company lands on the station home, an umrah company on its dashboard', function () {
    $registry = app(ModuleRegistry::class);
    $fuel = (new Company)->forceFill(['industry_code' => 'fuel_station', 'slug' => 'pump']);
    $umrah = (new Company)->forceFill(['industry_code' => 'umrah', 'slug' => 'trip']);

    expect($registry->get('umrah')->homeUrl($umrah))->toContain('/trip');
    expect($registry->get('fuel_station')->homeUrl($fuel))->toContain('/pump');
    // Both on: umrah owns the home.
    $both = (new Company)->forceFill(['industry_code' => 'umrah', 'slug' => 'both', 'settings' => ['modules' => ['fuel_station' => true]]]);
    expect($registry->get('fuel_station')->homeUrl($both))->toBeNull();
});

test('fuelNavigation is shared as null for a company without the fuel module', function () {
    $request = Request::create('/');
    $auth = (new HandleInertiaRequests)->share($request)['auth'];

    expect($auth)->toHaveKey('fuelNavigation');
    expect(value($auth['fuelNavigation']))->toBeNull();
});

test('the settings menu of a general company has no station sections', function () {
    $company = (new Company)->forceFill(['industry_code' => 'other', 'slug' => 'gen']);
    $titles = array_column((new SettingsMenu)->sections($company, godUser()), 'title');

    expect($titles)->not->toContain('Station settings')->not->toContain('Station equipment')->not->toContain('Help');
});

test('the settings menu of a fuel company keeps the station sections in place', function () {
    $company = (new Company)->forceFill(['industry_code' => 'fuel_station', 'slug' => 'pump']);
    $titles = array_column((new SettingsMenu)->sections($company, godUser()), 'title');

    expect($titles)->toBe(['Company', 'Station settings', 'Station equipment', 'Accounting', 'Payroll', 'Help']);
});

test('creating a travel company enables umrah and switches fuel and inventory off', function () {
    $company = shellCompany(['settings' => ['modules' => ['fuel_station' => true, 'inventory' => true]]]);

    app(ModuleRegistry::class)->applyIndustry($company, 'travel');

    $company->refresh();
    expect($company->isModuleEnabled('umrah'))->toBeTrue()
        ->and($company->isModuleEnabled('fuel_station'))->toBeFalse()
        ->and($company->isModuleEnabled('inventory'))->toBeFalse();
});

test('picking a fuel station enables it and switches nothing else off; other changes nothing', function () {
    $company = shellCompany(['settings' => ['modules' => ['inventory' => true]]]);
    app(ModuleRegistry::class)->applyIndustry($company, 'other');
    app(ModuleRegistry::class)->applyIndustry($company, null);
    expect($company->fresh()->isModuleEnabled('fuel_station'))->toBeFalse();

    app(ModuleRegistry::class)->applyIndustry($company, 'fuel_station');
    $company->refresh();
    expect($company->isModuleEnabled('fuel_station'))->toBeTrue()
        ->and($company->isModuleEnabled('inventory'))->toBeTrue();
});

test('permission display: umrah shows only its own, general hides fuel and umrah', function () {
    $presenter = new UserPermissionPresenter;
    $all = ['umrah.payment.view', 'daily_close.view', 'pump.view', 'invoice.view', 'employee.view'];

    $umrah = (new Company)->forceFill(['industry_code' => 'umrah', 'settings' => ['modules' => ['payroll' => true]]]);
    expect($presenter->forCompany($umrah, 'owner', $all)['permissions'])->toBe(['umrah.payment.view', 'employee.view']);

    $general = (new Company)->forceFill(['industry_code' => 'other', 'settings' => ['modules' => ['payroll' => true]]]);
    expect($presenter->forCompany($general, 'owner', $all)['permissions'])->toBe(['invoice.view', 'employee.view']);

    $fuel = (new Company)->forceFill(['industry_code' => 'fuel_station', 'settings' => ['modules' => ['payroll' => true]]]);
    expect($presenter->forCompany($fuel, 'owner', $all)['permissions'])->toBe(['daily_close.view', 'pump.view', 'invoice.view', 'employee.view']);
});
