<?php

use App\Enums\CompanyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use function Pest\Laravel\{actingAs, postJson, getJson};

uses(RefreshDatabase::class);

it('completes full invitation flow successfully', function () {
    // Setup: Create company with owner
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $company = createTestCompany($owner);
    
    // Step 1: Owner creates invitation
    $invitationResponse = actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'invitee@example.com',
            'role' => CompanyRole::Accountant->value,
            'expires_in_days' => 7
        ])
        ->assertCreated();
    
    $invitationId = $invitationResponse->json('data.id');
    $token = $invitationResponse->json('data.token');
    
    // Verify invitation was created correctly
    expect($invitationResponse->json('data.email'))->toBe('invitee@example.com');
    expect($invitationResponse->json('data.role'))->toBe(CompanyRole::Accountant->value);
    expect($invitationResponse->json('data.status'))->toBe('pending');
    
    // Verify database state
    $this->assertDatabaseHas('auth.company_invitations', [
        'id' => $invitationId,
        'company_id' => $company->id,
        'email' => 'invitee@example.com',
        'role' => CompanyRole::Accountant->value,
        'invited_by_user_id' => $owner->id,
        'status' => 'pending'
    ]);
    
    // Step 2: Invitee accepts invitation
    $acceptResponse = actingAs($invitee)
        ->postJson("/api/company-invitations/{$token}/accept")
        ->assertOk();
    
    // Verify acceptance response
    expect($acceptResponse->json('data.company.id'))->toBe($company->id);
    expect($acceptResponse->json('data.company.name'))->toBe($company->name);
    expect($acceptResponse->json('data.user.id'))->toBe($invitee->id);
    expect($acceptResponse->json('data.user.email'))->toBe('invitee@example.com');
    expect($acceptResponse->json('data.role'))->toBe(CompanyRole::Accountant->value);
    expect($acceptResponse->json('data.joined_at'))->not->toBeNull();
    
    // Step 3: Verify user is now a company member
    $this->assertDatabaseHas('auth.company_user', [
        'company_id' => $company->id,
        'user_id' => $invitee->id,
        'role' => CompanyRole::Accountant->value,
        'is_active' => true
    ]);
    
    // Step 4: Verify invitation status is updated
    $this->assertDatabaseHas('auth.company_invitations', [
        'id' => $invitationId,
        'status' => 'accepted',
        'accepted_by_user_id' => $invitee->id,
        'accepted_at' => fn($value) => $value !== null
    ]);
    
    // Step 5: Verify user can access the company
    actingAs($invitee)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertOk()
        ->assertJsonFragment(['user_role' => CompanyRole::Accountant->value]);
    
    // Step 6: Verify user can switch to company context
    actingAs($invitee)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company->id
        ])
        ->assertOk()
        ->assertJsonFragment(['user_role' => CompanyRole::Accountant->value]);
});

it('handles invitation rejection flow', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $company = createTestCompany($owner);
    
    // Create invitation
    $invitationResponse = actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => $invitee->email,
            'role' => CompanyRole::Viewer->value
        ])
        ->assertCreated();
    
    $token = $invitationResponse->json('data.token');
    $invitationId = $invitationResponse->json('data.id');
    
    // Invitee rejects invitation
    actingAs($invitee)
        ->postJson("/api/company-invitations/{$token}/reject")
        ->assertOk();
    
    // Verify invitation is marked as rejected
    $this->assertDatabaseHas('auth.company_invitations', [
        'id' => $invitationId,
        'status' => 'rejected',
        'accepted_by_user_id' => $invitee->id,
        'accepted_at' => fn($value) => $value !== null
    ]);
    
    // Verify user is NOT added to company
    $this->assertDatabaseMissing('auth.company_user', [
        'company_id' => $company->id,
        'user_id' => $invitee->id
    ]);
    
    // Verify user cannot access the company
    actingAs($invitee)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertForbidden();
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
    
    // Verify only one invitation exists
    $this->assertDatabaseCount('auth.company_invitations', 1);
});

it('prevents invitations for existing company members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $company = createTestCompany($owner);
    
    // Add member directly to company
    addUserToCompany($company, $member, CompanyRole::Accountant);
    
    // Attempt to invite existing member
    actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => $member->email,
            'role' => CompanyRole::Viewer->value
        ])
        ->assertUnprocessable()
        ->assertJsonFragment([
            'message' => 'User is already a member of this company.'
        ]);
    
    // Verify no invitation was created
    $this->assertDatabaseMissing('auth.company_invitations', [
        'company_id' => $company->id,
        'email' => $member->email
    ]);
});

it('handles expired invitations', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $company = createTestCompany($owner);
    
    // Create invitation with 1 day expiry
    $invitationResponse = actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => $invitee->email,
            'role' => CompanyRole::Viewer->value,
            'expires_in_days' => 1
        ])
        ->assertCreated();
    
    $token = $invitationResponse->json('data.token');
    $invitationId = $invitationResponse->json('data.id');
    
    // Manually expire the invitation for testing
    DB::table('auth.company_invitations')
        ->where('id', $invitationId)
        ->update([
            'expires_at' => now()->subDay(),
            'status' => 'expired'
        ]);
    
    // Attempt to accept expired invitation
    actingAs($invitee)
        ->postJson("/api/company-invitations/{$token}/accept")
        ->assertForbidden()
        ->assertJsonFragment([
            'message' => 'This invitation has expired.'
        ]);
    
    // Verify user is not added to company
    $this->assertDatabaseMissing('auth.company_user', [
        'company_id' => $company->id,
        'user_id' => $invitee->id
    ]);
});

it('validates invitation permissions based on user role', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $accountant = User::factory()->create();
    $viewer = User::factory()->create();
    
    $company = createTestCompany($owner);
    addUserToCompany($company, $admin, CompanyRole::Admin);
    addUserToCompany($company, $accountant, CompanyRole::Accountant);
    addUserToCompany($company, $viewer, CompanyRole::Viewer);
    
    $inviteeEmail = 'permission.test@example.com';
    
    // Owner can invite any role
    actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => $inviteeEmail,
            'role' => CompanyRole::Owner->value
        ])
        ->assertCreated();
    
    // Admin can invite accountant and viewer
    actingAs($admin)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'admin-test@example.com',
            'role' => CompanyRole::Accountant->value
        ])
        ->assertCreated();
    
    actingAs($admin)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'admin-test2@example.com',
            'role' => CompanyRole::Viewer->value
        ])
        ->assertCreated();
    
    // Admin cannot invite owner
    actingAs($admin)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'admin-owner@example.com',
            'role' => CompanyRole::Owner->value
        ])
        ->assertUnprocessable()
        ->assertJsonFragment([
            'message' => 'You do not have permission to assign this role.'
        ]);
    
    // Accountant cannot invite anyone
    actingAs($accountant)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'accountant-test@example.com',
            'role' => CompanyRole::Viewer->value
        ])
        ->assertForbidden()
        ->assertJsonFragment([
            'message' => 'You do not have permission to invite users to this company.'
        ]);
    
    // Viewer cannot invite anyone
    actingAs($viewer)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => 'viewer-test@example.com',
            'role' => CompanyRole::Viewer->value
        ])
        ->assertForbidden()
        ->assertJsonFragment([
            'message' => 'You do not have permission to invite users to this company.'
        ]);
});

it('handles multiple invitations for different roles', function () {
    $owner = User::factory()->create();
    $accountant1 = User::factory()->create(['email' => 'accountant1@example.com']);
    $accountant2 = User::factory()->create(['email' => 'accountant2@example.com']);
    $viewer1 = User::factory()->create(['email' => 'viewer1@example.com']);
    $viewer2 = User::factory()->create(['email' => 'viewer2@example.com']);
    
    $company = createTestCompany($owner);
    
    // Create multiple invitations
    $invitations = [];
    
    $invitations[] = actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => $accountant1->email,
            'role' => CompanyRole::Accountant->value
        ])
        ->json('data.id');
    
    $invitations[] = actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => $accountant2->email,
            'role' => CompanyRole::Accountant->value
        ])
        ->json('data.id');
    
    $invitations[] = actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => $viewer1->email,
            'role' => CompanyRole::Viewer->value
        ])
        ->json('data.id');
    
    $invitations[] = actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => $viewer2->email,
            'role' => CompanyRole::Viewer->value
        ])
        ->json('data.id');
    
    // Verify all invitations were created
    expect($invitations)->toHaveCount(4);
    $this->assertDatabaseCount('auth.company_invitations', 4);
    
    // Accept all invitations
    foreach ([$accountant1, $accountant2, $viewer1, $viewer2] as $user) {
        $invitation = DB::table('auth.company_invitations')
            ->where('email', $user->email)
            ->where('company_id', $company->id)
            ->first();
        
        actingAs($user)
            ->postJson("/api/company-invitations/{$invitation->token}/accept")
            ->assertOk();
    }
    
    // Verify all users are now company members
    $this->assertDatabaseHas('auth.company_user', [
        'company_id' => $company->id,
        'user_id' => $accountant1->id,
        'role' => CompanyRole::Accountant->value
    ]);
    
    $this->assertDatabaseHas('auth.company_user', [
        'company_id' => $company->id,
        'user_id' => $accountant2->id,
        'role' => CompanyRole::Accountant->value
    ]);
    
    $this->assertDatabaseHas('auth.company_user', [
        'company_id' => $company->id,
        'user_id' => $viewer1->id,
        'role' => CompanyRole::Viewer->value
    ]);
    
    $this->assertDatabaseHas('auth.company_user', [
        'company_id' => $company->id,
        'user_id' => $viewer2->id,
        'role' => CompanyRole::Viewer->value
    ]);
    
    // Verify all users can access the company with correct roles
    actingAs($accountant1)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertOk()
        ->assertJsonFragment(['user_role' => CompanyRole::Accountant->value]);
    
    actingAs($viewer1)
        ->getJson("/api/v1/companies/{$company->id}")
        ->assertOk()
        ->assertJsonFragment(['user_role' => CompanyRole::Viewer->value]);
    
    // Verify context shows all available companies
    foreach ([$accountant1, $accountant2, $viewer1, $viewer2] as $user) {
        $contextResponse = actingAs($user)
            ->getJson('/api/company-context/current')
            ->assertOk();
        
        expect($contextResponse->json('data.available_companies'))->toHaveCount(1);
        expect($contextResponse->json('data.available_companies.0.id'))->toBe($company->id);
    }
});

it('maintains invitation audit trail', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $company = createTestCompany($owner);
    
    // Create invitation
    $invitationResponse = actingAs($owner)
        ->postJson("/api/v1/companies/{$company->id}/invitations", [
            'email' => $invitee->email,
            'role' => CompanyRole::Admin->value
        ])
        ->assertCreated();
    
    $invitationId = $invitationResponse->json('data.id');
    $token = $invitationResponse->json('data.token');
    
    // Verify invitation has proper audit fields
    $invitation = DB::table('auth.company_invitations')
        ->where('id', $invitationId)
        ->first();
    
    expect($invitation->invited_by_user_id)->toBe($owner->id);
    expect($invitation->created_at)->not->toBeNull();
    expect($invitation->updated_at)->not->toBeNull();
    expect($invitation->expires_at)->not->toBeNull();
    
    // Accept invitation
    $beforeAccept = now();
    
    actingAs($invitee)
        ->postJson("/api/company-invitations/{$token}/accept")
        ->assertOk();
    
    // Verify acceptance was recorded with audit trail
    $updatedInvitation = DB::table('auth.company_invitations')
        ->where('id', $invitationId)
        ->first();
    
    expect($updatedInvitation->status)->toBe('accepted');
    expect($updatedInvitation->accepted_by_user_id)->toBe($invitee->id);
    expect($updatedInvitation->accepted_at)->not->toBeNull();
    expect($updatedInvitation->accepted_at)->toBeGreaterThanOrEqual($beforeAccept);
    
    // Verify company membership has audit trail
    $membership = DB::table('auth.company_user')
        ->where('company_id', $company->id)
        ->where('user_id', $invitee->id)
        ->first();
    
    expect($membership->created_at)->not->toBeNull();
    expect($membership->updated_at)->not->toBeNull();
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