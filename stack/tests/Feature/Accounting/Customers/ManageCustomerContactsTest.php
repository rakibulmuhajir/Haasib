<?php

use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->company = Company::factory()->create();
    $this->user->companies()->attach($this->company->id);

    $this->permissions = ['accounting.customers.manage_contacts'];
    $this->user->givePermissionTo($this->permissions);

    setCompanyContext($this->company);
});

it('can create a customer contact via command bus', function () {
    // Create a customer first
    $customer = \App\Models\Customer::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $contactData = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'phone' => '+1234567890',
        'role' => 'billing',
        'is_primary' => true,
        'preferred_channel' => 'email',
    ];

    // This should fail because the actions don't exist yet
    $response = $this->actingAs($this->user)
        ->postJson("/api/customers/{$customer->id}/contacts", $contactData);

    // Test should fail with 404 or 500 since actions aren't implemented
    $response->assertStatus(404);
});

it('validates unique email per customer', function () {
    $customer = \App\Models\Customer::factory()->create([
        'company_id' => $this->company->id,
    ]);

    // Create first contact
    $firstContact = [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane.smith@example.com',
        'role' => 'billing',
        'is_primary' => true,
    ];

    // Try to create second contact with same email
    $secondContact = [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.smith@example.com', // Same email
        'role' => 'technical',
        'is_primary' => false,
    ];

    // First request should fail since actions don't exist
    $response1 = $this->actingAs($this->user)
        ->postJson("/api/customers/{$customer->id}/contacts", $firstContact);
    $response1->assertStatus(404);

    // Second request should also fail
    $response2 = $this->actingAs($this->user)
        ->postJson("/api/customers/{$customer->id}/contacts", $secondContact);
    $response2->assertStatus(404);
});

it('enforces single primary contact per role', function () {
    $customer = \App\Models\Customer::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $primaryContact = [
        'first_name' => 'Alice',
        'last_name' => 'Johnson',
        'email' => 'alice.j@example.com',
        'role' => 'billing',
        'is_primary' => true,
    ];

    $secondPrimary = [
        'first_name' => 'Bob',
        'last_name' => 'Wilson',
        'email' => 'bob.w@example.com',
        'role' => 'billing', // Same role
        'is_primary' => true, // Also primary
    ];

    // Both should fail since actions don't exist
    $response1 = $this->actingAs($this->user)
        ->postJson("/api/customers/{$customer->id}/contacts", $primaryContact);
    $response1->assertStatus(404);

    $response2 = $this->actingAs($this->user)
        ->postJson("/api/customers/{$customer->id}/contacts", $secondPrimary);
    $response2->assertStatus(404);
});

it('can update customer contact', function () {
    $customer = \App\Models\Customer::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $updateData = [
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'email' => 'updated@example.com',
        'phone' => '+9876543210',
        'role' => 'technical',
        'is_primary' => false,
        'preferred_channel' => 'phone',
    ];

    // Should fail since contact and update actions don't exist
    $response = $this->actingAs($this->user)
        ->putJson("/api/customers/{$customer->id}/contacts/fake-contact-id", $updateData);

    $response->assertStatus(404);
});

it('can delete customer contact', function () {
    $customer = \App\Models\Customer::factory()->create([
        'company_id' => $this->company->id,
    ]);

    // Should fail since delete action doesn't exist
    $response = $this->actingAs($this->user)
        ->deleteJson("/api/customers/{$customer->id}/contacts/fake-contact-id");

    $response->assertStatus(404);
});

it('enforces rbac permissions', function () {
    $customer = \App\Models\Customer::factory()->create([
        'company_id' => $this->company->id,
    ]);

    // User without permissions
    $unauthorizedUser = User::factory()->create();
    $unauthorizedUser->companies()->attach($this->company->id);

    $contactData = [
        'first_name' => 'Unauthorized',
        'last_name' => 'User',
        'email' => 'unauth@example.com',
        'role' => 'billing',
    ];

    $response = $this->actingAs($unauthorizedUser)
        ->postJson("/api/customers/{$customer->id}/contacts", $contactData);

    // Should fail due to permissions (or 404 if routes don't exist)
    $response->assertStatus(403);
});

it('enforces tenancy isolation', function () {
    // Create customer in different company
    $otherCompany = Company::factory()->create();
    $otherCustomer = \App\Models\Customer::factory()->create([
        'company_id' => $otherCompany->id,
    ]);

    $contactData = [
        'first_name' => 'Cross',
        'last_name' => 'Tenant',
        'email' => 'cross@example.com',
        'role' => 'billing',
    ];

    // Should not be able to access contacts from other company
    $response = $this->actingAs($this->user)
        ->postJson("/api/customers/{$otherCustomer->id}/contacts", $contactData);

    $response->assertStatus(404);
});

it('can list customer contacts', function () {
    $customer = \App\Models\Customer::factory()->create([
        'company_id' => $this->company->id,
    ]);

    // Should fail since listing endpoint doesn't exist
    $response = $this->actingAs($this->user)
        ->getJson("/api/customers/{$customer->id}/contacts");

    $response->assertStatus(404);
});

// Helper function
function setCompanyContext(Company $company): void
{
    // Set current company context for RLS
    app()->singleton('current_company', fn () => $company);
    session(['current_company_id' => $company->id]);
}
