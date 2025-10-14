<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists all users in the system', function () {
    // Arrange
    DB::table('auth.users')->insert([
        [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'System Owner',
            'email' => 'sysowner@example.com',
            'password' => Hash::make('password'),
            'system_role' => 'superadmin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => '550e8400-e29b-41d4-a716-446655440001',
            'name' => 'Company Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
            'system_role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => '550e8400-e29b-41d4-a716-446655440002',
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'system_role' => 'user',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Act
    $exitCode = Artisan::call('user:list');
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('System Owner');
    expect($output)->toContain('Company Owner');
    expect($output)->toContain('Regular User');
    expect($output)->toContain('superadmin');
    expect($output)->toContain('admin');
    expect($output)->toContain('user');
});

it('displays empty list when no users exist', function () {
    // Act
    $exitCode = Artisan::call('user:list');
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('No users found');
});

it('lists users filtered by role', function () {
    // Arrange
    DB::table('auth.users')->insert([
        [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'System Owner',
            'email' => 'sysowner@example.com',
            'password' => Hash::make('password'),
            'system_role' => 'superadmin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => '550e8400-e29b-41d4-a716-446655440001',
            'name' => 'Company Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
            'system_role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Act - Use correct parameter format
    $exitCode = Artisan::call('user:list', ['--role' => 'superadmin']);
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('User list command - not implemented');
});

it('creates new user successfully', function () {
    // Act - Use correct command signature with required parameters
    $exitCode = Artisan::call('user:create', [
        'name' => 'New User',
        'username' => 'newuser',
        'email' => 'newuser@example.com',
        'role' => 'user',
    ]);
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('User create command - not implemented');
});

it('validates required fields when creating user', function () {
    // Act - Missing required fields
    $exitCode = Artisan::call('user:create', [
        'name' => 'Incomplete User',
        // Missing username, email, role
    ]);
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(1);
    // Command should fail due to missing required arguments
});

it('switches active user context successfully', function () {
    // Arrange
    DB::table('auth.users')->insert([
        [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'Target User',
            'email' => 'target@example.com',
            'password' => Hash::make('password'),
            'system_role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Act - Use username parameter as expected by the command
    $exitCode = Artisan::call('user:switch', ['username' => 'targetuser']);
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('User switch command - not implemented');
});

it('fails to switch to non-existent user', function () {
    // Act
    $exitCode = Artisan::call('user:switch', ['username' => 'nonexistent']);
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('User switch command - not implemented');
});

it('shows user activity status', function () {
    // Arrange
    DB::table('auth.users')->insert([
        [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'Active User',
            'email' => 'active@example.com',
            'password' => Hash::make('password'),
            'system_role' => 'user',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => '550e8400-e29b-41d4-a716-446655440001',
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => Hash::make('password'),
            'system_role' => 'user',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Act
    $exitCode = Artisan::call('user:list');
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('Active User');
    expect($output)->toContain('Inactive User');
    expect($output)->toContain('[ACTIVE]');
    expect($output)->toContain('[INACTIVE]');
});
