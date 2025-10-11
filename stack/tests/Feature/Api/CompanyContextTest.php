<?php

use App\Enums\CompanyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{actingAs, postJson, getJson};

uses(RefreshDatabase::class);

it('can switch to a company user belongs to', function () {
    $user = User::factory()->create();
    $company1 = createTestCompany($user);
    $company2 = createTestCompany($user);
    
    actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company2->id
        ])
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'company' => [
                    'id',
                    'name',
                    'slug',
                    'currency'
                ],
                'user_role',
                'fiscal_year' => [
                    'id',
                    'name',
                    'is_current'
                ],
                'switched_at'
            ]
        ])
        ->assertJsonFragment([
            'company' => [
                'id' => $company2->id,
                'name' => $company2->name,
                'slug' => $company2->slug,
                'currency' => $company2->currency
            ],
            'user_role' => CompanyRole::Owner->value
        ]);
});

it('requires authentication to switch company context', function () {
    $user = User::factory()->create();
    $company = createTestCompany($user);
    
    postJson('/api/company-context/switch', [
        'company_id' => $company->id
    ])
        ->assertUnauthorized();
});

it('validates company_id field when switching context', function () {
    $user = User::factory()->create();
    
    actingAs($user)
        ->postJson('/api/company-context/switch', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_id']);
    
    actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => 'invalid-uuid'
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_id']);
});

it('denies switching to company user does not belong to', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $company1 = createTestCompany($user1);
    $company2 = createTestCompany($user2);
    
    actingAs($user1)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company2->id
        ])
        ->assertForbidden()
        ->assertJsonFragment([
            'message' => 'You do not have access to this company.'
        ]);
});

it('denies switching to inactive companies for non-owners', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $company = createTestCompany($owner, ['is_active' => false]);
    addUserToCompany($company, $viewer, CompanyRole::Viewer);
    
    // Owner should be able to switch to inactive company
    actingAs($owner)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company->id
        ])
        ->assertOk();
    
    // Viewer should not be able to switch to inactive company
    actingAs($viewer)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company->id
        ])
        ->assertForbidden()
        ->assertJsonFragment([
            'message' => 'You cannot switch to an inactive company.'
        ]);
});

it('denies switching to company for inactive user membership', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $company = createTestCompany($owner);
    addUserToCompany($company, $member, CompanyRole::Viewer, false); // Inactive membership
    
    actingAs($member)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company->id
        ])
        ->assertForbidden()
        ->assertJsonFragment([
            'message' => 'Your access to this company is not active.'
        ]);
});

it('returns correct user role when switching context', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $accountant = User::factory()->create();
    $viewer = User::factory()->create();
    
    $company = createTestCompany($owner);
    addUserToCompany($company, $admin, CompanyRole::Admin);
    addUserToCompany($company, $accountant, CompanyRole::Accountant);
    addUserToCompany($company, $viewer, CompanyRole::Viewer);
    
    // Test each role
    actingAs($admin)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company->id
        ])
        ->assertOk()
        ->assertJsonFragment(['user_role' => CompanyRole::Admin->value]);
    
    actingAs($accountant)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company->id
        ])
        ->assertOk()
        ->assertJsonFragment(['user_role' => CompanyRole::Accountant->value]);
    
    actingAs($viewer)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company->id
        ])
        ->assertOk()
        ->assertJsonFragment(['user_role' => CompanyRole::Viewer->value]);
});

it('includes fiscal year information when switching context', function () {
    $user = User::factory()->create();
    $company = createTestCompany($user);
    
    actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company->id
        ])
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'fiscal_year' => [
                    'id',
                    'name',
                    'is_current'
                ]
            ]
        ])
        ->assertJsonPath('data.fiscal_year.is_current', true);
});

it('returns switched_at timestamp', function () {
    $user = User::factory()->create();
    $company = createTestCompany($user);
    
    $beforeSwitch = now();
    
    $response = actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company->id
        ])
        ->assertOk();
    
    $switchedAt = $response->json('data.switched_at');
    
    expect($switchedAt)->toBeString();
    expect($switchedAt)->toBeGreaterThanOrEqual($beforeSwitch->toIso8601String());
});

it('returns 404 for non-existent company when switching context', function () {
    $user = User::factory()->create();
    $fakeCompanyId = '550e8400-e29b-41d4-a716-446655440000';
    
    actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $fakeCompanyId
        ])
        ->assertNotFound()
        ->assertJsonFragment([
            'message' => 'Company not found.'
        ]);
});

it('can get current company context', function () {
    $user = User::factory()->create();
    $company = createTestCompany($user);
    
    actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'company' => [
                    'id',
                    'name',
                    'slug',
                    'currency'
                ],
                'user_role',
                'fiscal_year' => [
                    'id',
                    'name',
                    'is_current'
                ],
                'available_companies' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'role'
                    ]
                ]
            ]
        ]);
});

it('returns null company when no context is set', function () {
    $user = User::factory()->create();
    
    actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk()
        ->assertJsonFragment([
            'company' => null,
            'user_role' => null,
            'fiscal_year' => null
        ])
        ->assertJsonStructure([
            'data' => [
                'available_companies'
            ]
        ]);
});

it('returns available companies list in current context', function () {
    $user = User::factory()->create();
    $company1 = createTestCompany($user);
    $company2 = createTestCompany($user);
    
    actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'available_companies' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'role'
                    ]
                ]
            ]
        ])
        ->assertJsonCount(2, 'data.available_companies');
});

it('requires authentication to get current context', function () {
    getJson('/api/company-context/current')
        ->assertUnauthorized();
});

it('updates session context after switching companies', function () {
    $user = User::factory()->create();
    $company1 = createTestCompany($user);
    $company2 = createTestCompany($user);
    
    // Switch to company 1
    actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company1->id
        ])
        ->assertOk();
    
    // Verify context is set to company 1
    actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk()
        ->assertJsonFragment([
            'company' => ['id' => $company1->id]
        ]);
    
    // Switch to company 2
    actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company2->id
        ])
        ->assertOk();
    
    // Verify context is updated to company 2
    actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk()
        ->assertJsonFragment([
            'company' => ['id' => $company2->id]
        ]);
});

it('handles switching between companies with different currencies', function () {
    $user = User::factory()->create();
    $usCompany = createTestCompany($user, ['currency' => 'USD']);
    $eurCompany = createTestCompany($user, ['currency' => 'EUR']);
    
    // Switch to USD company
    $response1 = actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $usCompany->id
        ])
        ->assertOk()
        ->assertJsonFragment([
            'company' => ['currency' => 'USD']
        ]);
    
    // Switch to EUR company
    $response2 = actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $eurCompany->id
        ])
        ->assertOk()
        ->assertJsonFragment([
            'company' => ['currency' => 'EUR']
        ]);
});

it('maintains user session across company switches', function () {
    $user = User::factory()->create();
    $company1 = createTestCompany($user);
    $company2 = createTestCompany($user);
    
    actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company1->id
        ])
        ->assertOk();
    
    // User should still be authenticated after switch
    actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk();
    
    actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company2->id
        ])
        ->assertOk();
    
    // User should still be authenticated after second switch
    actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk();
});

// Helper functions
function createTestCompany(User $user, array $overrides = []): mixed
{
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
    
    return (object) $companyData;
}

function addUserToCompany(mixed $company, User $user, CompanyRole $role, bool $isActive = true): void
{
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'is_active' => $isActive,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}