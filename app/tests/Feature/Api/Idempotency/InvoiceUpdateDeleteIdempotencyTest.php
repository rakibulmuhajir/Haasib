<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('invoice update and delete are idempotent', function () {
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
        'name' => 'Idemp Update Co',
        'slug' => 'idemp-update-co-'.Str::random(4),
        'base_currency' => 'USD',
        'currency_id' => $currency->id,
        'language' => 'en',
        'locale' => 'en_US',
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
        'name' => 'ACME',
        'email' => 'billing@acme.test',
        'currency_id' => $currency->id,
        'is_active' => true,
        'payment_terms' => 7,
    ]);

    $createHeaders = ['X-Company-Id' => $company->id, 'Idempotency-Key' => (string) Str::uuid()];
    $invoice = $this->withHeaders($createHeaders)->postJson('/api/invoices', [
        'customer_id' => $customer->customer_id,
        'currency_id' => $currency->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 10]],
    ])->assertStatus(201)->json('data');

    $id = $invoice['invoice_id'];

    // Update idempotency
    $updatePayload = [
        'notes' => 'Updated once',
        'items' => [['description' => 'Item', 'quantity' => 2, 'unit_price' => 10]],
    ];
    $key = (string) Str::uuid();
    $headers = ['X-Company-Id' => $company->id, 'Idempotency-Key' => $key];
    $this->withHeaders($headers)->putJson("/api/invoices/{$id}", $updatePayload)->assertStatus(200);
    $this->withHeaders($headers)->putJson("/api/invoices/{$id}", $updatePayload)->assertStatus(200);
    $notes = DB::table('acct.invoices')->where('invoice_id', $id)->value('notes');
    expect($notes)->toBe('Updated once');

    // Delete idempotency
    $delKey = (string) Str::uuid();
    $delHeaders = ['X-Company-Id' => $company->id, 'Idempotency-Key' => $delKey];
    $this->withHeaders($delHeaders)->deleteJson("/api/invoices/{$id}")->assertStatus(200);
    $this->withHeaders($delHeaders)->deleteJson("/api/invoices/{$id}")->assertStatus(200);
    $exists = DB::table('acct.invoices')->where('invoice_id', $id)->whereNull('deleted_at')->exists();
    expect($exists)->toBeFalse();
});
