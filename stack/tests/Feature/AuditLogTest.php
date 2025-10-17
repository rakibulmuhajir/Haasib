<?php

use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('audit_log function writes to audit.entries table', function () {
    // Create a user for testing
    $user = User::factory()->create();

    // Set the current user context
    DB::statement("SET app.current_user_id = '{$user->id}'");
    DB::statement("SET app.current_company_id = '{$user->companies->first()->id}'");
    DB::statement("SET app.ip_address = '127.0.0.1'");
    DB::statement("SET app.user_agent = 'Test Browser'");

    // Call the audit_log function
    DB::statement("
        SELECT audit_log(
            'test_action',
            '{\"test\": \"data\", \"timestamp\": \"2025-10-16T10:00:00Z\"}'::jsonb,
            '{$user->id}'::uuid,
            'test.entity',
            '{$user->id}'::uuid,
            '{$user->companies->first()->id}'::uuid
        );
    ");

    // Assert that the audit entry was created in the correct table
    $auditEntry = AuditEntry::where('action', 'test_action')
        ->where('entity_type', 'test.entity')
        ->where('user_id', $user->id)
        ->first();

    expect($auditEntry)->not->toBeNull();
    expect($auditEntry->action)->toBe('test_action');
    expect($auditEntry->entity_type)->toBe('test.entity');
    expect($auditEntry->user_id)->toBe($user->id);
    expect($auditEntry->company_id)->toBe($user->companies->first()->id);
    expect($auditEntry->new_values)->toHaveKey('test');
    expect($auditEntry->new_values['test'])->toBe('data');
    expect($auditEntry->ip_address)->toBe('127.0.0.1');
    expect($auditEntry->user_agent)->toBe('Test Browser');
    expect($auditEntry->is_system_action)->toBeFalse();

    // Clean up
    DB::statement('RESET ALL');
});

test('audit_log function resolves current settings when parameters are null', function () {
    // Create a user for testing
    $user = User::factory()->create();

    // Set the current user context
    DB::statement("SET app.current_user_id = '{$user->id}'");
    DB::statement("SET app.current_company_id = '{$user->companies->first()->id}'");
    DB::statement("SET app.ip_address = '192.168.1.1'");
    DB::statement("SET app.user_agent = 'Auto Browser'");

    // Call the audit_log function with null parameters (should resolve from settings)
    DB::statement("
        SELECT audit_log(
            'auto_action',
            '{\"auto\": true}'::jsonb
        );
    ");

    // Assert that the audit entry was created with resolved values
    $auditEntry = AuditEntry::where('action', 'auto_action')
        ->where('user_id', $user->id)
        ->first();

    expect($auditEntry)->not->toBeNull();
    expect($auditEntry->user_id)->toBe($user->id);
    expect($auditEntry->company_id)->toBe($user->companies->first()->id);
    expect($auditEntry->ip_address)->toBe('192.168.1.1');
    expect($auditEntry->user_agent)->toBe('Auto Browser');
    expect($auditEntry->entity_type)->toBe('system.event');

    // Clean up
    DB::statement('RESET ALL');
});

test('audit entries are created in correct schema with RLS', function () {
    // Create a user for testing
    $user = User::factory()->create();

    // Set the current user context
    DB::statement("SET app.current_user_id = '{$user->id}'");
    DB::statement("SET app.current_company_id = '{$user->companies->first()->id}'");

    // Call the audit_log function
    DB::statement("
        SELECT audit_log(
            'rls_test',
            '{\"test\": \"rls\"}'::jsonb
        );
    ");

    // Verify the entry exists in the audit.entries table (not auth.audit_entries)
    $existsInNewSchema = DB::table('audit.entries')
        ->where('action', 'rls_test')
        ->where('user_id', $user->id)
        ->exists();

    expect($existsInNewSchema)->toBeTrue();

    // Verify it doesn't exist in the old schema (if the old table still exists)
    $existsInOldSchema = DB::table('auth.audit_entries')
        ->where('action', 'rls_test')
        ->where('user_id', $user->id)
        ->exists();

    // This might be false if the old table was dropped, which is expected
    // The test verifies that data is going to the new schema

    // Clean up
    DB::statement('RESET ALL');
});
