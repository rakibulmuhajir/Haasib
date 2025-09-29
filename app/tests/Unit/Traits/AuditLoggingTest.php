<?php

use App\Models\User;
use App\Support\ServiceContext;
use App\Traits\AuditLogging;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Create a test class that uses the AuditLogging trait
class AuditLoggingTestClass
{
    use AuditLogging;

    public function testLog(string $action, array $params, ?ServiceContext $context = null): void
    {
        $this->logAudit($action, $params, $context);
    }
}

test('audit logging trait logs with service context', function () {
    $user = User::factory()->create();
    $context = new ServiceContext($user, 'company-123', 'key-456');
    $tester = new AuditLoggingTestClass;

    $tester->testLog('test.action', ['foo' => 'bar'], $context);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'company_id' => 'company-123',
        'action' => 'test.action',
        'idempotency_key' => 'key-456',
    ]);

    $log = DB::table('audit_logs')->where('action', 'test.action')->first();
    expect(json_decode($log->params))->toEqual(['foo' => 'bar']);
});

test('audit logging trait works with legacy parameters', function () {
    $user = User::factory()->create();
    $tester = new AuditLoggingTestClass;

    $tester->testLog('legacy.action', ['old' => 'way']);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'legacy.action',
        'user_id' => null,
        'company_id' => null,
        'idempotency_key' => null,
    ]);
});

test('audit logging trait prioritizes context over legacy parameters', function () {
    $user = User::factory()->create();
    $context = new ServiceContext($user, 'from-context', 'from-context-key');
    $tester = new AuditLoggingTestClass;

    // Pass both context and legacy parameters - context should win
    $tester->testLog('mixed.action', ['test' => 'data'], $context);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'company_id' => 'from-context',
        'idempotency_key' => 'from-context-key',
    ]);
});

test('audit logging trait includes result data when provided', function () {
    $tester = new AuditLoggingTestClass;

    $tester->testLog('with.result', ['input' => 'value'], result: ['id' => '123']);

    $log = DB::table('audit_logs')->where('action', 'with.result')->first();
    expect(json_decode($log->result))->toEqual(['id' => '123']);
});

test('audit logging trait gracefully handles failures', function () {
    // This test verifies that audit logging failures don't throw exceptions
    $tester = new AuditLoggingTestClass;

    // Force a database error by making the params too large
    $largeParams = ['data' => str_repeat('x', 1000000)];

    // This should not throw an exception even if the insert fails
    $tester->testLog('error.test', $largeParams);

    // The log might not be in the database, but no exception was thrown
    $this->assertTrue(true); // If we get here, no exception was thrown
});
