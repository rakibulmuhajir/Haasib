<?php

// tests/Feature/TenancyTest.php
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

it('lists companies for the current user', function () {
    $user = User::factory()->create();
    $c1 = Company::factory()->create();
    $c2 = Company::factory()->create();
    $user->companies()->attach([$c1->id, $c2->id]);

    actingAs($user)
        ->getJson('/api/v1/me/companies')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('prevents switching to a company the user does not belong to', function () {
    $user = User::factory()->create();
    $alien = Company::factory()->create();

    actingAs($user)
        ->postJson('/api/v1/me/companies/switch', ['company_id' => $alien->id])
        ->assertStatus(403);
});

it('applies RLS via app.current_company_id', function () {
    $user = User::factory()->create();
    $c = Company::factory()->create();
    $user->companies()->attach($c->id);

    // Fake a tenant-scoped read by setting GUC then reading a tenant table you attach later
    actingAs($user)->withHeader('X-Company-Id', $c->id)
        ->getJson('/api/v1/me/companies') // runs through SetTenantContext and sets GUC
        ->assertOk();

    // Optional: once you have a tenant table, insert cross-company rows and assert PG filters them.
});
