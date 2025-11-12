<?php

use App\Models\AuditEntry;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('audit trail is created when customer is created', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    $this->actingAs($user);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    $auditEntry = AuditEntry::where('model_type', Customer::class)
        ->where('model_id', $customer->id)
        ->where('event', 'created')
        ->first();

    expect($auditEntry)->not->toBeNull();
    expect($auditEntry->user_id)->toBe($user->id);
    expect($auditEntry->company_id)->toBe($company->id);
    expect($auditEntry->event)->toBe('created');
    expect($auditEntry->new_values)->toHaveKey('name');
    expect($auditEntry->tags)->toContain('customer');
});

test('audit trail is created when customer is updated', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $customer = Customer::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user);

    $originalName = $customer->name;
    $newName = 'Updated Customer Name';

    $customer->update(['name' => $newName]);

    $auditEntry = AuditEntry::where('model_type', Customer::class)
        ->where('model_id', $customer->id)
        ->where('event', 'updated')
        ->first();

    expect($auditEntry)->not->toBeNull();
    expect($auditEntry->user_id)->toBe($user->id);
    expect($auditEntry->old_values['name'])->toBe($originalName);
    expect($auditEntry->new_values['name'])->toBe($newName);
    expect($auditEntry->tags)->toContain('customer');
    expect($auditEntry->tags)->toContain('updated');
});

test('audit trail is created when customer is deleted', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $customer = Customer::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user);

    $customer->delete();

    $auditEntry = AuditEntry::where('model_type', Customer::class)
        ->where('model_id', $customer->id)
        ->where('event', 'deleted')
        ->first();

    expect($auditEntry)->not->toBeNull();
    expect($auditEntry->user_id)->toBe($user->id);
    expect($auditEntry->company_id)->toBe($company->id);
    expect($auditEntry->event)->toBe('deleted');
    expect($auditEntry->old_values)->toHaveKey('name');
    expect($auditEntry->new_values)->toBeNull();
    expect($auditEntry->tags)->toContain('customer');
    expect($auditEntry->tags)->toContain('deleted');
});

test('audit trail ignores system field changes', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $customer = Customer::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user);

    // Update only the updated_at timestamp (which should be ignored)
    $customer->touch();

    $auditEntries = AuditEntry::where('model_type', Customer::class)
        ->where('model_id', $customer->id)
        ->where('event', 'updated')
        ->get();

    // Should not create audit entry for just timestamp changes
    expect($auditEntries)->toHaveCount(0);
});

test('audit trail stores metadata correctly', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    $this->actingAs($user);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'name' => 'Test Customer',
    ]);

    $auditEntry = AuditEntry::where('model_type', Customer::class)
        ->where('event', 'created')
        ->first();

    expect($auditEntry->metadata)->toHaveKey('model_name');
    expect($auditEntry->metadata['model_name'])->toBe('Customer');
    expect($auditEntry->metadata['model_id'])->toBe($customer->id);
    expect($auditEntry->metadata)->toHaveKey('user_context');
    expect($auditEntry->metadata['user_context']['id'])->toBe($user->id);
    expect($auditEntry->metadata['user_context']['name'])->toBe($user->name);
});

test('audit trail works with different models', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Test with Company model
    $company = Company::factory()->create();

    $auditEntry = AuditEntry::where('model_type', Company::class)
        ->where('event', 'created')
        ->first();

    expect($auditEntry)->not->BeNull();
    expect($auditEntry->user_id)->toBe($user->id);
    expect($auditEntry->tags)->toContain('security');
    expect($auditEntry->tags)->toContain('access_control');
});

test('audit entry can retrieve audited model', function () {
    $customer = Customer::factory()->create();

    // Force an audit entry creation
    $auditEntry = AuditEntry::factory()->create([
        'model_type' => Customer::class,
        'model_id' => $customer->id,
        'event' => 'updated',
    ]);

    $retrievedModel = $auditEntry->getModel();

    expect($retrievedModel)->not->toBeNull();
    expect($retrievedModel->id)->toBe($customer->id);
});

test('audit entry scopes work correctly', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    // Create different types of audit entries
    AuditEntry::factory()->count(3)->created()->forEntity(Customer::class, 'uuid-1')->create();
    AuditEntry::factory()->count(2)->updated()->forEntity(Company::class, 'uuid-2')->create();
    AuditEntry::factory()->count(1)->deleted()->forEntity(Customer::class, 'uuid-3')->create();

    // Test event scope
    $createdEntries = AuditEntry::forEvent('created')->get();
    expect($createdEntries)->toHaveCount(3);

    $updatedEntries = AuditEntry::forEvent('updated')->get();
    expect($updatedEntries)->toHaveCount(2);

    // Test model scope
    $customerEntries = AuditEntry::forModel(Customer::class)->get();
    expect($customerEntries)->toHaveCount(4);

    $companyEntries = AuditEntry::forModel(Company::class)->get();
    expect($companyEntries)->toHaveCount(2);
});

test('audit entry helper methods work correctly', function () {
    $user = User::factory()->create();

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
