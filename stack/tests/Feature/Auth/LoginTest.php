<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('logs in a user successfully via API', function () {
    // Arrange - Create a user with correct schema
    $username = 'testuser_' . fake()->unique()->numberBetween(1000, 9999);
    User::factory()->create([
        'name' => 'Test User',
        'username' => $username,
        'email' => fake()->unique()->email(),
        'password' => Hash::make('password123'),
        'system_role' => 'user',
        'is_active' => true,
    ]);

    // Act - Test API login endpoint
    $response = $this->postJson('/api/v1/users/login', [
        'username' => $username,
        'password' => 'password123',
    ]);

    // Assert - Should return successful JSON response
    $response->assertStatus(200)
        ->assertJsonStructure([
            'user' => [
                'id',
                'name',
                'username',
                'email',
                'role',
                'companies',
            ],
        ]);
});

it('creates users with correct schema', function () {
    // Arrange & Act
    $email = fake()->unique()->email();
    $user = User::factory()->create([
        'name' => 'Admin User',
        'email' => $email,
        'password' => Hash::make('password123'),
        'system_role' => 'admin',
        'is_active' => true,
    ]);

    expect($user->system_role)->toBe('admin');
    expect($user->is_active)->toBeTrue();
});

it('rejects login with invalid credentials', function () {
    // Act - Test API login endpoint with invalid credentials
    $response = $this->postJson('/api/v1/users/login', [
        'username' => 'nonexistent_user',
        'password' => 'wrongpassword',
    ]);

    // Assert - Should return 401 unauthorized
    $response->assertStatus(401)
        ->assertJson([
            'message' => 'Invalid credentials',
        ]);
});

it('validates user fields correctly', function () {
    // Test required fields
    expect(function () {
        User::create([
            'email' => 'test@example.com',
        ]);
    })->toThrow(\Illuminate\Database\QueryException::class);
});

it('handles user roles properly', function () {
    $roles = ['user', 'admin', 'superadmin'];

    collect($roles)->each(function (string $role) {
        User::factory()->create([
            'name' => ucfirst($role).' User',
            'email' => $role.'_'.Str::uuid().'@example.com',
            'password' => Hash::make('password'),
            'system_role' => $role,
            'is_active' => true,
        ]);
    });

    expect(User::whereIn('system_role', $roles)->count())->toBe(3);
});
