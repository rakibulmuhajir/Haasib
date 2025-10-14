<?php

use App\Enums\CompanyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{actingAs, getJson};

uses(RefreshDatabase::class);

it('can retrieve company details when authenticated', function () {
    $user = User::factory()->create();
    $company = createTestCompany($user);
    
    actingAs($user)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'slug',
                'currency',
                'timezone',
                'country',
                'language',
                'locale',
                'is_active',
                'created_at',
                'updated_at',
                'fiscal_year' => [
                    'id',
                    'name',
                    'start_date',
                    'end_date',
                    'is_current'
                ],
                'user_role'
            ]
        ])
        ->assertJsonFragment([
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'currency' => $company->currency,
            'timezone' => $company->timezone,
            'country' => $company->country,
            'language' => $company->language,
            'locale' => $company->locale,
            'is_active' => $company->is_active,
            'user_role' => CompanyRole::Owner->value
        ]);
});

it('requires authentication to view company details', function () {
    $user = User::factory()->create();
    $company = createTestCompany($user);
    
    getJson("/api/v1/companies/{$company->id}")
        ->assertUnauthorized();
});

it('returns 404 for non-existent company', function () {
    $user = User::factory()->create();
    $fakeId = '550e8400-e29b-41d4-a716-446655440000';
    
    actingAs($user)
        ->getJson("/api/v1/companies/{$fakeId}")
        ->assertNotFound()
        ->assertJsonFragment([
            'message' => 'Company not found.'
        ]);
});

it('allows access to companies user belongs to', function () {
    $user = User::factory()->create();
    $company = createTestCompany($user);
    
    actingAs($user)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertOk();
});

it('denies access to companies user does not belong to', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $company = createTestCompany($user1);
    
    actingAs($user2)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertForbidden()
        ->assertJsonFragment([
            'message' => 'You do not have permission to view this company.'
        ]);
});

it('includes fiscal year information when available', function () {
    $user = User::factory()->create();
    $company = createTestCompany($user);
    
    actingAs($user)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'fiscal_year' => [
                    'id',
                    'name',
                    'start_date',
                    'end_date',
                    'is_current'
                ]
            ]
        ]);
});

it('shows correct user role for company members', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $accountant = User::factory()->create();
    $viewer = User::factory()->create();
    
    $company = createTestCompany($owner);
    
    // Add users with different roles
    addUserToCompany($company, $admin, CompanyRole::Admin);
    addUserToCompany($company, $accountant, CompanyRole::Accountant);
    addUserToCompany($company, $viewer, CompanyRole::Viewer);
    
    // Test owner role
    actingAs($owner)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertOk()
        ->assertJsonFragment(['user_role' => CompanyRole::Owner->value]);
    
    // Test admin role
    actingAs($admin)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertOk()
        ->assertJsonFragment(['user_role' => CompanyRole::Admin->value]);
    
    // Test accountant role
    actingAs($accountant)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertOk()
        ->assertJsonFragment(['user_role' => CompanyRole::Accountant->value]);
    
    // Test viewer role
    actingAs($viewer)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertOk()
        ->assertJsonFragment(['user_role' => CompanyRole::Viewer->value]);
});

it('returns company with active fiscal year', function () {
    $user = User::factory()->create();
    $company = createTestCompany($user);
    
    actingAs($user)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertOk()
        ->assertJsonPath('data.fiscal_year.is_current', true);
});

it('handles companies with null country field', function () {
    $user = User::factory()->create();
    $company = createTestCompany($user, ['country' => null]);
    
    actingAs($user)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertOk()
        ->assertJsonFragment(['country' => null]);
});

it('includes timestamp fields in correct format', function () {
    $user = User::factory()->create();
    $company = createTestCompany($user);
    
    actingAs($user)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'created_at',
                'updated_at'
            ]
        ]);
    
    $response = actingAs($user)
        ->getJson("/api/v1/companies/{$company->id}")
        ->json('data');
    
    // Verify timestamps are in ISO 8601 format
    expect($response['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
    expect($response['updated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
});

it('returns correct company data types', function () {
    $user = User::factory()->create();
    $company = createTestCompany($user);
    
    $response = actingAs($user)
        ->getJson("/api/v1/companies/{$company->id}")
        ->json('data');
    
    // Verify field types
    expect($response['id'])->toBeString();
    expect($response['name'])->toBeString();
    expect($response['slug'])->toBeString();
    expect($response['currency'])->toBeString();
    expect($response['timezone'])->toBeString();
    expect($response['country'])->toBeString(); // Can be null
    expect($response['language'])->toBeString();
    expect($response['locale'])->toBeString();
    expect($response['is_active'])->toBeBool();
    expect($response['user_role'])->toBeString();
    expect($response['fiscal_year']['id'])->toBeString();
    expect($response['fiscal_year']['name'])->toBeString();
    expect($response['fiscal_year']['start_date'])->toBeString();
    expect($response['fiscal_year']['end_date'])->toBeString();
    expect($response['fiscal_year']['is_current'])->toBeBool();
});

it('denies access to inactive companies for non-owners', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $company = createTestCompany($owner, ['is_active' => false]);
    addUserToCompany($company, $viewer, CompanyRole::Viewer);
    
    // Owner should still have access
    actingAs($owner)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertOk();
    
    // Viewer should be denied access to inactive company
    actingAs($viewer)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertForbidden();
});

// Helper functions
function createTestCompany(User $user, array $overrides = []): mixed
{
    // This would be replaced with actual company creation logic once implemented
    // For now, we'll create a mock company structure that matches expected format
    
    $companyData = array_merge([
        'id' => fake()->uuid(),
        'name' => fake()->company(),
        'slug' => fake()->slug(),
        'currency' => 'USD',
        'timezone' => 'America/New_York',
        'country' => 'US',
        'language' => 'en',
        'locale' => 'en_US',
        'is_active' => true,
        'created_by_user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
    
    // Create company record first
    DB::table('auth.companies')->insert((array) $companyData);
    
    // Create user-company relationship
    DB::table('auth.company_user')->insert([
        'company_id' => $companyData['id'],
        'user_id' => $user->id,
        'role' => CompanyRole::Owner->value,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Create fiscal year for the company (like the real controller does)
    DB::table('acct.fiscal_years')->insert([
        'id' => Str::uuid(),
        'company_id' => $companyData['id'],
        'name' => now()->year . ' Fiscal Year',
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->endOfYear()->toDateString(),
        'is_active' => true,
        'is_locked' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    return (object) $companyData;
}

function addUserToCompany(mixed $company, User $user, CompanyRole $role): void
{
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}