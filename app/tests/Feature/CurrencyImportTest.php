<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\User;

test('currency import with base currency conversion works correctly', function () {
    // Create a user and company with EUR as base currency
    $user = User::factory()->create();
    $company = Company::factory()->create([
        'base_currency' => 'EUR',
    ]);

    // Associate user with company
    $user->companies()->attach($company->id, ['role' => 'admin']);

    // Act as user and set current company
    $this->actingAs($user);
    session(['current_company_id' => $company->id]);

    // Test importing specific currencies
    $response = $this->postJson('/api/currencies/import/specific', [
        'currency_codes' => ['GBP', 'JPY'],
        'source' => 'ecb',
        'update_existing' => false,
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            'source',
            'created',
            'updated',
            'skipped',
            'errors',
        ],
    ]);

    // Verify currencies were created
    $this->assertDatabaseHas('currencies', ['code' => 'GBP']);
    $this->assertDatabaseHas('currencies', ['code' => 'JPY']);

    // Verify exchange rates are relative to EUR (not USD)
    $gbp = Currency::where('code', 'GBP')->first();
    $jpy = Currency::where('code', 'JPY')->first();

    // With EUR as base, GBP should have a different rate than if USD was base
    $this->assertNotEquals(1.0, $gbp->exchange_rate);
    $this->assertNotEquals(1.0, $jpy->exchange_rate);
});

test('currency search returns results with base currency exchange rates', function () {
    // Reset cached permissions
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    // Seed RBAC
    $this->seed(\Database\Seeders\RbacSeeder::class);

    // Create a super admin user who has system permissions
    $user = User::factory()->create(['system_role' => 'superadmin']);
    $company = Company::factory()->create([
        'base_currency' => 'GBP',
    ]);

    // Associate user with company
    $user->companies()->attach($company->id, ['role' => 'admin']);

    // Act as user and set current company (superadmin bypasses permission checks)
    $this->actingAs($user);
    session(['current_company_id' => $company->id]);

    // Test searching currencies
    $response = $this->json('GET', '/api/currencies/import/search', [
        'query' => 'Euro',
        'source' => 'ecb',
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            'source',
            'query',
            'currencies',
        ],
    ]);

    // Verify EUR is in results with exchange rate relative to GBP
    $data = $response->json('data');
    $eur = collect($data['currencies'])->firstWhere('code', 'EUR');

    $this->assertNotNull($eur);
    $this->assertArrayHasKey('exchange_rate', $eur);
    $this->assertNotEquals(1.0, $eur['exchange_rate']); // Should not be 1 since base is GBP
});
