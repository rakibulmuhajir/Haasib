<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('debugs invoice post response', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $currency = Currency::create([
        'id' => (string) Str::uuid(),
        'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'minor_unit' => 2,
    ]);

    $company = Company::create([
        'id' => (string) Str::uuid(),
        'name' => 'Debug Co',
        'slug' => 'debug-co',
        'base_currency' => 'USD',
        'currency_id' => $currency->id,
        'language' => 'en',
        'locale' => 'en_US',
        'settings' => [],
    ]);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::statement("set local app.current_company = '".$company->id."'");

    // Seed ledger accounts and wire company settings
    $receivableId = (string) Str::uuid();
    $salesId = (string) Str::uuid();
    $taxId = (string) Str::uuid();
    DB::table('ledger_accounts')->insert([
        ['id' => $receivableId, 'company_id' => $company->id, 'code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'normal_balance' => 'debit', 'active' => true, 'system_account' => true, 'level' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $salesId, 'company_id' => $company->id, 'code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue', 'normal_balance' => 'credit', 'active' => true, 'system_account' => true, 'level' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $taxId, 'company_id' => $company->id, 'code' => '2100', 'name' => 'Sales Tax Payable', 'type' => 'liability', 'normal_balance' => 'credit', 'active' => true, 'system_account' => true, 'level' => 1, 'created_at' => now(), 'updated_at' => now()],
    ]);
    $company->settings = array_merge($company->settings ?? [], [
        'default_accounts_receivable_account_id' => $receivableId,
        'default_sales_revenue_account_id' => $salesId,
        'default_sales_tax_account_id' => $taxId,
    ]);
    $company->save();

    $customer = Customer::create([
        'customer_id' => (string) Str::uuid(),
        'company_id' => $company->id,
        'name' => 'Acme LLC',
        'email' => 'billing@acme.test',
        'currency_id' => $currency->id,
        'is_active' => true,
    ]);

    $createPayload = [
        'customer_id' => $customer->customer_id,
        'currency_id' => $currency->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'items' => [ ['description' => 'Service', 'quantity' => 1, 'unit_price' => 10] ],
    ];
    $headers = [ 'X-Company-Id' => $company->id, 'Idempotency-Key' => (string) Str::uuid() ];
    $invoiceData = $this->withHeaders($headers)->postJson('/api/invoices', $createPayload)->assertStatus(201)->json('data');

    $invoiceId = $invoiceData['invoice_id'];

    $this->withHeaders($headers)->postJson("/api/invoices/{$invoiceId}/send")->assertStatus(200);

    $this->withHeaders($headers)->postJson("/api/invoices/{$invoiceId}/post")->dump();
});

