<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('allows seamless company context switching for multi-company users', function () {
    // Arrange
    $userId = '550e8400-e29b-41d4-a716-446655440000';
    $companies = [
        [
            'id' => '550e8400-e29b-41d4-a716-446655440001',
            'name' => 'Hotel Paradise',
            'slug' => 'hotel-paradise',
            'industry' => 'hospitality',
            'base_currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => '550e8400-e29b-41d4-a716-446655440002',
            'name' => 'Retail Express',
            'slug' => 'retail-express',
            'industry' => 'retail',
            'base_currency' => 'EUR',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => '550e8400-e29b-41d4-a716-446655440003',
            'name' => 'Legal Advisors',
            'slug' => 'legal-advisors',
            'industry' => 'professional_services',
            'base_currency' => 'GBP',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ];

    DB::table('auth.users')->insert([
        'id' => $userId,
        'name' => 'Multi-Company Owner',
        'username' => 'multiowner',
        'email' => 'multi@example.com',
        'password' => Hash::make('password123'),
        'system_role' => 'company_owner',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('auth.companies')->insert($companies);

    foreach ($companies as $company) {
        DB::table('auth.company_user')->insert([
            'user_id' => $userId,
            'company_id' => $company['id'],
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Step 1: Login and get initial context
    $loginResponse = Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'multiowner',
        'password' => 'password123',
    ]);
    $loginResponse->assertStatus(200);

    $activeCompany = $loginResponse->json('active_company');
    expect($activeCompany['name'])->toBe('Hotel Paradise'); // First company by default

    // Step 2: Get all available companies
    $companiesResponse = $this->getJson('/api/v1/companies');
    $companiesResponse->assertStatus(200)
        ->assertJsonCount(3);

    // Step 3: Switch to second company
    $switchResponse1 = Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $companies[1]['id'],
    ]);
    $switchResponse1->assertStatus(200)
        ->assertJsonPath('company.name', 'Retail Express')
        ->assertJsonPath('company.industry', 'retail')
        ->assertJsonPath('company.base_currency', 'EUR');

    // Step 4: Switch to third company
    $switchResponse2 = Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $companies[2]['id'],
    ]);
    $switchResponse2->assertStatus(200)
        ->assertJsonPath('company.name', 'Legal Advisors')
        ->assertJsonPath('company.industry', 'professional_services')
        ->assertJsonPath('company.base_currency', 'GBP');

    // Step 5: Switch back to first company
    $switchResponse3 = Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $companies[0]['id'],
    ]);
    $switchResponse3->assertStatus(200)
        ->assertJsonPath('company.name', 'Hotel Paradise');
});

it('maintains module context during company switching', function () {
    // Arrange
    $userId = '550e8400-e29b-41d4-a716-446655440000';
    $company1Id = '550e8400-e29b-41d4-a716-446655440001';
    $company2Id = '550e8400-e29b-41d4-a716-446655440002';

    // Create user
    DB::table('auth.users')->insert([
        'id' => $userId,
        'name' => 'Company Owner',
        'username' => 'owner',
        'email' => 'owner@example.com',
        'password' => Hash::make('password123'),
        'system_role' => 'company_owner',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create companies
    DB::table('auth.companies')->insert([
        [
            'id' => $company1Id,
            'name' => 'Company A',
            'slug' => 'company-a',
            'industry' => 'hospitality',
            'base_currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => $company2Id,
            'name' => 'Company B',
            'slug' => 'company-b',
            'industry' => 'retail',
            'base_currency' => 'EUR',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Associate user with companies
    DB::table('auth.company_user')->insert([
        [
            'user_id' => $userId,
            'company_id' => $company1Id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'user_id' => $userId,
            'company_id' => $company2Id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Create modules
    $modules = [
        [
            'id' => '550e8400-e29b-41d4-a716-446655440003',
            'name' => 'Core',
            'version' => '1.0.0',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => '550e8400-e29b-41d4-a716-446655440004',
            'name' => 'Invoicing',
            'version' => '1.0.0',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ];
    DB::table('auth.modules')->insert($modules);

    // Enable different modules for each company
    DB::table('auth.company_modules')->insert([
        [
            'company_id' => $company1Id,
            'module_id' => $modules[0]['id'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'company_id' => $company2Id,
            'module_id' => $modules[0]['id'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'company_id' => $company2Id,
            'module_id' => $modules[1]['id'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Step 1: Login and switch to Company A
    Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'owner',
        'password' => 'password123',
    ]);

    $switch1Response = Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $company1Id,
    ]);
    $switch1Response->assertStatus(200);
    expect($switch1Response->json('company.modules_enabled'))->toBe(['Core']);

    // Step 2: Switch to Company B
    $switch2Response = Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $company2Id,
    ]);
    $switch2Response->assertStatus(200);
    expect($switch2Response->json('company.modules_enabled'))->toBe(['Core', 'Invoicing']);
});

it('prevents switching to inactive companies', function () {
    // Arrange
    $userId = '550e8400-e29b-41d4-a716-446655440000';
    $activeCompanyId = '550e8400-e29b-41d4-a716-446655440001';
    $inactiveCompanyId = '550e8400-e29b-41d4-a716-446655440002';

    DB::table('auth.users')->insert([
        'id' => $userId,
        'name' => 'Company Owner',
        'username' => 'owner',
        'email' => 'owner@example.com',
        'password' => Hash::make('password123'),
        'system_role' => 'company_owner',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('auth.companies')->insert([
        [
            'id' => $activeCompanyId,
            'name' => 'Active Company',
            'slug' => 'active-company',
            'industry' => 'hospitality',
            'base_currency' => 'USD',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => $inactiveCompanyId,
            'name' => 'Inactive Company',
            'slug' => 'inactive-company',
            'industry' => 'retail',
            'base_currency' => 'EUR',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('auth.company_user')->insert([
        [
            'user_id' => $userId,
            'company_id' => $activeCompanyId,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'user_id' => $userId,
            'company_id' => $inactiveCompanyId,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Step 1: Login
    Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'owner',
        'password' => 'password123',
    ]);

    // Step 2: Try to switch to inactive company
    $response = Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $inactiveCompanyId,
    ]);
    $response->assertStatus(403)
        ->assertJson(['message' => 'Company is inactive']);
});

it('handles rapid context switching without conflicts', function () {
    // Arrange
    $userId = '550e8400-e29b-41d4-a716-446655440000';
    $companyIds = [
        '550e8400-e29b-41d4-a716-446655440001',
        '550e8400-e29b-41d4-a716-446655440002',
        '550e8400-e29b-41d4-a716-446655440003',
    ];

    DB::table('auth.users')->insert([
        'id' => $userId,
        'name' => 'Fast Switcher',
        'username' => 'fastswitcher',
        'email' => 'fast@example.com',
        'password' => Hash::make('password123'),
        'system_role' => 'company_owner',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($companyIds as $index => $companyId) {
        DB::table('auth.companies')->insert([
            'id' => $companyId,
            'name' => 'Company '.($index + 1),
            'slug' => 'company-'.($index + 1),
            'industry' => 'professional_services',
            'base_currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('auth.company_user')->insert([
            'user_id' => $userId,
            'company_id' => $companyId,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Step 1: Login
    Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'fastswitcher',
        'password' => 'password123',
    ]);

    // Step 2: Rapid switching between companies
    for ($i = 0; $i < 3; $i++) {
        foreach ($companyIds as $index => $companyId) {
            $response = Pest\Laravel\postJson('/api/v1/companies/switch', [
                'company_id' => $companyId,
            ]);
            $response->assertStatus(200)
                ->assertJsonPath('company.name', 'Company '.($index + 1));
        }
    }
});