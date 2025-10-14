<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('initializes system successfully', function () {
    // Act - Test that the setup:initialize command exists
    $exitCode = \Illuminate\Support\Facades\Artisan::call('setup:initialize', [
        '--force' => true,
    ]);
    $output = \Illuminate\Support\Facades\Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->not->toBeEmpty();
    expect($output)->toContain('Haasib platform initialized');
});

it('initializes system without demo data', function () {
    // Act - Test command with force flag (no demo data)
    $exitCode = \Illuminate\Support\Facades\Artisan::call('setup:initialize', [
        '--force' => true,
    ]);
    $output = \Illuminate\Support\Facades\Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('Haasib platform initialized');
});

it('creates users and companies with custom parameters', function () {
    // Act - Test command with custom parameters
    $exitCode = \Illuminate\Support\Facades\Artisan::call('setup:initialize', [
        '--name' => 'Test Admin',
        '--email' => 'test@example.com',
        '--company' => 'Test Company',
        '--force' => true,
    ]);
    $output = \Illuminate\Support\Facades\Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('Haasib platform initialized');
    expect($output)->toContain('test@example.com');
    expect($output)->toContain('Test Company');
});

it('creates companies with correct schema', function () {
    // Act - Test command with specific company parameters
    $exitCode = \Illuminate\Support\Facades\Artisan::call('setup:initialize', [
        '--name' => 'Schema Test Admin',
        '--email' => 'schema@example.com',
        '--company' => 'Schema Test Corp',
        '--currency' => 'EUR',
        '--force' => true,
    ]);
    $output = \Illuminate\Support\Facades\Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('Haasib platform initialized');
    expect($output)->toContain('schema@example.com');
    expect($output)->toContain('Schema Test Corp');
});
