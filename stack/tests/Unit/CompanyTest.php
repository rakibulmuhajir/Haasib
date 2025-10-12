<?php

use App\Models\Company;
use App\Models\User;

// Company model validation tests
it('validates required fields for company creation', function () {
    $company = new Company;

    expect($company->validate(['name' => 'required']))->toBeTrue();
    expect($company->validate(['slug' => 'required']))->toBeTrue();
    expect($company->validate(['base_currency' => 'required']))->toBeTrue();
});

it('generates unique slugs from company names', function () {
    $company = new Company(['name' => 'Test Company']);

    $company->save();

    expect($company->slug)->toBe('test-company');
});

it('enforces unique company names per active company', function () {
    Company::factory()->create(['name' => 'Duplicate Test', 'is_active' => true]);

    $duplicate = Company::factory()->make(['name' => 'Duplicate Test', 'is_active' => true]);

    expect(fn () => $duplicate->save())->toThrow('Illuminate\Database\QueryException');
});

it('allows duplicate names for inactive companies', function () {
    Company::factory()->create(['name' => 'Inactive Test', 'is_active' => false]);

    $newInactive = Company::factory()->make(['name' => 'Inactive Test', 'is_active' => false]);

    expect(fn () => $newInactive->save())->not->toThrow('Illuminate\Database\QueryException');
});

it('casts settings attribute as array', function () {
    $company = Company::factory()->create([
        'settings' => ['feature_enabled' => true, 'max_users' => 10],
    ]);

    expect($company->settings)->toBeArray();
    expect($company->settings['feature_enabled'])->toBeTrue();
    expect($company->settings['max_users'])->toBe(10);
});

it('validates currency codes', function () {
    $company = new Company;

    // Valid currency codes
    expect($company->validate(['base_currency' => 'USD']))->toBeTrue();
    expect($company->validate(['base_currency' => 'EUR']))->toBeTrue();
    expect($company->validate(['base_currency' => 'SAR']))->toBeTrue();

    // Invalid currency codes
    expect($company->validate(['base_currency' => 'INVALID']))->toBeFalse();
    expect($company->validate(['base_currency' => '123']))->toBeFalse();
});

it('validates language codes', function () {
    $company = new Company;

    // Valid language codes
    expect($company->validate(['language' => 'en']))->toBeTrue();
    expect($company->validate(['language' => 'ar']))->toBeTrue();
    expect($company->validate(['language' => 'fr']))->toBeTrue();

    // Invalid language codes
    expect($company->validate(['language' => 'invalid']))->toBeFalse();
});

it('validates locale codes', function () {
    $company = new Company;

    // Valid locales
    expect($company->validate(['locale' => 'en_US']))->toBeTrue();
    expect($company->validate(['locale' => 'ar_SA']))->toBeTrue();
    expect($company->validate(['locale' => 'fr_FR']))->toBeTrue();

    // Invalid locales
    expect($company->validate(['locale' => 'invalid']))->toBeFalse();
});

it('scopes active companies correctly', function () {
    // Create active and inactive companies
    Company::factory()->count(3)->create(['is_active' => true]);
    Company::factory()->count(2)->create(['is_active' => false]);

    $activeCompanies = Company::active()->get();

    expect($activeCompanies)->toHaveCount(3);
    $activeCompanies->each(fn ($company) => expect($company->is_active)->toBeTrue());
});

it('scopes companies by user relationship', function () {
    $user = User::factory()->create();

    // User belongs to 2 companies
    $userCompanies = Company::factory()->count(2)->create();
    $user->companies()->attach($userCompanies, ['role' => 'owner']);

    // Create companies user doesn't belong to
    Company::factory()->count(3)->create();

    $accessibleCompanies = Company::forUser($user)->get();

    expect($accessibleCompanies)->toHaveCount(2);
    $accessibleCompanies->each(fn ($company) => expect($userCompanies->pluck('id')->contains($company->id))->toBeTrue()
    );
});

it('properly handles user relationships', function () {
    $company = Company::factory()->create();
    $users = User::factory()->count(3)->create();

    // Attach users with different roles
    $company->users()->attach($users[0], ['role' => 'owner']);
    $company->users()->attach($users[1], ['role' => 'admin']);
    $company->users()->attach($users[2], ['role' => 'viewer']);

    expect($company->users)->toHaveCount(3);

    // Test role-based relationships
    expect($company->owners())->toHaveCount(1);
    expect($company->admins())->toHaveCount(1);
    expect($company->viewers())->toHaveCount(1);
});

it('validates creator relationship', function () {
    $creator = User::factory()->create();
    $company = Company::factory()->create(['created_by_user_id' => $creator->id]);

    expect($company->creator->id)->toBe($creator->id);
});

it('handles soft deletes correctly', function () {
    $company = Company::factory()->create();

    $company->delete();

    expect($company->trashed())->toBeTrue();
    expect(Company::find($company->id))->toBeNull();
    expect(Company::withTrashed()->find($company->id))->not->toBeNull();
});

it('validates business logic constraints', function () {
    $company = Company::factory()->make();

    // Test that a company must have a valid creator
    expect(fn () => $company->save())->not->toThrow();

    // Test invalid country codes
    $invalidCompany = Company::factory()->make(['country' => 'XX']);
    expect($invalidCompany->validate(['country' => 'size:2,exists:countries']))->toBeFalse();
});

it('generates proper URLs and routes', function () {
    $company = Company::factory()->create(['slug' => 'test-company']);

    expect($company->url())->toBe('/companies/test-company');
    expect($company->editUrl())->toBe('/companies/test-company/edit');
});

it('handles settings defaults correctly', function () {
    $company = Company::factory()->create(['settings' => null]);

    expect($company->settings)->toBeArray();
    expect($company->settings)->toBeEmpty();
});

it('validates timezone settings', function () {
    $company = new Company;

    // Valid timezones
    expect($company->validate(['timezone' => 'UTC']))->toBeTrue();
    expect($company->validate(['timezone' => 'Asia/Riyadh']))->toBeTrue();
    expect($company->validate(['timezone' => 'America/New_York']))->toBeTrue();

    // Invalid timezones
    expect($company->validate(['timezone' => 'Invalid/Timezone']))->toBeFalse();
});
