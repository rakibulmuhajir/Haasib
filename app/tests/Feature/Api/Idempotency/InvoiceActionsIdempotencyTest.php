<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function seedCompanyWithLedgerForIdemp(): array
{
    $currency = Currency::where('code', 'USD')->first();
    if (! $currency) {
        $currency = Currency::create([
            'id' => (string) Str::uuid(),
            'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'minor_unit' => 2,
        ]);
    }
    $company = Company::create([
        'id' => (string) Str::uuid(),
        'name' => 'Idemp Actions Co',
        'slug' => 'idemp-actions-co-'.Str::random(4),
        'base_currency' => 'USD',
        'currency_id' => $currency->id,
        'language' => 'en',
        'locale' => 'en_US',
        'settings' => [],
    ]);
    DB::statement("set local app.current_company = '".$company->id."'");
    $receivableId = (string) Str::uuid();
    $salesId = (string) Str::uuid();
    $taxId = (string) Str::uuid();
    DB::table('acct.ledger_accounts')->insert([
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

    return [$company, $currency];
}

it('invoice send/post/cancel endpoints are idempotent', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$company, $currency] = seedCompanyWithLedgerForIdemp();
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

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
        'items' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 100]],
    ];
    $headers = ['X-Company-Id' => $company->id, 'Idempotency-Key' => (string) Str::uuid()];
    $invoice = $this->withHeaders($headers)->postJson('/api/invoices', $payload)->assertStatus(201)->json('data');
    $id = $invoice['invoice_id'];

    // SEND
    $key = (string) Str::uuid();
    $h = ['X-Company-Id' => $company->id, 'Idempotency-Key' => $key];
    $this->withHeaders($h)->postJson("/api/invoices/{$id}/send")->assertStatus(200);
    $this->withHeaders($h)->postJson("/api/invoices/{$id}/send")->assertStatus(200);
    $status = DB::table('acct.invoices')->where('invoice_id', $id)->value('status');
    expect($status)->toBe('sent');

    // POST
    $key2 = (string) Str::uuid();
    $h2 = ['X-Company-Id' => $company->id, 'Idempotency-Key' => $key2];
    $this->withHeaders($h2)->postJson("/api/invoices/{$id}/post")->assertStatus(200);
    $this->withHeaders($h2)->postJson("/api/invoices/{$id}/post")->assertStatus(200);
    $status = DB::table('acct.invoices')->where('invoice_id', $id)->value('status');
    expect($status)->toBe('posted');
    // Ensure single journal entry
    $jeCount = DB::table('acct.journal_entries')->where('source_type', 'invoice')->where('source_id', $id)->count();
    expect($jeCount)->toBe(1);

    // CANCEL
    $key3 = (string) Str::uuid();
    $h3 = ['X-Company-Id' => $company->id, 'Idempotency-Key' => $key3];
    $this->withHeaders($h3)->postJson("/api/invoices/{$id}/cancel", ['reason' => 'test'])->assertStatus(200);
    $this->withHeaders($h3)->postJson("/api/invoices/{$id}/cancel", ['reason' => 'test'])->assertStatus(200);
    $status = DB::table('acct.invoices')->where('invoice_id', $id)->value('status');
    expect($status)->toBe('cancelled');
});
