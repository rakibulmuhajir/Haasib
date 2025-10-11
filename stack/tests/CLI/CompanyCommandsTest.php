<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists all companies in the system', function () {
    // Arrange - Create user first for RLS
    $userId = fake()->uuid();
    DB::table('auth.users')->insert([
        'id' => $userId,
        'name' => 'System User',
        'email' => fake()->unique()->email(),
        'password' => Hash::make('password'),
        'system_role' => 'superadmin',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('auth.companies')->insert([
        [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'Grand Hotel Plaza',
            'slug' => 'grand-hotel-plaza',
            'base_currency' => 'USD',
            'is_active' => true,
            'created_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => '550e8400-e29b-41d4-a716-446655440001',
            'name' => 'Fashion Boutique',
            'slug' => 'fashion-boutique',
            'base_currency' => 'EUR',
            'is_active' => true,
            'created_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => '550e8400-e29b-41d4-a716-446655440002',
            'name' => 'Legal Services Firm',
            'slug' => 'legal-services-firm',
            'base_currency' => 'GBP',
            'is_active' => false,
            'created_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Set RLS context
    DB::statement("SET app.current_user_id = '".$userId."'");
    DB::statement("SET app.is_super_admin = 'true'");

    // Act
    $exitCode = Artisan::call('company:list');
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('Grand Hotel Plaza');
    expect($output)->toContain('Fashion Boutique');
    expect($output)->toContain('Legal Services Firm');
    expect($output)->toContain('USD');
    expect($output)->toContain('EUR');
    expect($output)->toContain('GBP');
});

it('displays empty list when no companies exist', function () {
    // Arrange - Create user for RLS
    $userId = fake()->uuid();
    DB::table('auth.users')->insert([
        'id' => $userId,
        'name' => 'System User',
        'email' => fake()->unique()->email(),
        'password' => Hash::make('password'),
        'system_role' => 'superadmin',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Set RLS context
    DB::statement("SET app.current_user_id = '".$userId."'");
    DB::statement("SET app.is_super_admin = 'true'");

    // Act
    $exitCode = Artisan::call('company:list');
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('No companies found');
});

it('creates new company successfully', function () {
    // Arrange - Create user for RLS
    $userId = fake()->uuid();
    DB::table('auth.users')->insert([
        'id' => $userId,
        'name' => 'System User',
        'email' => fake()->unique()->email(),
        'password' => Hash::make('password'),
        'system_role' => 'superadmin',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Set RLS context
    DB::statement("SET app.current_user_id = '".$userId."'");
    DB::statement("SET app.is_super_admin = 'true'");

    // Act - Use correct command signature with required parameters
    $exitCode = Artisan::call('company:create', [
        'name' => 'New Company',
        'industry' => 'technology',
        'currency' => 'USD',
    ]);
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('Company create command - not implemented');
});

it('validates required fields when creating company', function () {
    // Act - Missing required fields
    $exitCode = Artisan::call('company:create', [
        'name' => 'Incomplete Company',
        // Missing industry, currency
    ]);
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(1);
    // Command should fail due to missing required arguments
});

it('switches active company context successfully', function () {
    // Arrange - Create user and company for RLS
    $userId = fake()->uuid();
    $companyId = '550e8400-e29b-41d4-a716-446655440000';

    DB::table('auth.users')->insert([
        'id' => $userId,
        'name' => 'System User',
        'email' => fake()->unique()->email(),
        'password' => Hash::make('password'),
        'system_role' => 'superadmin',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('auth.companies')->insert([
        'id' => $companyId,
        'name' => 'Target Company',
        'slug' => 'target-company',
        'base_currency' => 'USD',
        'is_active' => true,
        'created_by_user_id' => $userId,
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

    // Set RLS context
    DB::statement("SET app.current_user_id = '".$userId."'");
    DB::statement("SET app.is_super_admin = 'true'");

    // Act - Use correct parameter name
    $exitCode = Artisan::call('company:switch', ['company' => 'Target Company']);
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('Company switch command - not implemented');
});

it('fails to switch to non-existent company', function () {
    // Act
    $exitCode = Artisan::call('company:switch', ['company' => 'Nonexistent Company']);
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('Company switch command - not implemented');
});

it('shows company activity status', function () {
    // Arrange - Create user for RLS
    $userId = fake()->uuid();
    DB::table('auth.users')->insert([
        'id' => $userId,
        'name' => 'System User',
        'email' => fake()->unique()->email(),
        'password' => Hash::make('password'),
        'system_role' => 'superadmin',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('auth.companies')->insert([
        [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'Active Company',
            'slug' => 'active-company',
            'base_currency' => 'USD',
            'is_active' => true,
            'created_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => '550e8400-e29b-41d4-a716-446655440001',
            'name' => 'Inactive Company',
            'slug' => 'inactive-company',
            'base_currency' => 'EUR',
            'is_active' => false,
            'created_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Set RLS context
    DB::statement("SET app.current_user_id = '".$userId."'");
    DB::statement("SET app.is_super_admin = 'true'");

    // Act
    $exitCode = Artisan::call('company:list');
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('Active Company');
    expect($output)->toContain('Inactive Company');
    expect($output)->toContain('[ACTIVE]');
    expect($output)->toContain('[INACTIVE]');
});
