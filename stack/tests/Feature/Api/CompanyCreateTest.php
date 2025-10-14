<?php

use App\Enums\CompanyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{actingAs, postJson};

uses(RefreshDatabase::class);

it('can create a company with valid data', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->postJson('/api/v1/companies', [
            'name' => 'Test Company LLC',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'country' => 'US',
            'language' => 'en',
            'locale' => 'en_US'
        ])
        ->assertCreated()
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
                'updated_at'
            ],
            'meta' => [
                'fiscal_year_created',
                'chart_of_accounts_created'
            ]
        ])
        ->assertJsonFragment([
            'name' => 'Test Company LLC',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'country' => 'US',
            'language' => 'en',
            'locale' => 'en_US',
            'is_active' => true
        ])
        ->assertJsonFragment([
            'meta' => [
                'fiscal_year_created' => true,
                'chart_of_accounts_created' => true
            ]
        ]);
});

it('requires authentication to create a company', function () {
    postJson('/api/v1/companies', [
        'name' => 'Test Company LLC',
        'currency' => 'USD',
        'timezone' => 'America/New_York'
    ])
        ->assertUnauthorized();
});

it('validates required fields when creating a company', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->postJson('/api/v1/companies', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'currency', 'timezone']);
});

it('validates name field when creating a company', function () {
    $user = User::factory()->create();

    // Test empty name
    actingAs($user)
        ->postJson('/api/v1/companies', [
            'name' => '',
            'currency' => 'USD',
            'timezone' => 'America/New_York'
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    // Test name too long
    actingAs($user)
        ->postJson('/api/v1/companies', [
            'name' => str_repeat('a', 256),
            'currency' => 'USD',
            'timezone' => 'America/New_York'
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('validates currency field when creating a company', function () {
    $user = User::factory()->create();

    // Test invalid currency format
    actingAs($user)
        ->postJson('/api/v1/companies', [
            'name' => 'Test Company',
            'currency' => 'INVALID',
            'timezone' => 'America/New_York'
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency']);

    // Test currency too long
    actingAs($user)
        ->postJson('/api/v1/companies', [
            'name' => 'Test Company',
            'currency' => 'USDD',
            'timezone' => 'America/New_York'
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency']);
});

it('validates timezone field when creating a company', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->postJson('/api/v1/companies', [
            'name' => 'Test Company',
            'currency' => 'USD',
            'timezone' => 'Invalid/Timezone'
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['timezone']);
});

it('validates country field format when creating a company', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->postJson('/api/v1/companies', [
            'name' => 'Test Company',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'country' => 'USA'  // Should be 2 letters
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country']);
});

it('creates company with minimal required data', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->postJson('/api/v1/companies', [
            'name' => 'Minimal Company',
            'currency' => 'EUR',
            'timezone' => 'Europe/London'
        ])
        ->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'slug',
                'currency',
                'timezone',
                'country', // nullable
                'language', // should default to 'en'
                'locale',  // should default to 'en_US'
                'is_active',
                'created_at',
                'updated_at'
            ]
        ])
        ->assertJsonFragment([
            'name' => 'Minimal Company',
            'currency' => 'EUR',
            'timezone' => 'Europe/London',
            'language' => 'en',
            'locale' => 'en_US',
            'is_active' => true
        ]);
});

it('generates unique slug for company name', function () {
    $user = User::factory()->create();

    $response1 = actingAs($user)
        ->postJson('/api/v1/companies', [
            'name' => 'Test Company LLC',
            'currency' => 'USD',
            'timezone' => 'America/New_York'
        ])
        ->assertCreated();

    $response2 = actingAs($user)
        ->postJson('/api/v1/companies', [
            'name' => 'Test Company LLC',
            'currency' => 'USD',
            'timezone' => 'America/New_York'
        ])
        ->assertCreated();

    $slug1 = $response1->json('data.slug');
    $slug2 = $response2->json('data.slug');

    expect($slug1)->not->toBe($slug2);
    expect($slug1)->toBe('test-company-llc');
    expect($slug2)->toContain('test-company-llc-');
});

it('assigns creator as company owner', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->postJson('/api/v1/companies', [
            'name' => 'Owner Test Company',
            'currency' => 'USD',
            'timezone' => 'America/New_York'
        ])
        ->assertCreated();

    $companyId = $response->json('data.id');

    // Verify user is assigned as owner
    $this->assertDatabaseHas('auth.company_user', [
        'company_id' => $companyId,
        'user_id' => $user->id,
        'role' => CompanyRole::Owner->value,
        'is_active' => true
    ]);
});

it('creates fiscal year and chart of accounts automatically', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->postJson('/api/v1/companies', [
            'name' => 'Auto Setup Company',
            'currency' => 'USD',
            'timezone' => 'America/New_York'
        ])
        ->assertCreated();

    $companyId = $response->json('data.id');

    // Verify fiscal year was created
    $this->assertDatabaseHas('acct.fiscal_years', [
        'company_id' => $companyId,
        'is_active' => true
    ]);

    // Verify accounts were created (should have default accounts)
    $this->assertDatabaseHas('acct.accounts', [
        'company_id' => $companyId,
        'is_active' => true
    ]);
});

it('returns company details after creation', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->postJson('/api/v1/companies', [
            'name' => 'Complete Test Company',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'country' => 'US'
        ])
        ->assertCreated();

    $data = $response->json('data');

    // Verify all expected fields are present
    expect($data)->toHaveKeys([
        'id', 'name', 'slug', 'currency', 'timezone', 'country',
        'language', 'locale', 'is_active', 'created_at', 'updated_at'
    ]);

    // Verify field types
    expect($data['id'])->toBeString();
    expect($data['name'])->toBeString();
    expect($data['slug'])->toBeString();
    expect($data['currency'])->toBeString();
    expect($data['timezone'])->toBeString();
    expect($data['is_active'])->toBeBool();
});
