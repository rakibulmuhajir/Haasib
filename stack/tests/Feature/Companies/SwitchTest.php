<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Services\ContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->contextService = app(ContextService::class);
});

function actingCompanyOwnerWithCompanies(int $companyCount = 2): array
{
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
        'system_role' => 'company_owner',
    ]);

    $companies = Company::factory()
        ->count($companyCount)
        ->sequence(
            ['name' => 'First Company', 'industry' => 'hospitality', 'base_currency' => 'USD'],
            ['name' => 'Second Company', 'industry' => 'retail', 'base_currency' => 'EUR']
        )
        ->create();

    $companies->each(function (Company $company) use ($user) {
        CompanyUser::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role' => 'owner',
        ]);
    });

    auth()->login($user);
    app(ContextService::class)->setCurrentCompany($user, $companies->first());

    return [$user, $companies];
}

it('switches active company successfully', function () {
    [$user, $companies] = actingCompanyOwnerWithCompanies();

    $targetCompany = $companies->last();

    $response = \Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $targetCompany->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('company.id', $targetCompany->id)
        ->assertJsonPath('company.name', $targetCompany->name);
});

it('fails to switch to company user does not have access to', function () {
    [$user, $companies] = actingCompanyOwnerWithCompanies(1);

    $otherCompany = Company::factory()->create([
        'name' => 'Inaccessible Company',
        'industry' => 'retail',
        'base_currency' => 'EUR',
    ]);

    $response = \Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $otherCompany->id,
    ]);

    $response->assertStatus(403)
        ->assertJson(['message' => 'Access denied to this company']);
});

it('fails to switch with invalid company ID format', function () {
    [$user] = actingCompanyOwnerWithCompanies();

    $response = \Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => 'invalid-uuid-format',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['company_id']);
});

it('fails to switch to non-existent company', function () {
    [$user] = actingCompanyOwnerWithCompanies();

    $response = \Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => (string) Str::uuid(),
    ]);

    $response->assertNotFound()
        ->assertJson(['message' => 'Company not found']);
});

it('fails to switch with missing company_id', function () {
    [$user] = actingCompanyOwnerWithCompanies();

    $response = \Pest\Laravel\postJson('/api/v1/companies/switch', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['company_id']);
});

it('cannot switch to inactive company', function () {
    [$user, $companies] = actingCompanyOwnerWithCompanies(1);

    $inactiveCompany = Company::factory()->create([
        'name' => 'Inactive Company',
        'industry' => 'hospitality',
        'base_currency' => 'USD',
        'is_active' => false,
    ]);

    $response = \Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $inactiveCompany->id,
    ]);

    $response->assertStatus(400)
        ->assertJson(['message' => 'Company is inactive']);
});
