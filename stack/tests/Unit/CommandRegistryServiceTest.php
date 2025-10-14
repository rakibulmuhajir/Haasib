<?php

use App\Models\Command;
use App\Models\Company;
use App\Services\CommandRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up command bus config for testing
    Config::set('command-bus', [
        'invoice.create' => \App\Actions\Invoice\CreateInvoiceAction::class,
        'customer.create' => \App\Actions\Customer\CreateCustomerAction::class,
        'invoice.send' => \App\Actions\Invoice\SendInvoiceAction::class,
    ]);
});

test('synchronizeCommands creates commands from command bus registry', function () {
    $company = Company::factory()->create();
    $service = new CommandRegistryService;

    $service->synchronizeCommands($company);

    expect(Command::where('company_id', $company->id)->count())->toBe(3);

    $commands = Command::where('company_id', $company->id)->get();
    expect($commands->pluck('name')->toArray())->toContain(
        'invoice.create',
        'customer.create',
        'invoice.send'
    );
});

test('synchronizeCommands updates existing commands', function () {
    $company = Company::factory()->create();
    $service = new CommandRegistryService;

    // Create initial command
    Command::create([
        'company_id' => $company->id,
        'name' => 'invoice.create',
        'description' => 'Old description',
        'parameters' => [],
        'required_permissions' => [],
        'is_active' => true,
    ]);

    $service->synchronizeCommands($company);

    $command = Command::where('company_id', $company->id)->where('name', 'invoice.create')->first();
    expect($command->description)->not->toBe('Old description');
    expect($command->description)->toBe('Create invoice');
});

test('getAvailableCommands returns active commands for company', function () {
    $company = Company::factory()->create();
    $service = new CommandRegistryService;

    $activeCommand = Command::factory()->create([
        'company_id' => $company->id,
        'name' => 'test.command.active',
        'is_active' => true,
    ]);

    $inactiveCommand = Command::factory()->create([
        'company_id' => $company->id,
        'name' => 'test.command.inactive',
        'is_active' => false,
    ]);

    $commands = $service->getAvailableCommands($company);

    expect($commands)->toHaveCount(1);
    expect($commands->first()->id)->toBe($activeCommand->id);
});

test('getCommandByName returns command when it exists', function () {
    $company = Company::factory()->create();
    $service = new CommandRegistryService;

    $command = Command::factory()->create([
        'company_id' => $company->id,
        'name' => 'test.command',
    ]);

    $foundCommand = $service->getCommandByName($company, 'test.command');

    expect($foundCommand)->not->toBeNull();
    expect($foundCommand->id)->toBe($command->id);
});

test('getCommandByName returns null when command does not exist', function () {
    $company = Company::factory()->create();
    $service = new CommandRegistryService;

    $foundCommand = $service->getCommandByName($company, 'nonexistent.command');

    expect($foundCommand)->toBeNull();
});

test('validateCommandSchema returns empty array for valid data', function () {
    $service = new CommandRegistryService;

    $validData = [
        'name' => 'test.command',
        'description' => 'Test command description',
        'parameters' => ['param1' => ['type' => 'string', 'required' => true]],
        'required_permissions' => ['manage invoices'],
    ];

    $errors = $service->validateCommandSchema($validData);

    expect($errors)->toBeArray();
    expect($errors)->toBeEmpty();
});

test('validateCommandSchema returns errors for invalid data', function () {
    $service = new CommandRegistryService;

    $invalidData = [
        'name' => '', // Empty name
        'description' => '', // Empty description
        'parameters' => 'not_an_array', // Not an array
        'required_permissions' => 'not_an_array', // Not an array
    ];

    $errors = $service->validateCommandSchema($invalidData);

    expect($errors)->toHaveCount(4);
    expect($errors[0])->toBe('Command name is required');
    expect($errors[1])->toBe('Command description is required');
    expect($errors[2])->toBe('Parameters must be an array');
    expect($errors[3])->toBe('Required permissions must be an array');
});

test('service clears cache when synchronizing commands', function () {
    $company = Company::factory()->create();
    $service = new CommandRegistryService;

    Cache::shouldReceive('forget')->once()->with('command_registry');
    Log::shouldReceive('info')->once();

    $service->synchronizeCommands($company);
});

test('generateCommandMetadata returns default metadata for nonexistent class', function () {
    $service = new CommandRegistryService;

    // Use reflection to access private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('generateCommandMetadata');
    $method->setAccessible(true);

    $metadata = $method->invoke($service, 'nonexistent.command', 'NonexistentClass');

    expect($metadata['description'])->toBe('Execute nonexistent');
    expect($metadata['parameters'])->toBeArray();
    expect($metadata['permissions'])->toBeArray();
});

test('extractParametersFromMethod correctly extracts method parameters', function () {
    $service = new CommandRegistryService;

    // Use reflection to access private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('extractParametersFromMethod');
    $method->setAccessible(true);

    // Create a mock class with a handle method for testing
    $mockClass = new class
    {
        public function handle(string $param1, int $param2, bool $param3 = true)
        {
            return null;
        }
    };

    $reflectionMethod = new ReflectionMethod($mockClass, 'handle');
    $parameters = $method->invoke($service, $reflectionMethod);

    expect($parameters)->toHaveCount(3);
    expect($parameters[0]['name'])->toBe('param1');
    expect($parameters[0]['type'])->toBe('string');
    expect($parameters[0]['required'])->toBeTrue();

    expect($parameters[1]['name'])->toBe('param2');
    expect($parameters[1]['type'])->toBe('int');
    expect($parameters[1]['required'])->toBeTrue();

    expect($parameters[2]['name'])->toBe('param3');
    expect($parameters[2]['type'])->toBe('bool');
    expect($parameters[2]['required'])->toBeFalse();
    expect($parameters[2]['default'])->toBeTrue();
});

test('inferPermissionsFromClass returns appropriate permissions based on command name', function () {
    $service = new CommandRegistryService;

    // Use reflection to access private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('inferPermissionsFromClass');
    $method->setAccessible(true);

    $permissions = $method->invoke($service, 'UserCreateAction');
    expect($permissions)->toContain('manage users');

    $permissions = $method->invoke($service, 'CustomerUpdateAction');
    expect($permissions)->toContain('manage customers');

    $permissions = $method->invoke($service, 'InvoicePostAction');
    expect($permissions)->toContain('manage invoices');

    $permissions = $method->invoke($service, 'UnknownAction');
    expect($permissions)->toBeArray();
    expect($permissions)->toBeEmpty();
});

test('generateDescription creates readable description from command name', function () {
    $service = new CommandRegistryService;

    // Use reflection to access private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('generateDescription');
    $method->setAccessible(true);

    $description = $method->invoke($service, 'customer.create');
    expect($description)->toBe('Create customer');

    $description = $method->invoke($service, 'invoice.send');
    expect($description)->toBe('Send invoice');

    $description = $method->invoke($service, 'unknown.action');
    expect($description)->toBe('Execute unknown');
});
