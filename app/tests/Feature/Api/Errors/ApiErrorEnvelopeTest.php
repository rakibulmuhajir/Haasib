<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Str;

it('returns standardized code for validation errors', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $currency = Currency::firstOrCreate(['code' => 'EUR'], [
        'id' => (string) Str::uuid(),
        'name' => 'Euro', 'symbol' => '€', 'minor_unit' => 2,
    ]);
    $company = Company::create([
        'id' => (string) Str::uuid(),
        'name' => 'Err Co ' . Str::random(5), 'slug' => 'err-co-' . Str::random(5), 'base_currency' => 'EUR', 'currency_id' => $currency->id,
        'language' => 'en', 'locale' => 'en_US',
    ]);
    \DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $customer = Customer::create([
        'customer_id' => (string) Str::uuid(), 'company_id' => $company->id,
        'name' => 'C', 'email' => 'c@c.test', 'currency_id' => $currency->id, 'is_active' => true, 'payment_terms' => 7,
    ]);

    $headers = ['X-Company-Id' => $company->id, 'Idempotency-Key' => (string) Str::uuid()];
    $invoice = $this->withHeaders($headers)->postJson('/api/invoices', [
        'customer_id' => $customer->customer_id,
        'items' => [['description' => 'S', 'quantity' => 1, 'unit_price' => 1]],
    ])->assertStatus(201)->json('data');

    // Force future sent_at then attempt post to trigger 422
    Invoice::where('invoice_id', $invoice['invoice_id'])->update(['status' => 'sent', 'sent_at' => now()->addDay()]);
    $resp = $this->withHeaders($headers)->postJson("/api/invoices/{$invoice['invoice_id']}/post");
    $resp->assertStatus(422);
    $resp->assertJsonPath('code', 'VALIDATION_ERROR');
});
