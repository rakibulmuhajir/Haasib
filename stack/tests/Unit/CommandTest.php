<?php

use App\Models\Command;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('command can be created with valid data', function () {
    $company = Company::factory()->create();

    $command = Command::create([
        'company_id' => $company->id,
        'name' => 'test.command',
        'description' => 'Test command description',
        'parameters' => ['param1' => ['type' => 'string', 'required' => true]],
        'required_permissions' => ['manage invoices'],
        'is_active' => true,
    ]);

    expect($command->id)->not->toBeNull();
    expect($command->company_id)->toBe($company->id);
    expect($command->name)->toBe('test.command');
    expect($command->description)->toBe('Test command description');
    expect($command->parameters)->toBe(['param1' => ['type' => 'string', 'required' => true]]);
    expect($command->required_permissions)->toBe(['manage invoices']);
    expect($command->is_active)->toBeTrue();
});

test('command belongs to company', function () {
    $company = Company::factory()->create();
    $command = Command::factory()->create(['company_id' => $company->id]);

    expect($command->company)->toBeInstanceOf(Company::class);
    expect($command->company->id)->toBe($company->id);
});

test('command has many command executions', function () {
    $command = Command::factory()->create();
    $execution1 = $command->commandExecutions()->create([
        'user_id' => User::factory()->create()->id,
        'company_id' => $command->company_id,
        'idempotency_key' => 'test-key-1',
        'status' => 'completed',
        'started_at' => now(),
        'completed_at' => now(),
        'parameters' => [],
        'result' => ['success' => true],
    ]);

    $execution2 = $command->commandExecutions()->create([
        'user_id' => User::factory()->create()->id,
        'company_id' => $command->company_id,
        'idempotency_key' => 'test-key-2',
        'status' => 'pending',
        'parameters' => [],
    ]);

    expect($command->commandExecutions)->toHaveCount(2);
    expect($command->commandExecutions->first()->id)->toBe($execution1->id);
});

test('command has many command history entries', function () {
    $command = Command::factory()->create();
    $user = User::factory()->create();

    $history1 = $command->commandHistory()->create([
        'user_id' => $user->id,
        'company_id' => $command->company_id,
        'executed_at' => now()->subDay(),
        'input_text' => 'test command input',
        'parameters_used' => ['param1' => 'value1'],
        'execution_status' => 'success',
        'result_summary' => 'Command executed successfully',
    ]);

    $history2 = $command->commandHistory()->create([
        'user_id' => $user->id,
        'company_id' => $command->company_id,
        'executed_at' => now()->subDays(2),
        'input_text' => 'another test input',
        'parameters_used' => ['param1' => 'value2'],
        'execution_status' => 'failed',
        'result_summary' => 'Command failed',
    ]);

    expect($command->commandHistory)->toHaveCount(2);
    expect($command->commandHistory->first()->id)->toBe($history1->id);
});

test('command has many command templates', function () {
    $command = Command::factory()->create();
    $user = User::factory()->create();

    $template1 = $command->commandTemplates()->create([
        'user_id' => $user->id,
        'company_id' => $command->company_id,
        'name' => 'Test Template 1',
        'parameter_values' => ['param1' => 'default1'],
        'is_shared' => true,
    ]);

    $template2 = $command->commandTemplates()->create([
        'user_id' => $user->id,
        'company_id' => $command->company_id,
        'name' => 'Test Template 2',
        'parameter_values' => ['param1' => 'default2'],
        'is_shared' => false,
    ]);

    expect($command->commandTemplates)->toHaveCount(2);
    expect($command->commandTemplates->first()->id)->toBe($template1->id);
});

test('active scope returns only active commands', function () {
    $company = Company::factory()->create();

    $activeCommand = Command::factory()->create([
        'company_id' => $company->id,
        'is_active' => true,
    ]);

    $inactiveCommand = Command::factory()->create([
        'company_id' => $company->id,
        'is_active' => false,
    ]);

    $activeCommands = Command::active()->get();

    expect($activeCommands)->toHaveCount(1);
    expect($activeCommands->first()->id)->toBe($activeCommand->id);
});

test('forCompany scope returns only commands for specific company', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();

    $command1 = Command::factory()->create(['company_id' => $company1->id]);
    $command2 = Command::factory()->create(['company_id' => $company2->id]);

    $company1Commands = Command::forCompany($company1->id)->get();

    expect($company1Commands)->toHaveCount(1);
    expect($company1Commands->first()->id)->toBe($command1->id);
});

test('userHasPermission returns true when user has required permissions', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    // Simulate user having the required permission
    $user->permissions = ['manage invoices', 'view reports'];

    $command = Command::factory()->create([
        'company_id' => $company->id,
        'required_permissions' => ['manage invoices'],
    ]);

    // Mock the hasAnyPermission method
    $command->userHasPermission($user)->willReturn(true);
});

test('userHasPermission returns false when user lacks required permissions', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    // Simulate user lacking the required permission
    $user->permissions = ['view reports'];

    $command = Command::factory()->create([
        'company_id' => $company->id,
        'required_permissions' => ['manage invoices'],
    ]);

    // Mock the hasAnyPermission method to return false
    $command->userHasPermission($user)->willReturn(false);
});

test('command casts JSON fields correctly', function () {
    $company = Company::factory()->create();

    $parameters = [
        'customer_id' => ['type' => 'uuid', 'required' => true],
        'amount' => ['type' => 'decimal', 'required' => true, 'min' => 0],
    ];

    $permissions = ['manage invoices', 'create payments'];

    $command = Command::create([
        'company_id' => $company->id,
        'name' => 'invoice.create',
        'description' => 'Create a new invoice',
        'parameters' => $parameters,
        'required_permissions' => $permissions,
        'is_active' => true,
    ]);

    expect($command->parameters)->toBeArray();
    expect($command->parameters)->toEqual($parameters);
    expect($command->required_permissions)->toBeArray();
    expect($command->required_permissions)->toEqual($permissions);
    expect($command->is_active)->toBeBool();
});
