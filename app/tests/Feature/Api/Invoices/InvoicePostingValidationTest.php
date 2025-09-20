<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function seedCompanyBasic(): array {
    $user = User::factory()->create();
    $currency = Currency::create([
        'id' => (string) Str::uuid(),
        'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'minor_unit' => 2,
    ]);
    $company = Company::create([
        'id' => (string) Str::uuid(),
        'name' => 'Val Co', 'slug' => 'val-co',
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
    return [$user, $company, $currency];
}

it('posting a draft invoice returns 422 and is idempotent', function () {
    [$user, $company, $currency] = seedCompanyBasic();
    $this->actingAs($user);
    $customer = Customer::create([
        'customer_id' => (string) Str::uuid(),
        'company_id' => $company->id,
        'name' => 'Draft Customer',
        'email' => 'c@c.test',
        'currency_id' => $currency->id,
        'is_active' => true,
        'payment_terms' => 7,
    ]);
    $headers = ['X-Company-Id' => $company->id, 'Idempotency-Key' => (string) Str::uuid()];
    $invoice = $this->withHeaders($headers)->postJson('/api/invoices', [
        'customer_id' => $customer->customer_id,
        'items' => [ ['description' => 'X', 'quantity' => 1, 'unit_price' => 10] ],
    ])->assertStatus(201)->json('data');

    $id = $invoice['invoice_id'];
    $k = (string) Str::uuid();
    $h = ['X-Company-Id' => $company->id, 'Idempotency-Key' => $k];
    $this->withHeaders($h)->postJson("/api/invoices/{$id}/post")->assertStatus(422);
    $this->withHeaders($h)->postJson("/api/invoices/{$id}/post")->assertStatus(422);
});

it('posting zero-total invoice returns 422', function () {
    [$user, $company, $currency] = seedCompanyBasic();
    $this->actingAs($user);
    $customer = Customer::create([
        'customer_id' => (string) Str::uuid(),
        'company_id' => $company->id,
        'name' => 'Zero Customer',
        'email' => 'z@z.test',
        'currency_id' => $currency->id,
        'is_active' => true,
        'payment_terms' => 7,
    ]);
    $headers = ['X-Company-Id' => $company->id, 'Idempotency-Key' => (string) Str::uuid()];
    $invoice = $this->withHeaders($headers)->postJson('/api/invoices', [
        'customer_id' => $customer->customer_id,
        'items' => [ ['description' => 'X', 'quantity' => 1, 'unit_price' => 10] ],
    ])->assertStatus(201)->json('data');

    // Force zero total
    $id = $invoice['invoice_id'];
    Invoice::where('invoice_id', $id)->update(['total_amount' => 0]);
    $k = (string) Str::uuid();
    $h = ['X-Company-Id' => $company->id, 'Idempotency-Key' => $k];
    // Mark as sent then force to zero and post
    $this->withHeaders($h)->postJson("/api/invoices/{$id}/send")->assertStatus(200);
    // Force zero again after send
    Invoice::where('invoice_id', $id)->update(['total_amount' => 0]);
    $this->withHeaders($h)->postJson("/api/invoices/{$id}/post")->assertStatus(422);
});

it('posting with future sent_at returns 422', function () {
    [$user, $company, $currency] = seedCompanyBasic();
    $this->actingAs($user);
    $customer = Customer::create([
        'customer_id' => (string) Str::uuid(),
        'company_id' => $company->id,
        'name' => 'Future Customer',
        'email' => 'f@f.test',
        'currency_id' => $currency->id,
        'is_active' => true,
        'payment_terms' => 7,
    ]);
    $headers = ['X-Company-Id' => $company->id, 'Idempotency-Key' => (string) Str::uuid()];
    $invoice = $this->withHeaders($headers)->postJson('/api/invoices', [
        'customer_id' => $customer->customer_id,
        'items' => [ ['description' => 'X', 'quantity' => 1, 'unit_price' => 10] ],
    ])->json('data');

    $id = $invoice['invoice_id'];
    Invoice::where('invoice_id', $id)->update(['status' => 'sent', 'sent_at' => now()->addDay()]);
    $k = (string) Str::uuid();
    $h = ['X-Company-Id' => $company->id, 'Idempotency-Key' => $k];
    $this->withHeaders($h)->postJson("/api/invoices/{$id}/post")->assertStatus(422);
});
