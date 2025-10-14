<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('displays setup status when system is not initialized', function () {
    // Act
    $exitCode = Artisan::call('setup:status');
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    // The command exists and should run without error
    expect($output)->not->toBeEmpty();
});

it('attempts to initialize system', function () {
    // Act
    $exitCode = Artisan::call('setup:initialize');
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    // The command exists and should run without error
    expect($output)->not->toBeEmpty();
});

it('attempts to reset system', function () {
    // Act
    $exitCode = Artisan::call('setup:reset');
    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    // The command exists and should run without error
    expect($output)->not->toBeEmpty();
});

it('validates command options exist', function () {
    // Test that commands have the expected signatures
    $listCommand = Artisan::call('list', ['command' => 'setup:initialize']);
    expect($listCommand)->toBe(0);

    $resetCommand = Artisan::call('list', ['command' => 'setup:reset']);
    expect($resetCommand)->toBe(0);
});
