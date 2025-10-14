<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('debugs invoice create response', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $currency = Currency::where('code', 'USD')->first();
    if (! $currency) {
        $currency = Currency::create([
            'id' => (string) Str::uuid(),
            'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'minor_unit' => 2,
        ]);
    }
    $company = Company::create([
        'id' => (string) Str::uuid(),
        'name' => 'Dbg Co', 'slug' => 'dbg-co-'.Str::random(4),
        'base_currency' => 'USD', 'currency_id' => $currency->id,
        'language' => 'en', 'locale' => 'en_US',
    ]);
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
        'name' => 'Dbg Customer',
        'email' => 'd@c.test',
        'currency_id' => $currency->id,
        'is_active' => true,
        'payment_terms' => 7,
    ]);

    $payload = [
        'customer_id' => $customer->customer_id,
        'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 10]],
    ];

    $headers = ['X-Company-Id' => $company->id, 'Idempotency-Key' => (string) Str::uuid()];
    $resp = $this->withHeaders($headers)->postJson('/api/invoices', $payload);
    $resp->assertStatus(201);
    $data = $resp->json('data');
    expect($data)->toHaveKeys(['invoice_id', 'status', 'total_amount']);
    expect($data['status'])->toBe('draft');
});
