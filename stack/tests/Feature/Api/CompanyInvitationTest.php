<?php

use App\Enums\CompanyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{actingAs, postJson};

uses(RefreshDatabase::class);

it('can create a company invitation with valid data', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'newuser@example.com',
            'role' => CompanyRole::Accountant->value,
            'expires_in_days' => 7
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'email',
                'role',
                'status',
                'expires_at',
                'created_at'
            ]
        ])
        ->assertJsonFragment([
            'email' => 'newuser@example.com',
            'role' => CompanyRole::Accountant->value,
            'status' => 'pending'
        ]);
});

it('requires authentication to create company invitation', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    postJson("/api/v1/companies/{$company->id}/invitations", [
        'email' => 'newuser@example.com',
        'role' => CompanyRole::Admin->value
    ])
        ->assertUnauthorized();
});

it('validates required fields when creating invitation', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'role']);
});

it('validates email field when creating invitation', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    // Test invalid email format
    actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'invalid-email',
            'role' => CompanyRole::Viewer->value
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
    
    // Test empty email
    actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => '',
            'role' => CompanyRole::Viewer->value
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('validates role field when creating invitation', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    // Test invalid role
    actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'user@example.com',
            'role' => 'invalid_role'
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role']);
});

it('validates expires_in_days field when creating invitation', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    // Test invalid expires_in_days (negative)
    actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'user@example.com',
            'role' => CompanyRole::Viewer->value,
            'expires_in_days' => -1
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['expires_in_days']);
    
    // Test invalid expires_in_days (too high)
    actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'user@example.com',
            'role' => CompanyRole::Viewer->value,
            'expires_in_days' => 365
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['expires_in_days']);
});

it('allows company owner to create invitations', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'invitee@example.com',
            'role' => CompanyRole::Admin->value
        ])
        ->assertCreated();
});

it('allows company admin to create invitations', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $company = createTestCompany($owner);
    addUserToCompany($company, $admin, CompanyRole::Admin);
    
    actingAs($admin)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'invitee@example.com',
            'role' => CompanyRole::Accountant->value
        ])
        ->assertCreated();
});

it('denies invitation creation for accountant role', function () {
    $owner = User::factory()->create();
    $accountant = User::factory()->create();
    $company = createTestCompany($owner);
    addUserToCompany($company, $accountant, CompanyRole::Accountant);
    
    actingAs($accountant)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'invitee@example.com',
            'role' => CompanyRole::Viewer->value
        ])
        ->assertForbidden()
        ->assertJsonFragment([
            'message' => 'You do not have permission to invite users to this company.'
        ]);
});

it('denies invitation creation for viewer role', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $company = createTestCompany($owner);
    addUserToCompany($company, $viewer, CompanyRole::Viewer);
    
    actingAs($viewer)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'invitee@example.com',
            'role' => CompanyRole::Viewer->value
        ])
        ->assertForbidden()
        ->assertJsonFragment([
            'message' => 'You do not have permission to invite users to this company.'
        ]);
});

it('denies invitation creation for non-members', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $company = createTestCompany($owner);
    
    actingAs($outsider)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'invitee@example.com',
            'role' => CompanyRole::Viewer->value
        ])
        ->assertForbidden()
        ->assertJsonFragment([
            'message' => 'You do not have permission to invite users to this company.'
        ]);
});

it('prevents duplicate invitations for same email', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    $email = 'duplicate@example.com';
    
    // Create first invitation
    actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => $email,
            'role' => CompanyRole::Accountant->value
        ])
        ->assertCreated();
    
    // Attempt to create duplicate invitation
    actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => $email,
            'role' => CompanyRole::Viewer->value
        ])
        ->assertUnprocessable()
        ->assertJsonFragment([
            'message' => 'An invitation for this email already exists.'
        ]);
});

it('prevents invitations for existing company members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $company = createTestCompany($owner);
    addUserToCompany($company, $member, CompanyRole::Accountant);
    
    actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => $member->email,
            'role' => CompanyRole::Viewer->value
        ])
        ->assertUnprocessable()
        ->assertJsonFragment([
            'message' => 'User is already a member of this company.'
        ]);
});

it('sets correct expiry date based on expires_in_days', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    $response = actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'expiry@example.com',
            'role' => CompanyRole::Viewer->value,
            'expires_in_days' => 14
        ])
        ->assertCreated();
    
    $expiresAt = $response->json('data.expires_at');
    $expectedExpiry = now()->addDays(14)->toIso8601String();
    
    expect($expiresAt)->toBe($expectedExpiry);
});

it('uses default expiry when expires_in_days not provided', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    $response = actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'default@example.com',
            'role' => CompanyRole::Viewer->value
        ])
        ->assertCreated();
    
    $expiresAt = $response->json('data.expires_at');
    $expectedExpiry = now()->addDays(7)->toIso8601String(); // Default 7 days
    
    expect($expiresAt)->toBe($expectedExpiry);
});

it('limits role assignments based on inviter role', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $company = createTestCompany($owner);
    addUserToCompany($company, $admin, CompanyRole::Admin);
    
    // Admin should not be able to invite owners
    actingAs($admin)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'owner@example.com',
            'role' => CompanyRole::Owner->value
        ])
        ->assertUnprocessable()
        ->assertJsonFragment([
            'message' => 'You do not have permission to assign this role.'
        ]);
    
    // Admin should be able to invite accountants and viewers
    actingAs($admin)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'accountant@example.com',
            'role' => CompanyRole::Accountant->value
        ])
        ->assertCreated();
    
    actingAs($admin)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'viewer@example.com',
            'role' => CompanyRole::Viewer->value
        ])
        ->assertCreated();
});

it('returns 404 for non-existent company', function () {
    $owner = User::factory()->create();
    $fakeCompanyId = '550e8400-e29b-41d4-a716-446655440000';
    
    actingAs($owner)
        ->postJson("/api/v1/companies/{$fakeCompanyId}/invitations", [
            'email' => 'test@example.com',
            'role' => CompanyRole::Viewer->value
        ])
        ->assertNotFound()
        ->assertJsonFragment([
            'message' => 'Company not found.'
        ]);
});

it('handles invitation creation for inactive companies', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner, ['is_active' => false]);
    
    // Owner should still be able to create invitations for inactive companies
    actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'inactive@example.com',
            'role' => CompanyRole::Viewer->value
        ])
        ->assertCreated();
});

it('generates unique invitation tokens', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    $response1 = actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'user1@example.com',
            'role' => CompanyRole::Viewer->value
        ])
        ->assertCreated();
    
    $response2 = actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'user2@example.com',
            'role' => CompanyRole::Viewer->value
        ])
        ->assertCreated();
    
    $token1 = $response1->json('data.token');
    $token2 = $response2->json('data.token');
    
    expect($token1)->not->toBe($token2);
    expect($token1)->toBeString();
    expect($token2)->toBeString();
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