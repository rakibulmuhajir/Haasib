<?php

use App\Enums\CompanyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use function Pest\Laravel\{artisan};

uses(RefreshDatabase::class);

it('invites user to company with required options', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'invitee@example.com',
        '--role' => 'accountant'
    ])
        ->assertExitCode(0)
        ->expectsOutput('✓ Invitation sent to invitee@example.com')
        ->expectsOutput("Company: {$company->name}")
        ->expectsOutput('Role: accountant')
        ->expectsOutput('Expires: 7 days')
        ->expectsOutput('Token: ');
});

it('invites user to company with all options', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'complete@example.com',
        '--role' => 'admin',
        '--expires-in-days' => 14,
        '--message' => 'Welcome to our team!',
        '--invited-by' => $owner->id
    ])
        ->assertExitCode(0)
        ->expectsOutput('✓ Invitation sent to complete@example.com')
        ->expectsOutput("Company: {$company->name}")
        ->expectsOutput('Role: admin')
        ->expectsOutput('Expires: 14 days');
});

it('requires company argument', function () {
    artisan('company:invite')
        ->assertExitCode(1);
});

it('requires email option', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    artisan('company:invite', [
        $company->slug,
        '--role' => 'accountant'
    ])
        ->assertExitCode(1);
});

it('requires role option', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'test@example.com'
    ])
        ->assertExitCode(1);
});

it('validates email format', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'invalid-email',
        '--role' => 'accountant'
    ])
        ->assertExitCode(1)
        ->expectsOutput('Invalid email address.');
});

it('validates role values', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'test@example.com',
        '--role' => 'invalid_role'
    ])
        ->assertExitCode(1)
        ->expectsOutput('Invalid role. Must be one of: owner, admin, accountant, viewer');
});

it('validates expires_in_days range', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    // Test negative days
    artisan('company:invite', [
        $company->slug,
        '--email' => 'test@example.com',
        '--role' => 'accountant',
        '--expires-in-days' => -1
    ])
        ->assertExitCode(1)
        ->expectsOutput('Expires in days must be between 1 and 30.');
    
    // Test too many days
    artisan('company:invite', [
        $company->slug,
        '--email' => 'test2@example.com',
        '--role' => 'accountant',
        '--expires-in-days' => 31
    ])
        ->assertExitCode(1)
        ->expectsOutput('Expires in days must be between 1 and 30.');
});

it('allows company owner to invite users', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'owner-invite@example.com',
        '--role' => 'accountant'
    ])
        ->assertExitCode(0);
    
    // Verify invitation was created
    $this->assertDatabaseHas('auth.company_invitations', [
        'company_id' => $company->id,
        'email' => 'owner-invite@example.com',
        'role' => CompanyRole::Accountant->value,
        'invited_by_user_id' => $owner->id,
        'status' => 'pending'
    ]);
});

it('allows company admin to invite users', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $company = createTestCompany($owner);
    addUserToCompany($company, $admin, CompanyRole::Admin);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'admin-invite@example.com',
        '--role' => 'viewer',
        '--invited-by' => $admin->id
    ])
        ->assertExitCode(0);
    
    // Verify invitation was created
    $this->assertDatabaseHas('auth.company_invitations', [
        'company_id' => $company->id,
        'email' => 'admin-invite@example.com',
        'role' => CompanyRole::Viewer->value,
        'invited_by_user_id' => $admin->id,
        'status' => 'pending'
    ]);
});

it('prevents accountant from inviting users', function () {
    $owner = User::factory()->create();
    $accountant = User::factory()->create();
    $company = createTestCompany($owner);
    addUserToCompany($company, $accountant, CompanyRole::Accountant);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'test@example.com',
        '--role' => 'viewer',
        '--invited-by' => $accountant->id
    ])
        ->assertExitCode(1)
        ->expectsOutput('You do not have permission to invite users to this company.');
    
    // Verify no invitation was created
    $this->assertDatabaseMissing('auth.company_invitations', [
        'company_id' => $company->id,
        'email' => 'test@example.com'
    ]);
});

it('prevents viewer from inviting users', function () {
    $owner = User::factory()->create();
    $viewer = User::Factory()->create();
    $company = createTestCompany($owner);
    addUserToCompany($company, $viewer, CompanyRole::Viewer);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'test@example.com',
        '--role' => 'viewer',
        '--invited-by' => $viewer->id
    ])
        ->assertExitCode(1)
        ->expectsOutput('You do not have permission to invite users to this company.');
});

it('prevents non-members from inviting users', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $company = createTestCompany($owner);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'test@example.com',
        '--role' => 'viewer',
        '--invited-by' => $outsider->id
    ])
        ->assertExitCode(1)
        ->expectsOutput('You do not have permission to invite users to this company.');
});

it('prevents duplicate invitations', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    $email = 'duplicate@example.com';
    
    // Create first invitation
    artisan('company:invite', [
        $company->slug,
        '--email' => $email,
        '--role' => 'accountant'
    ])
        ->assertExitCode(0);
    
    // Attempt to create duplicate invitation
    artisan('company:invite', [
        $company->slug,
        '--email' => $email,
        '--role' => 'viewer'
    ])
        ->assertExitCode(1)
        ->expectsOutput('An invitation for this email already exists.');
    
    // Verify only one invitation exists
    $this->assertDatabaseCount('auth.company_invitations', 1);
});

it('prevents invitations for existing members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $company = createTestCompany($owner);
    addUserToCompany($company, $member, CompanyRole::Accountant);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => $member->email,
        '--role' => 'viewer'
    ])
        ->assertExitCode(1)
        ->expectsOutput('User is already a member of this company.');
    
    // Verify no invitation was created
    $this->assertDatabaseMissing('auth.company_invitations', [
        'company_id' => $company->id,
        'email' => $member->email
    ]);
});

it('handles company identification by slug', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner, ['name' => 'Slug Test Company']);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'slug-test@example.com',
        '--role' => 'accountant'
    ])
        ->assertExitCode(0)
        ->expectsOutput("Company: {$company->name}");
    
    // Verify invitation was created for correct company
    $this->assertDatabaseHas('auth.company_invitations', [
        'company_id' => $company->id,
        'email' => 'slug-test@example.com'
    ]);
});

it('handles company identification by ID', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    artisan('company:invite', [
        $company->id,
        '--email' => 'id-test@example.com',
        '--role' => 'accountant'
    ])
        ->assertExitCode(0)
        ->expectsOutput("Company: {$company->name}");
    
    // Verify invitation was created for correct company
    $this->assertDatabaseHas('auth.company_invitations', [
        'company_id' => $company->id,
        'email' => 'id-test@example.com'
    ]);
});

it('handles non-existent company', function () {
    $owner = User::factory()->create();
    
    artisan('company:invite', [
        'non-existent-company',
        '--email' => 'test@example.com',
        '--role' => 'accountant',
        '--invited-by' => $owner->id
    ])
        ->assertExitCode(1)
        ->expectsOutput('Company not found.');
});

it('sets correct expiry date based on expires_in_days', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'expiry@example.com',
        '--role' => 'accountant',
        '--expires-in-days' => 14
    ])
        ->assertExitCode(0)
        ->expectsOutput('Expires: 14 days');
    
    // Verify expiry date is correct
    $invitation = DB::table('auth.company_invitations')
        ->where('email', 'expiry@example.com')
        ->first();
    
    $expectedExpiry = now()->addDays(14);
    expect($invitation->expires_at)->toEqual($expectedExpiry);
});

it('uses default expiry when not specified', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'default@example.com',
        '--role' => 'accountant'
    ])
        ->assertExitCode(0)
        ->expectsOutput('Expires: 7 days');
    
    // Verify default expiry (7 days)
    $invitation = DB::table('auth.company_invitations')
        ->where('email', 'default@example.com')
        ->first();
    
    $expectedExpiry = now()->addDays(7);
    expect($invitation->expires_at)->toEqual($expectedExpiry);
});

it('generates unique invitation tokens', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    // Create two invitations
    artisan('company:invite', [
        $company->slug,
        '--email' => 'token1@example.com',
        '--role' => 'accountant'
    ])
        ->assertExitCode(0);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'token2@example.com',
        '--role' => 'viewer'
    ])
        ->assertExitCode(0);
    
    // Get invitations and verify unique tokens
    $invitations = DB::table('auth.company_invitations')
        ->where('company_id', $company->id)
        ->get();
    
    expect($invitations)->toHaveCount(2);
    expect($invitations[0]->token)->not->toBe($invitations[1]->token);
    expect($invitations[0]->token)->toBeString();
    expect($invitations[1]->token)->toBeString();
});

it('enforces role hierarchy for invitations', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $company = createTestCompany($owner);
    addUserToCompany($company, $admin, CompanyRole::Admin);
    
    // Admin should be able to invite accountants and viewers
    artisan('company:invite', [
        $company->slug,
        '--email' => 'accountant@example.com',
        '--role' => 'accountant',
        '--invited-by' => $admin->id
    ])
        ->assertExitCode(0);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'viewer@example.com',
        '--role' => 'viewer',
        '--invited-by' => $admin->id
    ])
        ->assertExitCode(0);
    
    // Admin should not be able to invite owners
    artisan('company:invite', [
        $company->slug,
        '--email' => 'owner@example.com',
        '--role' => 'owner',
        '--invited-by' => $admin->id
    ])
        ->assertExitCode(1)
        ->expectsOutput('You do not have permission to assign this role.');
});

it('handles multiple invitations successfully', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    $emails = [
        'multi1@example.com',
        'multi2@example.com',
        'multi3@example.com',
        'multi4@example.com',
        'multi5@example.com'
    ];
    
    foreach ($emails as $email) {
        $role = fake()->randomElement(['accountant', 'viewer']);
        
        artisan('company:invite', [
            $company->slug,
            '--email' => $email,
            '--role' => $role
        ])
            ->assertExitCode(0)
            ->expectsOutput("✓ Invitation sent to {$email}")
            ->expectsOutput("Company: {$company->name}")
            ->expectsOutput("Role: {$role}");
    }
    
    // Verify all invitations were created
    $this->assertDatabaseCount('auth.company_invitations', 5);
    
    foreach ($emails as $email) {
        $this->assertDatabaseHas('auth.company_invitations', [
            'company_id' => $company->id,
            'email' => $email,
            'status' => 'pending'
        ]);
    }
});

it('provides clear error messages for validation failures', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner);
    
    // Test invalid email
    artisan('company:invite', [
        $company->slug,
        '--email' => 'not-an-email',
        '--role' => 'accountant'
    ])
        ->assertExitCode(1)
        ->expectsOutput('Invalid email address.');
    
    // Test invalid role
    artisan('company:invite', [
        $company->slug,
        '--email' => 'test@example.com',
        '--role' => 'invalid'
    ])
        ->assertExitCode(1)
        ->expectsOutput('Invalid role. Must be one of: owner, admin, accountant, viewer');
    
    // Test invalid expiry
    artisan('company:invite', [
        $company->slug,
        '--email' => 'test2@example.com',
        '--role' => 'accountant',
        '--expires-in-days' => 0
    ])
        ->assertExitCode(1)
        ->expectsOutput('Expires in days must be between 1 and 30.');
});

it('displays invitation details in formatted output', function () {
    $owner = User::factory()->create();
    $company = createTestCompany($owner, ['name' => 'Detailed Company LLC']);
    
    artisan('company:invite', [
        $company->slug,
        '--email' => 'detailed@example.com',
        '--role' => 'accountant',
        '--expires-in-days' => 10
    ])
        ->assertExitCode(0)
        ->expectsOutput('✓ Invitation sent to detailed@example.com')
        ->expectsOutput('Company: Detailed Company LLC')
        ->expectsOutput('Role: accountant')
        ->expectsOutput('Expires: 10 days')
        ->expectsOutput('Token: ');
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
    
    // Create company record
    DB::table('auth.companies')->insert((array) $companyData);
    
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