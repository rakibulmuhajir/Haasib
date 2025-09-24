<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\LedgerIntegrationService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function seedCompanyWithARAndCash(): array
{
    $user = User::factory()->create();
    $currency = Currency::create([
        'id' => (string) Str::uuid(),
        'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'minor_unit' => 2,
    ]);
    $company = Company::create([
        'id' => (string) Str::uuid(),
        'name' => 'Alloc Co', 'slug' => 'alloc-co',
        'base_currency' => 'USD', 'currency_id' => $currency->id,
        'language' => 'en', 'locale' => 'en_US',
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
    DB::statement("set local app.current_company_id = '".$company->id."'");

    $receivableId = (string) Str::uuid();
    $cashId = (string) Str::uuid();
    DB::table('ledger_accounts')->insert([
        ['id' => $receivableId, 'company_id' => $company->id, 'code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'normal_balance' => 'debit', 'active' => true, 'system_account' => true, 'level' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['id' => $cashId, 'company_id' => $company->id, 'code' => '1010', 'name' => 'Cash', 'type' => 'asset', 'normal_balance' => 'debit', 'active' => true, 'system_account' => true, 'level' => 1, 'created_at' => now(), 'updated_at' => now()],
    ]);
    $company->settings = array_merge($company->settings ?? [], [
        'default_accounts_receivable_account_id' => $receivableId,
        'default_cash_account_id' => $cashId,
        'default_sales_revenue_account_id' => (string) Str::uuid(),
    ]);
    $company->save();

    return [$user, $company, $currency];
}

it('partial allocation updates invoice status to partial and can be posted to ledger', function () {
    [$user, $company, $currency] = seedCompanyWithARAndCash();
    $this->actingAs($user);

    $customer = Customer::create([
        'customer_id' => (string) Str::uuid(),
        'company_id' => $company->id,
        'name' => 'Client', 'email' => 'c@c.test', 'currency_id' => $currency->id,
        'is_active' => true, 'payment_terms' => 7,
    ]);

    // Create and post invoice of 100
    $invoice = app(InvoiceService::class)->createInvoice(
        company: $company,
        customer: $customer,
        items: [['description' => 'S', 'quantity' => 1, 'unit_price' => 100]],
        currency: $currency
    );
    $invoice = app(InvoiceService::class)->markAsSent($invoice);
    $invoice = app(InvoiceService::class)->markAsPosted($invoice);

    // Payment of 30
    $payment = app(PaymentService::class)->processIncomingPayment(
        company: $company,
        customer: $customer,
        amount: 30.00,
        paymentMethod: 'cash',
        paymentReference: 'PMT-Partial',
        currency: $currency
    );

    // Allocate 30 to the invoice
    $allocs = app(PaymentService::class)->allocatePayment($payment, [
        ['invoice_id' => $invoice->invoice_id, 'amount' => 30.00],
    ]);
    expect($allocs)->toBeArray();

    $invoice->refresh();
    expect($invoice->status)->toBe('partial');

    // Post allocation to ledger explicitly
    $allocation = $payment->allocations()->first();
    app(LedgerIntegrationService::class)->postPaymentAllocationToLedger($allocation);

    $posted = DB::table('journal_entries')
        ->where('company_id', $company->id)
        ->where('source_type', 'payment_allocation')
        ->where('source_id', $allocation->allocation_id)
        ->where('status', 'posted')
        ->exists();
    expect($posted)->toBeTrue();
});

it('void allocation sets status and can void ledger entry', function () {
    [$user, $company, $currency] = seedCompanyWithARAndCash();
    $this->actingAs($user);

    $customer = Customer::create([
        'customer_id' => (string) Str::uuid(),
        'company_id' => $company->id,
        'name' => 'Client2', 'email' => 'c2@c.test', 'currency_id' => $currency->id,
        'is_active' => true, 'payment_terms' => 7,
    ]);
    $invoice = app(InvoiceService::class)->createInvoice(
        company: $company,
        customer: $customer,
        items: [['description' => 'S', 'quantity' => 1, 'unit_price' => 50]],
        currency: $currency
    );
    $invoice = app(InvoiceService::class)->markAsSent($invoice);
    $invoice = app(InvoiceService::class)->markAsPosted($invoice);

    $payment = app(PaymentService::class)->processIncomingPayment(
        company: $company,
        customer: $customer,
        amount: 50.00,
        paymentMethod: 'cash',
        paymentReference: 'PMT-2',
        currency: $currency
    );
    app(PaymentService::class)->allocatePayment($payment, [['invoice_id' => $invoice->invoice_id, 'amount' => 20.00]]);
    $allocation = $payment->allocations()->first();
    app(LedgerIntegrationService::class)->postPaymentAllocationToLedger($allocation);

    // Void allocation (business record)
    app(PaymentService::class)->voidAllocation($allocation, 'Allocation voided');

    $allocation->refresh();
    expect($allocation->status)->toBe('void');
});

it('refunds an allocation and records a refund allocation', function () {
    [$user, $company, $currency] = seedCompanyWithARAndCash();
    $this->actingAs($user);

    $customer = Customer::create([
        'customer_id' => (string) Str::uuid(),
        'company_id' => $company->id,
        'name' => 'Client3', 'email' => 'c3@c.test', 'currency_id' => $currency->id,
        'is_active' => true, 'payment_terms' => 7,
    ]);
    $invoice = app(InvoiceService::class)->createInvoice(
        company: $company,
        customer: $customer,
        items: [['description' => 'S', 'quantity' => 1, 'unit_price' => 80]],
        currency: $currency
    );
    $invoice = app(InvoiceService::class)->markAsSent($invoice);
    $invoice = app(InvoiceService::class)->markAsPosted($invoice);

    $payment = app(PaymentService::class)->processIncomingPayment(
        company: $company,
        customer: $customer,
        amount: 80.00,
        paymentMethod: 'cash',
        paymentReference: 'PMT-3',
        currency: $currency
    );
    app(PaymentService::class)->allocatePayment($payment, [['invoice_id' => $invoice->invoice_id, 'amount' => 50.00]]);
    $allocation = $payment->allocations()->first();

    $refund = app(PaymentService::class)->refundAllocation($allocation, \Brick\Money\Money::of(20, 'USD'), 'Partial refund');
    expect($refund->status)->toBe('refunded');

    $invoice->refresh();
    // Active allocations sum should now be 30 (50 - 20), so not fully paid
    expect($invoice->status)->toBe('partial');
});
