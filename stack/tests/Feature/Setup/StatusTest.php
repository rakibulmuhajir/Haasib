<?php

use App\Models\Company;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('returns setup status when system is not initialized', function () {
    // Act
    $response = $this->getJson('/api/v1/setup/status');

    // Assert
    $response->assertStatus(200)
        ->assertJsonStructure([
            'is_setup',
            'has_companies',
            'has_users',
            'modules_enabled',
        ])
        ->assertJson([
            'is_setup' => false,
            'has_companies' => false,
            'has_users' => false,
            'modules_enabled' => [],
        ]);
});

it('returns setup status when system is initialized with data', function () {
    // Arrange - Setup some data
    $user = User::factory()->create([
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'system_role' => 'system_owner',
    ]);

    Company::factory()->create([
        'name' => 'Test Company',
        'industry' => 'hospitality',
        'base_currency' => 'USD',
        'created_by_user_id' => $user->id, // Use the created user's UUID
    ]);

    Module::factory()->create([
        'name' => 'Test Core Module',
        'key' => 'core',
        'is_active' => true,
    ]);

    // Act
    $response = $this->getJson('/api/v1/setup/status');

    // Assert
    $response->assertStatus(200)
        ->assertJsonStructure([
            'is_setup',
            'has_companies',
            'has_users',
            'modules_enabled',
        ])
        ->assertJson([
            'is_setup' => true,
            'has_companies' => true,
            'has_users' => true,
        ]);

    // Verify modules_enabled is an array with module names
    $data = $response->json();
    expect($data['modules_enabled'])->toBeArray();
    expect($data['modules_enabled'])->toContain('Test Core Module');
});

it('returns setup status when system has companies but no users', function () {
    // Arrange - Only companies (without creating users via factory)
    Company::factory()->create([
        'name' => 'Test Company',
        'industry' => 'retail',
        'base_currency' => 'USD',
        'created_by_user_id' => null, // Don't create a user
    ]);

    // Act
    $response = $this->getJson('/api/v1/setup/status');

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'is_setup' => true, // Has some data
            'has_companies' => true,
            'has_users' => false,
            'modules_enabled' => [],
        ]);
});

it('returns setup status when system has users but no companies', function () {
    // Arrange - Only users
    User::factory()->create([
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'system_role' => 'company_owner',
    ]);

    // Act
    $response = $this->getJson('/api/v1/setup/status');

    // Assert
    $response->assertStatus(200)
        ->assertJson([
            'is_setup' => true, // Has some data
            'has_companies' => false,
            'has_users' => true,
            'modules_enabled' => [],
        ]);
});

it('returns empty modules_enabled when no modules exist', function () {
    // Act
    $response = $this->getJson('/api/v1/setup/status');

    // Assert
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['modules_enabled'])->toBeArray();
    expect($data['modules_enabled'])->toBeEmpty();
});

it('returns multiple enabled modules in status', function () {
    // Arrange - Need a user for modules to be returned
    $user = User::factory()->create([
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'system_role' => 'system_owner',
    ]);

    // Multiple modules
    Module::factory()->create([
        'name' => 'Test Core Module',
        'key' => 'core-'.Str::uuid(),
        'is_active' => true,
    ]);

    Module::factory()->create([
        'name' => 'Test Invoicing Module',
        'key' => 'invoicing-'.Str::uuid(),
        'is_active' => true,
    ]);

    Module::factory()->create([
        'name' => 'Test Ledger Module',
        'key' => 'ledger-'.Str::uuid(),
        'is_active' => false,
    ]);

    // Act
    $response = $this->getJson('/api/v1/setup/status');

    // Assert
    $response->assertStatus(200);
    $data = $response->json();
    expect($data['modules_enabled'])->toBeArray();
    expect($data['modules_enabled'])->toContain('Test Core Module');
    expect($data['modules_enabled'])->toContain('Test Invoicing Module');
    expect($data['modules_enabled'])->not->toContain('Test Ledger Module'); // Should not include disabled modules
});
