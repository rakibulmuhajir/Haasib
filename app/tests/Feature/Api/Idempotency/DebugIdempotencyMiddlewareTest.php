<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('debugs idempotency replay', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $currency = Currency::create([
        'id' => (string) Str::uuid(), 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'minor_unit' => 2,
    ]);
    $company = Company::create([
        'id' => (string) Str::uuid(), 'name' => 'IdemDbg', 'slug' => 'idem-dbg',
        'base_currency' => 'USD', 'currency_id' => $currency->id, 'language' => 'en', 'locale' => 'en_US',
    ]);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::statement("set local app.current_company = '".$company->id."'");
    $customer = Customer::create([
        'customer_id' => (string) Str::uuid(), 'company_id' => $company->id,
        'name' => 'C', 'email' => 'c@c.test', 'currency_id' => $currency->id, 'is_active' => true, 'payment_terms' => 7,
    ]);
    $payload = ['customer_id' => $customer->customer_id, 'items' => [['description' => 'A', 'quantity' => 1, 'unit_price' => 1]]];
    $key = (string) Str::uuid();
    $h = ['X-Company-Id' => $company->id, 'Idempotency-Key' => $key];
    $r1 = $this->withHeaders($h)->postJson('/api/invoices', $payload)
        ->assertStatus(201)->json('data');
    $r2 = $this->withHeaders($h)->postJson('/api/invoices', $payload)
        ->assertStatus(201)->json('data');

    // Same response reused for the same Idempotency-Key
    expect($r2['invoice_id'])->toBe($r1['invoice_id']);
});
