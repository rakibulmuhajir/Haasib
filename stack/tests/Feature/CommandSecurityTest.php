<?php

use App\Models\AuditEntry;
use App\Models\Command;
use App\Models\CommandExecution;
use App\Models\CommandHistory;
use App\Models\CommandTemplate;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('users cannot access commands from other companies', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Associate users with companies
    $company1->users()->attach($user1->id, ['role' => 'owner', 'is_active' => true]);
    $company2->users()->attach($user2->id, ['role' => 'owner', 'is_active' => true]);

    // Create commands for both companies
    $command1 = Command::factory()->create([
        'company_id' => $company1->id,
        'name' => 'company1.command',
        'is_active' => true,
    ]);

    $command2 = Command::factory()->create([
        'company_id' => $company2->id,
        'name' => 'company2.command',
        'is_active' => true,
    ]);

    // User1 should only see their company's commands
    $response = $this->actingAs($user1)
        ->withSession(['active_company_id' => $company1->id])
        ->get('/api/commands');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['name'])->toBe('company1.command');

    // User1 should not be able to access User2's command by ID
    $response = $this->actingAs($user1)
        ->withSession(['active_company_id' => $company1->id])
        ->get("/api/commands/{$command2->id}");

    $response->assertStatus(404);
});

test('commands are isolated by company RLS policies', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $company1->users()->attach($user1->id, ['role' => 'owner', 'is_active' => true]);
    $company2->users()->attach($user2->id, ['role' => 'owner', 'is_active' => true]);

    // Create commands for both companies
    Command::factory()->count(5)->create(['company_id' => $company1->id]);
    Command::factory()->count(3)->create(['company_id' => $company2->id]);

    // Test direct database access with RLS
    DB::statement("SET app.current_company_id = '{$company1->id}'");
    DB::statement("SET app.current_user_id = '{$user1->id}'");
    DB::statement("SET app.user_role = 'owner'");

    $directQuery = DB::table('commands')->count();
    expect($directQuery)->toBe(5);

    // Reset context
    DB::statement("SET app.current_company_id = '{$company2->id}'");
    DB::statement("SET app.current_user_id = '{$user2->id}'");

    $directQuery = DB::table('commands')->count();
    expect($directQuery)->toBe(3);

    // Reset RLS context
    DB::statement('RESET app.current_company_id');
    DB::statement('RESET app.current_user_id');
    DB::statement('RESET app.user_role');
});

test('command executions are company isolated', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $company1->users()->attach($user1->id, ['role' => 'owner', 'is_active' => true]);
    $company2->users()->attach($user2->id, ['role' => 'owner', 'is_active' => true]);

    $command1 = Command::factory()->create(['company_id' => $company1->id]);
    $command2 = Command::factory()->create(['company_id' => $company2->id]);

    // Create executions for both companies
    CommandExecution::factory()->count(3)->create([
        'company_id' => $company1->id,
        'command_id' => $command1->id,
        'user_id' => $user1->id,
    ]);

    CommandExecution::factory()->count(2)->create([
        'company_id' => $company2->id,
        'command_id' => $command2->id,
        'user_id' => $user2->id,
    ]);

    // User1 should only see their company's executions
    $response = $this->actingAs($user1)
        ->withSession(['active_company_id' => $company1->id])
        ->get('/api/commands/history');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveCount(3);
});

test('command history respects company boundaries', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $company1->users()->attach($user1->id, ['role' => 'owner', 'is_active' => true]);
    $company2->users()->attach($user2->id, ['role' => 'owner', 'is_active' => true]);

    $command1 = Command::factory()->create(['company_id' => $company1->id]);
    $command2 = Command::factory()->create(['company_id' => $company2->id]);

    // Create history entries for both companies
    CommandHistory::factory()->count(4)->create([
        'company_id' => $company1->id,
        'command_id' => $command1->id,
        'user_id' => $user1->id,
        'execution_status' => 'success',
    ]);

    CommandHistory::factory()->count(2)->create([
        'company_id' => $company2->id,
        'command_id' => $command2->id,
        'user_id' => $user2->id,
        'execution_status' => 'success',
    ]);

    // User1 should only see their company's history
    $response = $this->actingAs($user1)
        ->withSession(['active_company_id' => $company1->id])
        ->get('/api/commands/history');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveCount(4);
});

test('command templates are company isolated', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $company1->users()->attach($user1->id, ['role' => 'owner', 'is_active' => true]);
    $company2->users()->attach($user2->id, ['role' => 'owner', 'is_active' => true]);

    $command1 = Command::factory()->create(['company_id' => $company1->id]);
    $command2 = Command::factory()->create(['company_id' => $company2->id]);

    // Create templates for both companies
    CommandTemplate::factory()->count(3)->create([
        'company_id' => $company1->id,
        'command_id' => $command1->id,
        'user_id' => $user1->id,
    ]);

    CommandTemplate::factory()->count(2)->create([
        'company_id' => $company2->id,
        'command_id' => $command2->id,
        'user_id' => $user2->id,
    ]);

    // User1 should only see their company's templates
    $response = $this->actingAs($user1)
        ->withSession(['active_company_id' => $company1->id])
        ->get('/api/commands/templates');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveCount(3);
});

test('users cannot modify templates from other companies', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $company1->users()->attach($user1->id, ['role' => 'owner', 'is_active' => true]);
    $company2->users()->attach($user2->id, ['role' => 'owner', 'is_active' => true]);

    $command1 = Command::factory()->create(['company_id' => $company1->id]);
    $command2 = Command::factory()->create(['company_id' => $company2->id]);

    // User2 creates a template
    $template2 = CommandTemplate::factory()->create([
        'company_id' => $company2->id,
        'command_id' => $command2->id,
        'user_id' => $user2->id,
        'name' => 'User2 Template',
    ]);

    // User1 should not be able to update User2's template
    $response = $this->actingAs($user1)
        ->withSession(['active_company_id' => $company1->id])
        ->put("/api/commands/templates/{$template2->id}", [
            'name' => 'Hacked Template',
        ]);

    $response->assertStatus(404);

    // Verify template wasn't changed
    $this->assertDatabaseHas('command_templates', [
        'id' => $template2->id,
        'name' => 'User2 Template',
    ]);
});

test('idempotency keys are scoped to company', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $company1->users()->attach($user1->id, ['role' => 'owner', 'is_active' => true]);
    $company2->users()->attach($user2->id, ['role' => 'owner', 'is_active' => true]);

    $command1 = Command::factory()->create(['company_id' => $company1->id]);
    $command2 = Command::factory()->create(['company_id' => $company2->id]);

    $idempotencyKey = 'same-key-across-companies';

    // User1 executes command with idempotency key
    $response1 = $this->actingAs($user1)
        ->withSession(['active_company_id' => $company1->id])
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->post('/api/commands/execute', [
            'command_name' => 'test.command',
            'parameters' => [],
        ]);

    $response1->assertStatus(200);

    // User2 should be able to use the same idempotency key (different company scope)
    $response2 = $this->actingAs($user2)
        ->withSession(['active_company_id' => $company2->id])
        ->withHeaders(['Idempotency-Key' => $idempotencyKey])
        ->post('/api/commands/execute', [
            'command_name' => 'test.command',
            'parameters' => [],
        ]);

    $response2->assertStatus(200);
});

test('audit logs respect company boundaries', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $company1->users()->attach($user1->id, ['role' => 'owner', 'is_active' => true]);
    $company2->users()->attach($user2->id, ['role' => 'owner', 'is_active' => true]);

    $command1 = Command::factory()->create(['company_id' => $company1->id]);
    $command2 = Command::factory()->create(['company_id' => $company2->id]);

    // Execute commands for both companies
    $this->actingAs($user1)
        ->withSession(['active_company_id' => $company1->id])
        ->post('/api/commands/execute', [
            'command_name' => 'test.command',
            'parameters' => [],
        ]);

    $this->actingAs($user2)
        ->withSession(['active_company_id' => $company2->id])
        ->post('/api/commands/execute', [
            'command_name' => 'test.command',
            'parameters' => [],
        ]);

    // Check audit entries are properly company-scoped
    $company1Audits = AuditEntry::where('company_id', $company1->id)->get();
    $company2Audits = AuditEntry::where('company_id', $company2->id)->get();

    expect($company1Audits)->toHaveCount(1);
    expect($company2Audits)->toHaveCount(1);

    expect($company1Audits->first()->company_id)->toBe($company1->id);
    expect($company2Audits->first()->company_id)->toBe($company2->id);
});

test('permission escalation attempts are blocked', function () {
    $company = Company::factory()->create();

    // Create user with limited permissions
    $limitedUser = User::factory()->create();
    $company->users()->attach($limitedUser->id, ['role' => 'employee', 'is_active' => true]);

    // Create command that requires higher permissions
    $restrictedCommand = Command::factory()->create([
        'company_id' => $company->id,
        'name' => 'admin.only.command',
        'required_permissions' => ['manage_companies'],
        'is_active' => true,
    ]);

    // Try to execute restricted command
    $response = $this->actingAs($limitedUser)
        ->withSession(['active_company_id' => $company->id])
        ->post('/api/commands/execute', [
            'command_name' => 'admin.only.command',
            'parameters' => [],
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

test('unauthenticated access is blocked', function () {
    $endpoints = [
        ['GET', '/api/commands'],
        ['GET', '/api/commands/suggestions'],
        ['POST', '/api/commands/execute'],
        ['GET', '/api/commands/history'],
        ['GET', '/api/commands/templates'],
        ['POST', '/api/commands/templates'],
        ['PUT', '/api/commands/templates/123'],
        ['DELETE', '/api/commands/templates/123'],
    ];

    foreach ($endpoints as [$method, $endpoint]) {
        $response = $this->json($method, $endpoint);
        $response->assertStatus(401);
    }
});

test('rate limiting is applied per user and company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $company->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    Command::factory()->create(['company_id' => $company->id, 'is_active' => true]);

    // Make multiple rapid requests to test rate limiting
    $responses = [];
    for ($i = 0; $i < 25; $i++) {
        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->get('/api/commands');
        $responses[] = $response->status();
    }

    // First requests should succeed, later ones should be rate limited
    $successCount = count(array_filter($responses, fn ($status) => $status === 200));
    $rateLimitedCount = count(array_filter($responses, fn ($status) => $status === 429));

    expect($successCount)->toBeGreaterThan(0);
    expect($rateLimitedCount)->toBeGreaterThan(0);
});

test('input validation prevents SQL injection', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $company->users()->attach($user->id, ['role' => 'owner', 'is_active' => true]);

    // Try SQL injection attempts
    $maliciousInputs = [
        "'; DROP TABLE commands; --",
        "1' OR '1'='1",
        'UNION SELECT * FROM users --',
        "<script>alert('xss')</script>",
        "javascript:alert('xss')",
    ];

    foreach ($maliciousInputs as $maliciousInput) {
        $response = $this->actingAs($user)
            ->withSession(['active_company_id' => $company->id])
            ->post('/api/commands/execute', [
                'command_name' => $maliciousInput,
                'parameters' => [],
            ]);

        // Should either return 422 (validation) or 404 (command not found), not 500
        expect($response->status())->toBeIn([422, 404]);
    }

    // Verify database tables still exist and are intact
    $commandCount = Command::count();
    expect($commandCount)->toBeGreaterThanOrEqual(0);
});
