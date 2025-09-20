<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('creates and posts an invoice and updates AR (pgsql)', function () {
    if (DB::getDriverName() !== 'pgsql') {
        test()->markTestSkipped('Requires PostgreSQL (set -c app/phpunit.pgsql.xml).');
    }

    Artisan::call('migrate:fresh', [
        '--seed' => true,
        '--seeder' => 'Database\Seeders\PgsqlTestSeeder',
        '--force' => true,
    ]);

    // 1) Seed minimal tenant context
    $user = User::create([
        'id' => (string) Str::uuid(),
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('secret'),
    ]);

    Auth::login($user);

    $currency = Currency::create([
        'id' => (string) Str::uuid(),
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'minor_unit' => 2,
    ]);

    $company = Company::create([
        'id' => (string) Str::uuid(),
        'name' => 'Test Co',
        'slug' => 'test-co',
        'base_currency' => 'USD',
        'currency_id' => $currency->id,
        'language' => 'en',
        'locale' => 'en_US',
    ]);

    // Set RLS session var
    DB::statement("set local app.current_company = '".$company->id."'");

    $customer = Customer::create([
        'customer_id' => (string) Str::uuid(),
        'company_id' => $company->id,
        'name' => 'Acme LLC',
        'email' => 'billing@acme.test',
        'currency_id' => $currency->id,
        'is_active' => true,
    ]);

    // 2) Create invoice via service
    $svc = app(InvoiceService::class);
    $invoice = $svc->createInvoice(
        company: $company,
        customer: $customer,
        items: [
            ['description' => 'Consulting', 'quantity' => 2, 'unit_price' => 100],
            ['description' => 'Support', 'quantity' => 1, 'unit_price' => 50],
        ],
        currency: $currency,
        invoiceDate: now()->toDateString(),
        dueDate: now()->addDays(7)->toDateString(),
        notes: 'Test invoice',
        terms: 'NET 7'
    );

    expect($invoice->status)->toBe('draft');
    expect($invoice->total_amount)->toBeFloat();

    // 3) Post invoice to ledger (listener will update AR)
    $posted = $svc->markAsPosted($invoice);
    expect($posted->status)->toBe('posted');

    // 4) Verify Accounts Receivable updated
    $ar = \App\Models\AccountsReceivable::where('company_id', $company->id)
        ->where('customer_id', $customer->customer_id)
        ->where('invoice_id', $posted->invoice_id)
        ->first();

    expect($ar)->not()->toBeNull();
    expect($ar->amount_due)->toBeFloat();
    expect(in_array($ar->aging_category, ['current','1-30','31-60','61-90','90+']))->toBeTrue();
})->group('pgsql');

