<?php

use App\Models\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('audit entry factory creates valid records', function () {
    $auditEntry = AuditEntry::factory()->create();

    expect($auditEntry)->not->toBeNull();
    expect($auditEntry->id)->not->toBeEmpty();
    expect($auditEntry->event)->toBeIn(['created', 'updated', 'deleted', 'restored', 'password_changed']);
    expect($auditEntry->model_type)->not->toBeEmpty();
    expect($auditEntry->user_id)->not->toBeEmpty();
    expect($auditEntry->company_id)->not->toBeEmpty();
});

test('audit entry scopes work correctly', function () {
    // Create different types of audit entries
    AuditEntry::factory()->count(3)->create(['event' => 'created']);
    AuditEntry::factory()->count(2)->create(['event' => 'updated']);
    AuditEntry::factory()->count(1)->create(['event' => 'deleted']);

    // Test event scope
    $createdEntries = AuditEntry::forEvent('created')->get();
    expect($createdEntries)->toHaveCount(3);

    $updatedEntries = AuditEntry::forEvent('updated')->get();
    expect($updatedEntries)->toHaveCount(2);

    $deletedEntries = AuditEntry::forEvent('deleted')->get();
    expect($deletedEntries)->toHaveCount(1);
});

test('audit entry helper methods work correctly', function () {
    // Test different event types
    $createdEntry = AuditEntry::factory()->create(['event' => 'created']);
    $updatedEntry = AuditEntry::factory()->create(['event' => 'updated']);
    $deletedEntry = AuditEntry::factory()->create(['event' => 'deleted']);

    expect($createdEntry->isCreation())->toBeTrue();
    expect($createdEntry->isUpdate())->toBeFalse();
    expect($createdEntry->isDeletion())->toBeFalse();

    expect($updatedEntry->isCreation())->toBeFalse();
    expect($updatedEntry->isUpdate())->toBeTrue();
    expect($updatedEntry->isDeletion())->toBeFalse();

    expect($deletedEntry->isCreation())->toBeFalse();
    expect($deletedEntry->isUpdate())->toBeFalse();
    expect($deletedEntry->isDeletion())->toBeTrue();
});

test('audit entry diff calculation works correctly', function () {
    $oldValues = ['name' => 'Old Name', 'email' => 'old@example.com', 'status' => 'active'];
    $newValues = ['name' => 'New Name', 'email' => 'old@example.com', 'phone' => '123-456-7890'];

    $auditEntry = AuditEntry::factory()->create([
        'old_values' => $oldValues,
        'new_values' => $newValues,
    ]);

    $diff = $auditEntry->getDiff();

    expect($diff)->toHaveCount(3); // name changed, phone added, status removed
    expect($diff['name']['old'])->toBe('Old Name');
    expect($diff['name']['new'])->toBe('New Name');
    expect($diff['phone']['old'])->toBeNull();
    expect($diff['phone']['new'])->toBe('123-456-7890');
    expect($diff['status']['old'])->toBe('active');
    expect($diff['status']['new'])->toBeNull();
});

test('audit entry attribute change detection works', function () {
    $auditEntry = AuditEntry::factory()->create([
        'old_values' => ['name' => 'Old Name', 'amount' => 100.00],
        'new_values' => ['name' => 'New Name', 'amount' => 150.00],
    ]);

    expect($auditEntry->hasAttributeChange('name'))->toBeTrue();
    expect($auditEntry->hasAttributeChange('amount'))->toBeTrue();
    expect($auditEntry->hasAttributeChange('email'))->toBeFalse();

    expect($auditEntry->getOldValue('name'))->toBe('Old Name');
    expect($auditEntry->getNewValue('name'))->toBe('New Name');
    expect($auditEntry->getOldValue('amount'))->toBe(100.00);
    expect($auditEntry->getNewValue('amount'))->toBe(150.00);
});
