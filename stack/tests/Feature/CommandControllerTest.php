<?php

use App\Models\Command;
use App\Models\CommandTemplate;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up a user and company for testing
    $this->user = User::factory()->create();
    $this->company = Company::factory()->create();
    $this->company->users()->attach($this->user->id, ['role' => 'owner', 'is_active' => true]);

    // Set session for authentication
    Session::start();
    Session::put('auth.id', $this->user->id);
    Session::put('active_company_id', $this->company->id);
});

test('GET /api/commands returns available commands for authenticated user', function () {
    // Create commands for the company
    Command::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'test.command',
        'required_permissions' => ['manage invoices'],
        'is_active' => true,
    ]);

    $response = $this->withSession([
        'auth.id' => $this->user->id,
        'active_company_id' => $this->company->id,
    ])->get('/api/commands');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'parameters',
                    'required_permissions',
                    'category',
                ],
            ],
            'meta' => [
                'total',
                'categories',
            ],
        ]);
});

test('GET /api/commands filters by category', function () {
    Command::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'invoice.create',
        'is_active' => true,
    ]);

    Command::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'customer.create',
        'is_active' => true,
    ]);

    $response = $this->withSession([
        'auth.id' => $this->user->id,
        'active_company_id' => $this->company->id,
    ])->get('/api/commands?category=invoice');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['name'])->toBe('invoice.create');
});

test('GET /api/commands filters by search', function () {
    Command::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'invoice.create',
        'description' => 'Create a new invoice',
        'is_active' => true,
    ]);

    Command::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'customer.create',
        'description' => 'Create a new customer',
        'is_active' => true,
    ]);

    $response = $this->withSession([
        'auth.id' => $this->user->id,
        'active_company_id' => $this->company->id,
    ])->get('/api/commands?search=invoice');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['name'])->toBe('invoice.create');
});

test('GET /api/commands/suggestions returns contextual suggestions', function () {
    Command::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'invoice.create',
        'description' => 'Create a new invoice',
        'is_active' => true,
    ]);

    $response = $this->withSession([
        'auth.id' => $this->user->id,
        'active_company_id' => $this->company->id,
    ])->post('/api/commands/suggestions', [
        'input' => 'invoice',
        'context' => ['page' => 'dashboard'],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'confidence',
                    'match_type',
                ],
            ],
            'meta' => [
                'input',
                'count',
            ],
        ]);
});

test('POST /api/commands/execute executes valid command', function () {
    Command::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'test.command',
        'is_active' => true,
    ]);

    $response = $this->withSession([
        'auth.id' => $this->user->id,
        'active_company_id' => $this->company->id,
    ])->post('/api/commands/execute', [
        'command_name' => 'test.command',
        'parameters' => ['param1' => 'value1'],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'execution_id',
            'audit_reference',
        ]);
});

test('POST /api/commands/execute returns error for invalid command', function () {
    $response = $this->withSession([
        'auth.id' => $this->user->id,
        'active_company_id' => $this->company->id,
    ])->post('/api/commands/execute', [
        'command_name' => 'nonexistent.command',
        'parameters' => [],
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'success',
            'error',
        ]);
});

test('POST /api/commands/execute handles idempotency', function () {
    Command::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'test.command',
        'is_active' => true,
    ]);

    $idempotencyKey = 'test-idempotency-key';

    $response1 = $this->withSession([
        'auth.id' => $this->user->id,
        'active_company_id' => $this->company->id,
    ])->withHeaders([
        'Idempotency-Key' => $idempotencyKey,
    ])->post('/api/commands/execute', [
        'command_name' => 'test.command',
        'parameters' => [],
    ]);

    $response1->assertStatus(200);

    // Second request with same idempotency key should return existing result
    $response2 = $this->withSession([
        'auth.id' => $this->user->id,
        'active_company_id' => $this->company->id,
    ])->withHeaders([
        'Idempotency-Key' => $idempotencyKey,
    ])->post('/api/commands/execute', [
        'command_name' => 'test.command',
        'parameters' => [],
    ]);

    $response2->assertStatus(200)
        ->assertJson([
            'success' => true,
            'idempotency_collision' => true,
        ]);
});

test('GET /api/commands/history returns user command history', function () {
    // Create command history
    $command = Command::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'test.command',
        'is_active' => true,
    ]);

    $this->user->commandHistory()->create([
        'command_id' => $command->id,
        'company_id' => $this->company->id,
        'executed_at' => now()->subHour(),
        'input_text' => 'test command',
        'parameters_used' => ['param1' => 'value1'],
        'execution_status' => 'success',
        'result_summary' => 'Command executed successfully',
    ]);

    $response = $this->withSession([
        'auth.id' => $this->user->id,
        'active_company_id' => $this->company->id,
    ])->get('/api/commands/history');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'command_id',
                    'executed_at',
                    'input_text',
                    'parameters_used',
                    'execution_status',
                    'result_summary',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
});

test('GET /api/commands/templates returns user templates', function () {
    $command = Command::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'test.command',
        'is_active' => true,
    ]);

    // Create a template
    CommandTemplate::factory()->create([
        'command_id' => $command->id,
        'user_id' => $this->user->id,
        'company_id' => $this->company->id,
        'name' => 'Test Template',
        'parameter_values' => ['param1' => 'default1'],
        'is_shared' => false,
    ]);

    $response = $this->withSession([
        'auth.id' => $this->user->id,
        'active_company_id' => $this->company->id,
    ])->get('/api/commands/templates');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'command_id',
                    'command_name',
                    'parameter_values',
                    'is_shared',
                    'created_by',
                    'created_at',
                ],
            ],
        ]);
});

test('POST /api/commands/templates creates new template', function () {
    $command = Command::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'test.command',
        'is_active' => true,
    ]);

    $response = $this->withSession([
        'auth.id' => $this->user->id,
        'active_company_id' => $this->company->id,
    ])->post('/api/commands/templates', [
        'command_id' => $command->id,
        'name' => 'New Template',
        'parameter_values' => ['param1' => 'default1'],
        'is_shared' => false,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'command_id',
                'parameter_values',
                'is_shared',
                'created_at',
            ],
        ]);

    $this->assertDatabaseHas('command_templates', [
        'name' => 'New Template',
        'user_id' => $this->user->id,
        'company_id' => $this->company->id,
        'command_id' => $command->id,
    ]);
});

test('PUT /api/commands/templates/{id} updates existing template', function () {
    $command = Command::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'test.command',
        'is_active' => true,
    ]);

    $template = CommandTemplate::factory()->create([
        'command_id' => $command->id,
        'user_id' => $this->user->id,
        'company_id' => $this->company->id,
        'name' => 'Original Template',
    ]);

    $response = $this->withSession([
        'auth.id' => $this->user->id,
        'active_company_id' => $this->company->id,
    ])->put("/api/commands/templates/{$template->id}", [
        'name' => 'Updated Template',
        'parameter_values' => ['param1' => 'updated1'],
        'is_shared' => true,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Template');

    $this->assertDatabaseHas('command_templates', [
        'id' => $template->id,
        'name' => 'Updated Template',
        'is_shared' => true,
    ]);
});

test('DELETE /api/commands/templates/{id} deletes template', function () {
    $command = Command::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'test.command',
        'is_active' => true,
    ]);

    $template = CommandTemplate::factory()->create([
        'command_id' => $command->id,
        'user_id' => $this->user->id,
        'company_id' => $this->company->id,
        'name' => 'Template to Delete',
    ]);

    $response = $this->withSession([
        'auth.id' => $this->user->id,
        'active_company_id' => $this->company->id,
    ])->delete("/api/commands/templates/{$template->id}");

    $response->assertStatus(204);

    $this->assertDatabaseMissing('command_templates', [
        'id' => $template->id,
    ]);
});

test('Unauthorized access returns 401 for all endpoints', function () {
    $endpoints = [
        ['GET', '/api/commands'],
        ['GET', '/api/commands/suggestions'],
        ['POST', '/api/commands/execute'],
        ['GET', '/api/commands/history'],
        ['GET', '/api/commands/templates'],
    ];

    foreach ($endpoints as [$method, $endpoint]) {
        $response = $this->json($method, $endpoint);
        $response->assertStatus(401);
    }
});

test('Endpoints require company context', function () {
    // Remove company session
    $response = $this->withSession([
        'auth.id' => $this->user->id,
    ])->get('/api/commands');

    $response->assertStatus(403)
        ->assertJson([
            'error' => 'No company context found',
        ]);
});
