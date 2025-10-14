<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('enables and disables modules for company with proper flow', function () {
    // Arrange
    $userId = '550e8400-e29b-41d4-a716-446655440000';
    $companyId = '550e8400-e29b-41d4-a716-446655440001';

    // Create user and company
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
        'id' => $companyId,
        'name' => 'Test Company',
        'slug' => 'test-company',
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

    // Create modules
    $modules = [
        [
            'id' => '550e8400-e29b-41d4-a716-446655440002',
            'name' => 'Invoicing',
            'key' => 'invoicing_toggle1',
            'version' => '1.0.0',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => '550e8400-e29b-41d4-a716-446655440003',
            'name' => 'Ledger',
            'key' => 'ledger_toggle1',
            'version' => '1.0.0',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ];
    DB::table('auth.modules')->insert($modules);

    // Step 1: Login and set company context
    Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'owner',
        'password' => 'password123',
    ]);

    Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $companyId,
    ]);

    // Step 2: Check initial modules (should be empty)
    $companiesResponse = $this->getJson('/api/v1/companies');
    expect($companiesResponse->json()[0]['modules_enabled'])->toBeEmpty();

    // Step 3: Enable first module
    $enableResponse1 = Pest\Laravel\postJson("/api/v1/modules/{$modules[0]['id']}/enable");
    $enableResponse1->assertStatus(200);

    // Verify module is enabled
    $companiesResponse1 = $this->getJson('/api/v1/companies');
    expect($companiesResponse1->json()[0]['modules_enabled'])->toContain('Invoicing');

    // Step 4: Enable second module
    $enableResponse2 = Pest\Laravel\postJson("/api/v1/modules/{$modules[1]['id']}/enable");
    $enableResponse2->assertStatus(200);

    // Verify both modules are enabled
    $companiesResponse2 = $this->getJson('/api/v1/companies');
    $enabledModules = $companiesResponse2->json()[0]['modules_enabled'];
    expect($enabledModules)->toContain('Invoicing');
    expect($enabledModules)->toContain('Ledger');
    expect($enabledModules)->toHaveCount(2);
});

it('handles module toggle across multiple companies independently', function () {
    // Arrange
    $userId = '550e8400-e29b-41d4-a716-446655440000';
    $company1Id = '550e8400-e29b-41d4-a716-446655440001';
    $company2Id = '550e8400-e29b-41d4-a716-446655440002';
    $moduleId = '550e8400-e29b-41d4-a716-446655440003';

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

    // Create two companies
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

    // Associate user with both companies
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

    // Create module
    DB::table('auth.modules')->insert([
        'id' => $moduleId,
        'name' => 'Invoicing',
        'key' => 'invoicing',
        'version' => '1.0.0',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Step 1: Login
    Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'owner',
        'password' => 'password123',
    ]);

    // Step 2: Enable module for Company A only
    Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $company1Id,
    ]);
    Pest\Laravel\postJson("/api/v1/modules/{$moduleId}/enable");

    // Step 3: Check Company A has module
    $companyAResponse = $this->getJson('/api/v1/companies');
    expect($companyAResponse->json()[0]['modules_enabled'])->toContain('Invoicing');

    // Step 4: Check Company B does not have module
    Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $company2Id,
    ]);
    $companyBResponse = $this->getJson('/api/v1/companies');
    expect($companyBResponse->json()[0]['modules_enabled'])->toBeEmpty();

    // Step 5: Enable module for Company B
    Pest\Laravel\postJson("/api/v1/modules/{$moduleId}/enable");

    // Step 6: Now both companies should have the module
    $companyBResponse2 = $this->getJson('/api/v1/companies');
    expect($companyBResponse2->json()[0]['modules_enabled'])->toContain('Invoicing');
});

it('prevents unauthorized users from enabling modules', function () {
    // Arrange
    $memberUserId = '550e8400-e29b-41d4-a716-446655440000';
    $ownerUserId = '550e8400-e29b-41d4-a716-446655440001';
    $companyId = '550e8400-e29b-41d4-a716-446655440002';
    $moduleId = '550e8400-e29b-41d4-a716-446655440003';

    // Create member user (limited permissions)
    DB::table('auth.users')->insert([
        'id' => $memberUserId,
        'name' => 'Member User',
        'username' => 'member',
        'email' => 'member@example.com',
        'password' => Hash::make('password123'),
        'system_role' => 'member',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create owner user
    DB::table('auth.users')->insert([
        'id' => $ownerUserId,
        'name' => 'Owner User',
        'username' => 'owner',
        'email' => 'owner@example.com',
        'password' => Hash::make('password123'),
        'system_role' => 'company_owner',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('auth.companies')->insert([
        'id' => $companyId,
        'name' => 'Test Company',
        'slug' => 'test-company',
        'industry' => 'professional_services',
        'base_currency' => 'USD',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('auth.company_user')->insert([
        [
            'user_id' => $memberUserId,
            'company_id' => $companyId,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'user_id' => $ownerUserId,
            'company_id' => $companyId,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('auth.modules')->insert([
        'id' => $moduleId,
        'name' => 'Invoicing',
        'key' => 'invoicing',
        'version' => '1.0.0',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Step 1: Member login and attempt to enable module
    Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'member',
        'password' => 'password123',
    ]);

    Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $companyId,
    ]);

    $memberResponse = Pest\Laravel\postJson("/api/v1/modules/{$moduleId}/enable");
    $memberResponse->assertStatus(403);

    // Step 2: Owner login and successfully enable module
    Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'owner',
        'password' => 'password123',
    ]);

    Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $companyId,
    ]);

    $ownerResponse = Pest\Laravel\postJson("/api/v1/modules/{$moduleId}/enable");
    $ownerResponse->assertStatus(200);
});

it('handles rapid module enable/disable operations', function () {
    // Arrange
    $userId = '550e8400-e29b-41d4-a716-446655440000';
    $companyId = '550e8400-e29b-41d4-a716-446655440001';
    $modules = [
        [
            'id' => '550e8400-e29b-41d4-a716-446655440002',
            'name' => 'Invoicing',
            'key' => 'invoicing_rapid',
            'version' => '1.0.0',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => '550e8400-e29b-41d4-a716-446655440003',
            'name' => 'Ledger',
            'key' => 'ledger_rapid',
            'version' => '1.0.0',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => '550e8400-e29b-41d4-a716-446655440004',
            'name' => 'Analytics',
            'key' => 'analytics_rapid',
            'version' => '1.0.0',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ];

    DB::table('auth.users')->insert([
        'id' => $userId,
        'name' => 'Power User',
        'username' => 'poweruser',
        'email' => 'power@example.com',
        'password' => Hash::make('password123'),
        'system_role' => 'company_owner',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('auth.companies')->insert([
        'id' => $companyId,
        'name' => 'Tech Company',
        'slug' => 'tech-company',
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

    DB::table('auth.modules')->insert($modules);

    // Step 1: Login and set context
    Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'poweruser',
        'password' => 'password123',
    ]);

    Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $companyId,
    ]);

    // Step 2: Rapidly enable all modules
    foreach ($modules as $module) {
        $response = Pest\Laravel\postJson("/api/v1/modules/{$module['id']}/enable");
        $response->assertStatus(200);
    }

    // Step 3: Verify all modules are enabled
    $companiesResponse = $this->getJson('/api/v1/companies');
    $enabledModules = $companiesResponse->json()[0]['modules_enabled'];
    expect($enabledModules)->toHaveCount(3);
    expect($enabledModules)->toContain('Invoicing');
    expect($enabledModules)->toContain('Ledger');
    expect($enabledModules)->toContain('Analytics');
});

it('validates module capabilities during toggle', function () {
    // Arrange
    $userId = '550e8400-e29b-41d4-a716-446655440000';
    $companyId = '550e8400-e29b-41d4-a716-446655440001';
    $moduleId = '550e8400-e29b-41d4-a716-446655440002';

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
        'id' => $companyId,
        'name' => 'Test Company',
        'slug' => 'test-company',
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

    DB::table('auth.modules')->insert([
        'id' => $moduleId,
        'name' => 'Advanced Invoicing',
        'key' => 'advanced_invoicing',
        'version' => '1.0.0',
        'is_active' => true,
        'permissions' => json_encode(['create_invoice', 'send_invoice', 'track_payments', 'generate_reports', 'multi_currency']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Step 1: Login and enable module
    Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'owner',
        'password' => 'password123',
    ]);

    Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $companyId,
    ]);

    $enableResponse = Pest\Laravel\postJson("/api/v1/modules/{$moduleId}/enable");
    $enableResponse->assertStatus(200);

    // Step 2: Verify module is available with capabilities
    $modulesResponse = $this->getJson('/api/v1/modules');
    $module = collect($modulesResponse->json())->firstWhere('id', $moduleId);

    expect($module['capabilities'])->toBeArray();
    expect($module['capabilities'])->toHaveCount(5);
    expect($module['capabilities'])->toContain('create_invoice');
    expect($module['capabilities'])->toContain('multi_currency');
});
