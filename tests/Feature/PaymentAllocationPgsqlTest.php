<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('creates a payment and allocates/refunds on pgsql', function () {
    if (DB::getDriverName() !== 'pgsql') {
        test()->markTestSkipped('Requires PostgreSQL (use -c app/phpunit.pgsql.xml).');
    }

    Artisan::call('migrate:fresh', [
        '--seed' => true,
        '--seeder' => 'Database\\Seeders\\PgsqlTestSeeder',
        '--force' => true,
    ]);

    $user = User::create([
        'id' => (string) Str::uuid(),
        'name' => 'Allocator',
        'email' => 'alloc@example.com',
        'password' => bcrypt('secret'),
    ]);
    Auth::login($user);

    $currency = Currency::where('code', 'USD')->first();
    $company = Company::create([
        'id' => (string) Str::uuid(),
        'name' => 'Alloc Co',
        'slug' => 'alloc-co',
        'base_currency' => 'USD',
        'currency_id' => $currency->id,
        'language' => 'en',
        'locale' => 'en_US',
    ]);

    DB::statement("set local app.current_company = '".$company->id."'");

    $customer = Customer::create([
        'customer_id' => (string) Str::uuid(),
        'company_id' => $company->id,
        'name' => 'Test Customer',
        'email' => 'c@example.test',
        'currency_id' => $currency->id,
        'is_active' => true,
    ]);

    // Create two invoices via service to have balances to allocate
    $invSvc = app(\App\Services\InvoiceService::class);
    $i1 = $invSvc->createInvoice($company, $customer, [[ 'description' => 'A', 'quantity' => 1, 'unit_price' => 50 ]], $currency, now()->toDateString(), now()->addDays(7)->toDateString());
    $i2 = $invSvc->createInvoice($company, $customer, [[ 'description' => 'B', 'quantity' => 1, 'unit_price' => 25 ]], $currency, now()->toDateString(), now()->addDays(7)->toDateString());

    // Create a payment and allocate to invoice 1
    $paySvc = app(PaymentService::class);
    $payment = $paySvc->processPayment(
        company: $company,
        customer: $customer,
        amount: 60,
        paymentMethod: 'cash',
        paymentReference: 'REF-1',
        paymentDate: now()->toDateString(),
        currency: $currency,
        exchangeRate: 1,
        notes: 'test',
        autoAllocate: false,
        idempotencyKey: (string) Str::uuid()
    );

    $alloc = $paySvc->allocatePayment($payment, $i1->getKey(), 50, now()->toDateString(), 'primary alloc');
    expect($alloc->allocated_amount)->toBeFloat();

    // Refund part of allocation
    $refund = $alloc->refund(Brick\Money\Money::of(10, 'USD'), 'partial');
    expect($refund->status)->toBe('refunded');
})->group('pgsql');

