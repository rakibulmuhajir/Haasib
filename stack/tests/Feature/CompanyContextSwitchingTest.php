<?php

use App\Enums\CompanyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use function Pest\Laravel\{actingAs, postJson, getJson};

uses(RefreshDatabase::class);

it('handles complete company context switching workflow', function () {
    // Setup: Create user with multiple companies and different roles
    $user = User::factory()->create(['email' => 'multiuser@example.com']);
    
    $company1 = createTestCompany($user, [
        'name' => 'First Company',
        'currency' => 'USD',
        'timezone' => 'America/New_York'
    ]);
    
    $company2 = createTestCompany($user, [
        'name' => 'Second Company',
        'currency' => 'EUR',
        'timezone' => 'Europe/Paris'
    ]);
    
    $company3 = createTestCompanyWithOwner($user, [
        'name' => 'Third Company',
        'currency' => 'GBP',
        'timezone' => 'Europe/London'
    ]);
    
    // Add user as admin to company3
    addUserToCompany($company3, $user, CompanyRole::Admin);
    
    // Step 1: Verify initial context (should be null)
    $initialContext = actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk();
    
    expect($initialContext->json('data.company'))->toBeNull();
    expect($initialContext->json('data.user_role'))->toBeNull();
    expect($initialContext->json('data.available_companies'))->toHaveCount(3);
    
    // Step 2: Switch to first company (owner role)
    $switch1Response = actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company1->id
        ])
        ->assertOk();
    
    expect($switch1Response->json('data.company.id'))->toBe($company1->id);
    expect($switch1Response->json('data.company.name'))->toBe('First Company');
    expect($switch1Response->json('data.company.currency'))->toBe('USD');
    expect($switch1Response->json('data.user_role'))->toBe(CompanyRole::Owner->value);
    
    // Verify context is updated
    $context1 = actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk();
    
    expect($context1->json('data.company.id'))->toBe($company1->id);
    expect($context1->json('data.user_role'))->toBe(CompanyRole::Owner->value);
    
    // Step 3: Switch to second company (owner role)
    $switch2Response = actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company2->id
        ])
        ->assertOk();
    
    expect($switch2Response->json('data.company.id'))->toBe($company2->id);
    expect($switch2Response->json('data.company.name'))->toBe('Second Company');
    expect($switch2Response->json('data.company.currency'))->toBe('EUR');
    expect($switch2Response->json('data.user_role'))->toBe(CompanyRole::Owner->value);
    
    // Step 4: Switch to third company (admin role)
    $switch3Response = actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company3->id
        ])
        ->assertOk();
    
    expect($switch3Response->json('data.company.id'))->toBe($company3->id);
    expect($switch3Response->json('data.company.name'))->toBe('Third Company');
    expect($switch3Response->json('data.company.currency'))->toBe('GBP');
    expect($switch3Response->json('data.user_role'))->toBe(CompanyRole::Admin->value);
    
    // Step 5: Verify all companies are still accessible
    actingAs($user)
        ->getJson("/api/v1/companies/{$company1->id}")
        ->assertOk()
        ->assertJsonFragment(['user_role' => CompanyRole::Owner->value]);
    
    actingAs($user)
        ->getJson("/api/v1/companies/{$company2->id}")
        ->assertOk()
        ->assertJsonFragment(['user_role' => CompanyRole::Owner->value]);
    
    actingAs($user)
        ->getJson("/api/v1/companies/{$company3->id}")
        ->assertOk()
        ->assertJsonFragment(['user_role' => CompanyRole::Admin->value]);
});

it('maintains context across multiple requests', function () {
    $user = User::factory()->create();
    $company1 = createTestCompany($user);
    $company2 = createTestCompany($user);
    
    // Switch to company 1
    actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company1->id
        ])
        ->assertOk();
    
    // Verify context persists across multiple requests
    for ($i = 0; $i < 5; $i++) {
        $contextResponse = actingAs($user)
            ->getJson('/api/company-context/current')
            ->assertOk();
        
        expect($contextResponse->json('data.company.id'))->toBe($company1->id);
    }
    
    // Switch to company 2
    actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company2->id
        ])
        ->assertOk();
    
    // Verify new context persists
    for ($i = 0; $i < 5; $i++) {
        $contextResponse = actingAs($user)
            ->getJson('/api/company-context/current')
            ->assertOk();
        
        expect($contextResponse->json('data.company.id'))->toBe($company2->id);
    }
});

it('handles context switching with different user roles', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $accountant = User::factory()->create();
    $viewer = User::factory()->create();
    
    $company = createTestCompany($owner);
    addUserToCompany($company, $admin, CompanyRole::Admin);
    addUserToCompany($company, $accountant, CompanyRole::Accountant);
    addUserToCompany($company, $viewer, CompanyRole::Viewer);
    
    // Test each role can switch to company
    $roles = [
        $admin => CompanyRole::Admin,
        $accountant => CompanyRole::Accountant,
        $viewer => CompanyRole::Viewer,
    ];
    
    foreach ($roles as $user => $expectedRole) {
        $switchResponse = actingAs($user)
            ->postJson('/api/company-context/switch', [
                'company_id' => $company->id
            ])
            ->assertOk();
        
        expect($switchResponse->json('data.user_role'))->toBe($expectedRole->value);
        
        $contextResponse = actingAs($user)
            ->getJson('/api/company-context/current')
            ->assertOk();
        
        expect($contextResponse->json('data.user_role'))->toBe($expectedRole->value);
    }
});

it('includes correct fiscal year information in context', function () {
    $user = User::factory()->create();
    $company1 = createTestCompany($user);
    $company2 = createTestCompany($user);
    
    // Create different fiscal years for each company
    $fiscalYear1 = createFiscalYear($company1, '2025', '2025-01-01', '2025-12-31');
    $fiscalYear2 = createFiscalYear($company2, '2024', '2024-01-01', '2024-12-31');
    
    // Switch to company 1
    $switch1Response = actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company1->id
        ])
        ->assertOk();
    
    expect($switch1Response->json('data.fiscal_year.id'))->toBe($fiscalYear1->id);
    expect($switch1Response->json('data.fiscal_year.name'))->toBe('2025');
    expect($switch1Response->json('data.fiscal_year.is_current'))->toBeTrue();
    
    // Switch to company 2
    $switch2Response = actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company2->id
        ])
        ->assertOk();
    
    expect($switch2Response->json('data.fiscal_year.id'))->toBe($fiscalYear2->id);
    expect($switch2Response->json('data.fiscal_year.name'))->toBe('2024');
    expect($switch2Response->json('data.fiscal_year.is_current'))->toBeTrue();
});

it('handles context switching with company currency differences', function () {
    $user = User::factory()->create();
    
    $usCompany = createTestCompany($user, ['currency' => 'USD']);
    $eurCompany = createTestCompany($user, ['currency' => 'EUR']);
    $gbpCompany = createTestCompany($user, ['currency' => 'GBP']);
    
    $companies = [
        $usCompany => 'USD',
        $eurCompany => 'EUR',
        $gbpCompany => 'GBP'
    ];
    
    foreach ($companies as $company => $expectedCurrency) {
        $switchResponse = actingAs($user)
            ->postJson('/api/company-context/switch', [
                'company_id' => $company->id
            ])
            ->assertOk();
        
        expect($switchResponse->json('data.company.currency'))->toBe($expectedCurrency);
        
        $contextResponse = actingAs($user)
            ->getJson('/api/company-context/current')
            ->assertOk();
        
        expect($contextResponse->json('data.company.currency'))->toBe($expectedCurrency);
    }
});

it('prevents context switching to inactive companies for non-owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $inactiveCompany = createTestCompany($owner, ['is_active' => false]);
    addUserToCompany($inactiveCompany, $member, CompanyRole::Viewer);
    
    $activeCompany = createTestCompany($owner);
    addUserToCompany($activeCompany, $member, CompanyRole::Viewer);
    
    // Member should be able to switch to active company
    actingAs($member)
        ->postJson('/api/company-context/switch', [
            'company_id' => $activeCompany->id
        ])
        ->assertOk();
    
    // Member should not be able to switch to inactive company
    actingAs($member)
        ->postJson('/api/company-context/switch', [
            'company_id' => $inactiveCompany->id
        ])
        ->assertForbidden()
        ->assertJsonFragment([
            'message' => 'You cannot switch to an inactive company.'
        ]);
    
    // Owner should be able to switch to inactive company
    actingAs($owner)
        ->postJson('/api/company-context/switch', [
            'company_id' => $inactiveCompany->id
        ])
        ->assertOk();
});

it('handles context switching with inactive user memberships', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $company = createTestCompany($owner);
    
    // Add member with inactive status
    addUserToCompany($company, $member, CompanyRole::Viewer, false);
    
    // User should not be able to switch to company with inactive membership
    actingAs($member)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company->id
        ])
        ->assertForbidden()
        ->assertJsonFragment([
            'message' => 'Your access to this company is not active.'
        ]);
    
    // Activate membership
    DB::table('auth.company_user')
        ->where('company_id', $company->id)
        ->where('user_id', $member->id)
        ->update(['is_active' => true]);
    
    // User should now be able to switch
    actingAs($member)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company->id
        ])
        ->assertOk();
});

it('maintains authentication across context switches', function () {
    $user = User::factory()->create();
    $company1 = createTestCompany($user);
    $company2 = createTestCompany($user);
    
    // Switch to company 1
    actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company1->id
        ])
        ->assertOk();
    
    // Verify user is still authenticated
    actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk();
    
    // Switch to company 2
    actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company2->id
        ])
        ->assertOk();
    
    // Verify user is still authenticated after switch
    actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk();
    
    // Switch back to company 1
    actingAs($user)
        ->postJson('/api/company-context/switch', [
            'company_id' => $company1->id
        ])
        ->assertOk();
    
    // Verify user is still authenticated
    actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk();
});

it('provides correct available companies list in context', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    
    $company1 = createTestCompany($user);
    $company2 = createTestCompany($user);
    $company3 = createTestCompany($otherUser);
    
    // Add user to company3 with different role
    addUserToCompany($company3, $user, CompanyRole::Accountant);
    
    // Get current context
    $contextResponse = actingAs($user)
        ->getJson('/api/company-context/current')
        ->assertOk();
    
    $availableCompanies = $contextResponse->json('data.available_companies');
    
    // Verify all 3 companies are available
    expect($availableCompanies)->toHaveCount(3);
    
    // Verify correct roles for each company
    $companyRoles = [
        $company1->id => CompanyRole::Owner->value,
        $company2->id => CompanyRole::Owner->value,
        $company3->id => CompanyRole::Accountant->value,
    ];
    
    foreach ($availableCompanies as $company) {
        expect($company['role'])->toBe($companyRoles[$company['id']]);
    }
});

it('handles rapid context switching without conflicts', function () {
    $user = User::factory()->create();
    $companies = [];
    
    // Create 5 companies
    for ($i = 1; $i <= 5; $i++) {
        $companies[] = createTestCompany($user, [
            'name' => "Company {$i}",
            'currency' => 'USD'
        ]);
    }
    
    // Rapidly switch between companies
    for ($i = 0; $i < 10; $i++) {
        $randomCompany = $companies[array_rand($companies)];
        
        $switchResponse = actingAs($user)
            ->postJson('/api/company-context/switch', [
                'company_id' => $randomCompany->id
            ])
            ->assertOk();
        
        // Verify switch was successful
        expect($switchResponse->json('data.company.id'))->toBe($randomCompany->id);
        
        // Verify current context
        $contextResponse = actingAs($user)
            ->getJson('/api/company-context/current')
            ->assertOk();
        
        expect($contextResponse->json('data.company.id'))->toBe($randomCompany->id);
    }
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

function createTestCompanyWithOwner(User $owner, array $overrides = []): mixed
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
        'created_by_user_id' => $owner->id,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
    
    // Create company record without owner relationship
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

function createFiscalYear(mixed $company, string $name, string $startDate, string $endDate): mixed
{
    $fiscalYearId = DB::table('accounting.fiscal_years')->insertGetId([
        'id' => fake()->uuid(),
        'company_id' => $company->id,
        'name' => $name,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'is_current' => true,
        'is_locked' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    return (object) ['id' => $fiscalYearId, 'name' => $name];
}