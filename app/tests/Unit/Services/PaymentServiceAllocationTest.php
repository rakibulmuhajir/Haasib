<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\ServiceContext;
use Brick\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('payment allocation locks invoices to prevent race conditions', function () {
    $company = Company::factory()->create();
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $currency = Currency::factory()->create();

    // Create two invoices with $100 balance each
    $invoice1 = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'total_amount' => 100,
        'balance_due' => 100,
    ]);

    $invoice2 = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'total_amount' => 100,
        'balance_due' => 100,
    ]);

    $service = new PaymentService;
    $context = ServiceContext::forSystem($company->id);

    // Create a payment of $150
    $payment = $service->createPayment(
        $company,
        $customer,
        Money::of(150, $currency->code),
        $currency,
        'check',
        now()->toDateString(),
        $context
    );

    // Simulate concurrent allocation attempts
    $results = DB::transaction(function () use ($service, $payment, $invoice1, $invoice2) {
        // First allocation tries to allocate full payment amount
        $allocations1 = [
            ['invoice_id' => $invoice1->id, 'amount' => 100],
            ['invoice_id' => $invoice2->id, 'amount' => 50],
        ];

        $result1 = $service->allocatePayment(
            $payment,
            $allocations1,
            'First allocation attempt',
            $context
        );

        // This should succeed only if locking is working
        return $result1;
    });

    // Verify that invoices were locked and allocation succeeded
    expect($results)->toHaveCount(2);
    expect($results[0]->amount)->toBe('100.0000');
    expect($results[1]->amount)->toBe('50.0000');

    // Verify invoice balances were updated correctly
    $invoice1->refresh();
    $invoice2->refresh();
    expect($invoice1->balance_due)->toBe(0);
    expect($invoice2->balance_due)->toBe(50);
});

test('payment allocation with autoallocate uses locked invoices', function () {
    $company = Company::factory()->create();
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $currency = Currency::factory()->create();

    // Create two invoices
    $invoice1 = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'total_amount' => 75,
        'balance_due' => 75,
    ]);

    $invoice2 = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'total_amount' => 25,
        'balance_due' => 25,
    ]);

    $service = new PaymentService;
    $context = ServiceContext::forSystem($company->id);

    // Create payment with auto-allocation
    $payment = $service->createPayment(
        $company,
        $customer,
        Money::of(100, $currency->code),
        $currency,
        'check',
        now()->toDateString(),
        $context,
        null,
        null,
        null,
        null,
        autoAllocate: true,
        invoiceAllocations: [
            $invoice1->id => 75,
            $invoice2->id => 25,
        ]
    );

    // Verify allocations were created correctly
    $allocations = $payment->allocations;
    expect($allocations)->toHaveCount(2);
    expect($allocations->sum('amount'))->toBe(100);

    // Verify invoice balances
    $invoice1->refresh();
    $invoice2->refresh();
    expect($invoice1->balance_due)->toBe(0);
    expect($invoice2->balance_due)->toBe(0);
});

test('payment allocation prevents overspending with concurrent requests', function () {
    $company = Company::factory()->create();
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $currency = Currency::factory()->create();

    // Create invoice with $50 balance
    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'total_amount' => 50,
        'balance_due' => 50,
    ]);

    // Create payment with $100 unallocated
    $payment = Payment::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'amount' => 100,
        'status' => 'completed',
    ]);

    $service = new PaymentService;
    $context = ServiceContext::forSystem($company->id);

    // First allocation succeeds
    $allocations1 = [
        ['invoice_id' => $invoice->id, 'amount' => 40],
    ];

    $result1 = $service->allocatePayment($payment, $allocations1, 'First allocation', $context);
    expect($result1)->toHaveCount(1);

    // Second allocation attempts to allocate more than remaining unallocated amount
    $allocations2 = [
        ['invoice_id' => $invoice->id, 'amount' => 70], // Only $60 left unallocated
    ];

    // This should throw an exception
    expect(fn () => $service->allocatePayment($payment, $allocations2, 'Second allocation', $context))
        ->toThrow(\InvalidArgumentException::class, 'Total allocation amount exceeds unallocated payment amount');
});

test('payment allocation validates invoice ownership within locked query', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();
    $customer = Customer::factory()->create(['company_id' => $company1->id]);
    $currency = Currency::factory()->create();

    // Invoice belongs to company1
    $invoice = Invoice::factory()->create([
        'company_id' => $company1->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'total_amount' => 50,
    ]);

    // Payment belongs to company2
    $payment = Payment::factory()->create([
        'company_id' => $company2->id,
        'customer_id' => $customer->id,
        'currency_id' => $currency->id,
        'amount' => 100,
    ]);

    $service = new PaymentService;
    $context = ServiceContext::forSystem($company2->id);

    // Attempt to allocate payment from company2 to invoice from company1
    $allocations = [
        ['invoice_id' => $invoice->id, 'amount' => 50],
    ];

    expect(fn () => $service->allocatePayment($payment, $allocations, 'Invalid allocation', $context))
        ->toThrow(\InvalidArgumentException::class, 'Invalid invoice ID');
});
