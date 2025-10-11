<?php

use App\Services\SetupService;
use App\Models\Company;
use App\Models\Module;
use App\Models\User;

covers(SetupService::class);

it('can check if system is initialized', function () {
    // Fresh system should not be initialized
    $setupService = new SetupService();
    expect($setupService->isInitialized())->toBeFalse();

    // Create required data for initialization
    User::factory()->create(['role' => 'system_owner']);
    Company::factory()->count(3)->create();
    Module::factory()->create(['name' => 'Accounting', 'is_active' => true]);

    expect($setupService->isInitialized())->toBeTrue();
});

it('can get initialization status details', function () {
    $setupService = new SetupService();

    // Empty system
    $status = $setupService->getStatus();
    expect($status['initialized'])->toBeFalse();
    expect($status['requirements']['system_owner'])->toBeFalse();
    expect($status['requirements']['companies'])->toBe(0);
    expect($status['requirements']['modules'])->toBe(0);

    // Add system owner
    User::factory()->create(['role' => 'system_owner']);
    $status = $setupService->getStatus();
    expect($status['requirements']['system_owner'])->toBeTrue();

    // Add companies
    Company::factory()->count(2)->create();
    $status = $setupService->getStatus();
    expect($status['requirements']['companies'])->toBe(2);

    // Add modules
    Module::factory()->count(1)->create(['is_active' => true]);
    $status = $setupService->getStatus();
    expect($status['requirements']['modules'])->toBe(1);
});

it('can initialize the system', function () {
    $setupService = new SetupService();
    
    $userData = [
        'name' => 'System Owner',
        'email' => 'admin@example.com',
        'username' => 'admin',
        'password' => 'password123',
    ];

    $companyData = [
        ['name' => 'Company 1', 'industry' => 'technology', 'base_currency' => 'USD'],
        ['name' => 'Company 2', 'industry' => 'retail', 'base_currency' => 'USD'],
    ];

    $result = $setupService->initialize($userData, $companyData);

    expect($result['success'])->toBeTrue();
    expect($result['system_owner']['email'])->toBe('admin@example.com');
    expect($result['companies_created'])->toBe(2);
    expect($result['modules_enabled'])->toBeGreaterThan(0);

    // Verify system is now initialized
    expect($setupService->isInitialized())->toBeTrue();

    // Verify created data
    expect(User::where('email', 'admin@example.com')->exists())->toBeTrue();
    expect(Company::count())->toBe(2);
});

it('throws exception when initializing already initialized system', function () {
    // Pre-populate system
    User::factory()->create(['role' => 'system_owner']);
    Company::factory()->create();
    Module::factory()->create(['is_active' => true]);

    $setupService = new SetupService();

    expect(fn () => $setupService->initialize(
        ['name' => 'Admin', 'email' => 'admin@example.com', 'username' => 'admin', 'password' => 'password'],
        [['name' => 'Company', 'industry' => 'tech', 'base_currency' => 'USD']]
    ))->toThrow(\Exception::class, 'System is already initialized');
});

it('validates required user data during initialization', function () {
    $setupService = new SetupService();

    expect(fn () => $setupService->initialize(
        ['name' => '', 'email' => 'invalid', 'username' => 'admin', 'password' => ''],
        []
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('creates default accounting module during initialization', function () {
    $setupService = new SetupService();
    
    $userData = [
        'name' => 'System Owner',
        'email' => 'admin@example.com',
        'username' => 'admin',
        'password' => 'password123',
    ];

    $companyData = [
        ['name' => 'Test Company', 'industry' => 'technology', 'base_currency' => 'USD'],
    ];

    $setupService->initialize($userData, $companyData);

    expect(Module::where('name', 'Accounting')->exists())->toBeTrue();
    
    $accountingModule = Module::where('name', 'Accounting')->first();
    expect($accountingModule->is_enabled)->toBeTrue();
    expect($accountingModule->version)->toBe('1.0.0');
});

it('handles initialization transaction atomically', function () {
    // Mock to throw exception during company creation
    $this->mock(\Illuminate\Database\ConnectionInterface::class)
        ->shouldReceive('transaction')
        ->once()
        ->andThrow(new \Exception('Database error'));

    $setupService = new SetupService();

    $result = $setupService->initialize(
        ['name' => 'Admin', 'email' => 'admin@example.com', 'username' => 'admin', 'password' => 'password'],
        [['name' => 'Company', 'industry' => 'tech', 'base_currency' => 'USD']]
    );

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBe('Database error');

    // Ensure no data was partially created
    expect(User::count())->toBe(0);
    expect(Company::count())->toBe(0);
});