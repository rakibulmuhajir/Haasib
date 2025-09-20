<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('reuses response for same Idempotency-Key on invoice create', function () {
    // Arrange
    $user = User::factory()->create();
    $this->actingAs($user);

    $currency = Currency::create([
        'id' => (string) Str::uuid(),
        'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'minor_unit' => 2,
    ]);

    $company = Company::create([
        'id' => (string) Str::uuid(),
        'name' => 'Idemp Co',
        'slug' => 'idemp-co',
        'base_currency' => 'USD',
        'currency_id' => $currency->id,
        'language' => 'en',
        'locale' => 'en_US',
    ]);

    // Attach user to company and set RLS session var for test
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::statement("set local app.current_company = '".$company->id."'");

    $customer = Customer::create([
        'customer_id' => (string) Str::uuid(),
        'company_id' => $company->id,
        'name' => 'ACME',
        'email' => 'billing@acme.test',
        'currency_id' => $currency->id,
        'is_active' => true,
        'payment_terms' => 7,
    ]);

    $payload = [
        'customer_id' => $customer->customer_id,
        'currency_id' => $currency->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'notes' => 'Idempotency test',
        'items' => [
            ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100],
        ],
    ];

    $headers = [
        'X-Company-Id' => $company->id,
        'Idempotency-Key' => (string) Str::uuid(),
    ];

    // Act: first request
    $r1 = $this->withHeaders($headers)->postJson('/api/invoices', $payload)
        ->assertStatus(201)->json('data');

    // Act: second request with the same idempotency key
    $r2 = $this->withHeaders($headers)->postJson('/api/invoices', $payload)
        ->assertStatus(201)->json('data');

    // Assert: same invoice returned, no duplicates
    expect($r1['invoice_id'])->toBe($r2['invoice_id']);
    expect(App\Models\Invoice::count())->toBe(1);
});
