<?php

use App\Models\AuditEntry;
use App\Models\Company;
use App\Models\User;

test('audit entry model can be created manually', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    $auditEntry = AuditEntry::create([
        'event' => 'created',
        'model_type' => 'App\\Models\\Customer',
        'model_id' => 'test-uuid',
        'user_id' => $user->id,
        'company_id' => $company->id,
        'old_values' => null,
        'new_values' => ['name' => 'Test Customer'],
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test Agent',
        'tags' => ['customer', 'created'],
        'metadata' => ['test' => true],
    ]);

    expect($auditEntry)->not->toBeNull();
    expect($auditEntry->id)->not->toBeEmpty();
    expect($auditEntry->event)->toBe('created');
    expect($auditEntry->model_type)->toBe('App\\Models\\Customer');
    expect($auditEntry->model_id)->toBe('test-uuid');
    expect($auditEntry->user_id)->toBe($user->id);
    expect($auditEntry->company_id)->toBe($company->id);
    expect($auditEntry->new_values)->toHaveKey('name');
    expect($auditEntry->tags)->toContain('customer');
    expect($auditEntry->metadata['test'])->toBeTrue();
});

test('audit entry event detection methods work', function () {
    $createdEntry = new AuditEntry(['event' => 'created']);
    $updatedEntry = new AuditEntry(['event' => 'updated']);
    $deletedEntry = new AuditEntry(['event' => 'deleted']);

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

test('audit entry diff calculation works', function () {
    $auditEntry = new AuditEntry([
        'old_values' => ['name' => 'Old Name', 'email' => 'old@example.com', 'status' => 'active'],
        'new_values' => ['name' => 'New Name', 'email' => 'old@example.com', 'phone' => '123-456-7890'],
    ]);

    $diff = $auditEntry->getDiff();

    expect($diff)->toHaveCount(3);
    expect($diff['name']['old'])->toBe('Old Name');
    expect($diff['name']['new'])->toBe('New Name');
    expect($diff['phone']['old'])->toBeNull();
    expect($diff['phone']['new'])->toBe('123-456-7890');
    expect($diff['status']['old'])->toBe('active');
    expect($diff['status']['new'])->toBeNull();
});

test('audit entry attribute change detection works', function () {
    $auditEntry = new AuditEntry([
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
