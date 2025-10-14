<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists companies accessible to authenticated user via API', function () {
    // Arrange - Create user and companies
    $user = User::factory()->create([
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
        'system_role' => 'user',
        'is_active' => true,
    ]);

    $company1 = Company::factory()->create([
        'name' => 'First Company',
        'slug' => 'first-company',
        'base_currency' => 'USD',
        'is_active' => true,
    ]);

    $company2 = Company::factory()->create([
        'name' => 'Second Company',
        'slug' => 'second-company', 
        'base_currency' => 'EUR',
        'is_active' => true,
    ]);

    // Create user-company relationships
    CompanyUser::factory()->create([
        'user_id' => $user->id,
        'company_id' => $company1->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    CompanyUser::factory()->create([
        'user_id' => $user->id,
        'company_id' => $company2->id,
        'role' => 'admin',
        'is_active' => true,
    ]);

    // Act - Call API endpoint
    $response = actingAs($user)->getJson('/api/v1/companies');

    // Assert
    $response->assertStatus(200)
        ->assertJsonStructure([
            'companies' => [
                '*' => [
                    'id',
                    'name',
                    'role',
                    'is_active',
                ],
            ],
        ]);

    $data = $response->json();
    expect($data['companies'])->toHaveCount(2);
    
    $companyNames = collect($data['companies'])->pluck('name')->toArray();
    expect($companyNames)->toContain('First Company', 'Second Company');
});

it('creates companies with correct schema', function () {
    // Arrange - Create user first
    $owner = User::factory()->create([
        'name' => 'Company Owner',
        'email' => fake()->unique()->email(),
        'password' => Hash::make('password123'),
        'system_role' => 'user',
    ]);

    $company = Company::factory()->create([
        'name' => 'Test Company',
        'slug' => 'test-company-schema',
        'base_currency' => 'USD',
        'created_by_user_id' => $owner->id,
        'is_active' => true,
    ]);

    expect($company->name)->toBe('Test Company');
    expect($company->slug)->toBe('test-company-schema');
    expect($company->base_currency)->toBe('USD');
    expect($company->is_active)->toBeTrue();
});

it('creates company-user relationships correctly', function () {
    // Arrange
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => fake()->unique()->email(),
        'password' => Hash::make('password'),
        'system_role' => 'user',
    ]);

    $company = Company::factory()->create([
        'name' => 'Test Company',
        'slug' => 'test-company-relationship',
        'base_currency' => 'USD',
        'created_by_user_id' => $user->id,
        'is_active' => true,
    ]);

    $relationship = CompanyUser::factory()->create([
        'user_id' => $user->id,
        'company_id' => $company->id,
        'role' => 'owner',
    ]);

    expect($relationship->role)->toBe('owner');
});

it('validates company fields correctly', function () {
    // Test required fields
    expect(function () {
        Company::create([
            'name' => 'Test Company',
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

it('enforces unique company slug constraint', function () {
    // Arrange
    $slug = fake()->unique()->slug();
    Company::factory()->create([
        'name' => 'First Company',
        'slug' => $slug,
        'base_currency' => 'USD',
        'is_active' => true,
    ]);

    expect(function () use ($slug) {
        Company::factory()->create([
            'name' => 'Second Company',
            'slug' => $slug,
            'base_currency' => 'EUR',
            'is_active' => true,
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});
