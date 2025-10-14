<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('completes full user selection flow for system owner', function () {
    // Arrange - Create system owner
    User::factory()->create([
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'name' => 'System Owner',
        'username' => 'sysowner',
        'email' => 'sysowner@example.com',
        'password' => Hash::make('password123'),
        'system_role' => 'system_owner',
    ]);

    // Step 1: Check setup status (should be false)
    $statusResponse = $this->getJson('/api/v1/setup/status');
    $statusResponse->assertStatus(200)
        ->assertJson(['is_setup' => false]);

    // Step 2: Login as system owner
    $loginResponse = Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'sysowner',
        'password' => 'password123',
    ]);
    $loginResponse->assertStatus(200)
        ->assertJsonPath('user.role', 'system_owner');

    $token = $loginResponse->json('token');

    // Step 3: Initialize system
    $initResponse = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/setup/initialize', [
        'confirm_reset' => true,
        'create_demo_data' => true,
        'industries' => ['hospitality', 'retail'],
    ]);
    $initResponse->assertStatus(201)
        ->assertJsonPath('success', true);

    // Step 4: Verify setup status
    $finalStatusResponse = $this->getJson('/api/v1/setup/status');
    $finalStatusResponse->assertStatus(200)
        ->assertJson([
            'is_setup' => true,
            'has_companies' => true,
            'has_users' => true,
        ]);
});

it('completes user selection flow for company owner with pre-existing companies', function () {
    // Arrange - Create companies first
    $companies = collect([
        Company::factory()->create([
            'id' => '550e8400-e29b-41d4-a716-446655440001',
            'name' => 'Grand Hotel',
            'industry' => 'hospitality',
            'base_currency' => 'USD',
        ]),
        Company::factory()->create([
            'id' => '550e8400-e29b-41d4-a716-446655440002',
            'name' => 'Fashion Store',
            'industry' => 'retail',
            'base_currency' => 'EUR',
        ]),
    ]);

    // Create company owner with access to one company
    $owner = User::factory()->create([
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'name' => 'Company Owner',
        'username' => 'owner',
        'email' => 'owner@example.com',
        'password' => Hash::make('password123'),
        'system_role' => 'company_owner',
    ]);

    CompanyUser::factory()->create([
        'user_id' => $owner->id,
        'company_id' => $companies[0]->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    // Step 1: Login
    $loginResponse = Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'owner',
        'password' => 'password123',
    ]);
    $loginResponse->assertStatus(200);

    $user = $loginResponse->json('user');
    expect($user['companies'])->toHaveCount(1);
    expect($user['companies'][0]['name'])->toBe('Grand Hotel');

    // Step 2: Get available companies
    $companiesResponse = $this->getJson('/api/v1/companies');
    $companiesResponse->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Grand Hotel');

    // Step 3: Switch to company
    $switchResponse = Pest\Laravel\postJson('/api/v1/companies/switch', [
        'company_id' => $companies[0]['id'],
    ]);
    $switchResponse->assertStatus(200)
        ->assertJsonPath('company.name', 'Grand Hotel');
});

it('handles user selection flow for accountant role', function () {
    // Arrange
    $userId = '550e8400-e29b-41d4-a716-446655440000';
    $companyId = '550e8400-e29b-41d4-a716-446655440001';

    Company::factory()->create([
        'id' => $companyId,
        'name' => 'Professional Services',
        'industry' => 'professional_services',
        'base_currency' => 'USD',
    ]);

    $accountant = User::factory()->create([
        'id' => $userId,
        'name' => 'Accountant User',
        'username' => 'accountant',
        'email' => 'accountant@example.com',
        'password' => Hash::make('password123'),
        'system_role' => 'accountant',
    ]);

    CompanyUser::factory()->create([
        'user_id' => $accountant->id,
        'company_id' => $companyId,
        'role' => 'accountant',
    ]);

    // Step 1: Login
    $loginResponse = Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'accountant',
        'password' => 'password123',
    ]);
    $loginResponse->assertStatus(200)
        ->assertJsonPath('user.role', 'accountant');
});

it('prevents member role from accessing system setup', function () {
    // Arrange
    User::factory()->create([
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'name' => 'Member User',
        'username' => 'member',
        'email' => 'member@example.com',
        'password' => Hash::make('password123'),
        'system_role' => 'member',
    ]);

    // Step 1: Login successfully
    $loginResponse = Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'member',
        'password' => 'password123',
    ]);
    $loginResponse->assertStatus(200)
        ->assertJsonPath('user.role', 'member');

    $token = $loginResponse->json('token');

    // Step 2: Attempt to initialize system (should fail)
    $initResponse = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/setup/initialize', [
        'confirm_reset' => true,
        'create_demo_data' => true,
    ]);
    $initResponse->assertStatus(403);
});

it('handles failed login attempts gracefully', function () {
    // Step 1: Attempt login with non-existent user
    $response1 = Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'nonexistent',
        'password' => 'password',
    ]);
    $response1->assertStatus(401);

    // Step 2: Attempt login with wrong password
    User::factory()->create([
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => Hash::make('correctpassword'),
        'system_role' => 'member',
    ]);

    $response2 = Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'testuser',
        'password' => 'wrongpassword',
    ]);
    $response2->assertStatus(401);

    // Step 3: Successful login should still work
    $response3 = Pest\Laravel\postJson('/api/v1/users/login', [
        'username' => 'testuser',
        'password' => 'correctpassword',
    ]);
    $response3->assertStatus(200);
});
