<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\PaymentAllocationService;
use App\Services\AllocationStrategyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create();
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
});

test('can create payment allocation with valid data', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'amount' => 1000,
        'remaining_amount' => 1000,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 500,
        'balance_due' => 500,
    ]);

    $allocation = PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 300,
        'allocation_strategy' => 'fifo',
    ]);

    expect($allocation)->toBeInstanceOf(PaymentAllocation::class);
    expect($allocation->payment_id)->toBe($payment->id);
    expect($allocation->invoice_id)->toBe($invoice->id);
    expect($allocation->allocated_amount)->toBe(300.0);
    expect($allocation->allocation_strategy)->toBe('fifo');
    expect($allocation->is_reversed)->toBeFalse();
});

test('payment allocation relationships work correctly', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $allocation = PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
    ]);

    expect($allocation->payment)->toBeInstanceOf(Payment::class);
    expect($allocation->invoice)->toBeInstanceOf(Invoice::class);
    expect($allocation->payment->id)->toBe($payment->id);
    expect($allocation->invoice->id)->toBe($invoice->id);
});

test('can reverse payment allocation', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $allocation = PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 200,
    ]);

    expect($allocation->is_reversed)->toBeFalse();
    expect($allocation->reversed_at)->toBeNull();

    $allocation->reverse('Customer requested refund', $this->user);

    $allocation->refresh();
    expect($allocation->is_reversed)->toBeTrue();
    expect($allocation->reversed_at)->not->toBeNull();
    expect($allocation->reversal_reason)->toBe('Customer requested refund');
    expect($allocation->reversed_by_user_id)->toBe($this->user->id);
});

test('allocation scopes work correctly', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    // Create active allocations
    $activeAllocation1 = PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 100,
        'allocation_strategy' => 'fifo',
    ]);

    $activeAllocation2 = PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 200,
        'allocation_strategy' => 'proportional',
    ]);

    // Create reversed allocation
    $reversedAllocation = PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 150,
        'reversed_at' => now(),
        'reversal_reason' => 'Test reversal',
    ]);

    // Test active scope
    $activeAllocations = PaymentAllocation::active()->get();
    expect($activeAllocations)->toHaveCount(2);
    expect($activeAllocations->pluck('id'))->toContain($activeAllocation1->id, $activeAllocation2->id);

    // Test reversed scope
    $reversedAllocations = PaymentAllocation::reversed()->get();
    expect($reversedAllocations)->toHaveCount(1);
    expect($reversedAllocations->first()->id)->toBe($reversedAllocation->id);

    // Test strategy scope
    $fifoAllocations = PaymentAllocation::forStrategy('fifo')->get();
    expect($fifoAllocations)->toHaveCount(1);
    expect($fifoAllocations->first()->id)->toBe($activeAllocation1->id);

    // Test payment scope
    $paymentAllocations = PaymentAllocation::forPayment($payment->id)->get();
    expect($paymentAllocations)->toHaveCount(3);

    // Test invoice scope
    $invoiceAllocations = PaymentAllocation::forInvoice($invoice->id)->get();
    expect($invoiceAllocations)->toHaveCount(3);
});

test('allocation validation prevents invalid amounts', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    // Test negative amount
    expect(fn() => PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => -100,
    ]))->toThrow('Exception');

    // Test zero amount
    expect(fn() => PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 0,
    ]))->toThrow('Exception');
});

test('allocation methods calculate correctly', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $allocation = PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 250.75,
    ]);

    expect($allocation->getFormattedAllocatedAmount())->toBe('250.75');
    expect($allocation->getAllocationAgeInDays())->toBe(0); // Created today
});

test('can get allocation statistics', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    // Create allocations with different strategies
    PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 100,
        'allocation_strategy' => 'fifo',
    ]);

    PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 200,
        'allocation_strategy' => 'fifo',
    ]);

    PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 150,
        'allocation_strategy' => 'proportional',
    ]);

    $stats = PaymentAllocation::getAllocationStats($this->company->id);

    expect($stats['total_allocations'])->toBe(3);
    expect($stats['total_allocated_amount'])->toBe(450.0);
    expect($stats['active_allocations'])->toBe(3);
    expect($stats['reversed_allocations'])->toBe(0);
    expect($stats['strategy_usage']['fifo'])->toBe(2);
    expect($stats['strategy_usage']['proportional'])->toBe(1);
});

test('allocation audit trail is maintained', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $allocation = PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 300,
        'allocation_method' => 'automatic',
        'allocation_strategy' => 'fifo',
    ]);

    // Check initial state
    expect($allocation->allocation_method)->toBe('automatic');
    expect($allocation->allocation_strategy)->toBe('fifo');
    expect($allocation->created_at)->not->toBeNull();
    expect($allocation->updated_at)->not->toBeNull();

    // Reverse and check audit trail
    $allocation->reverse('Test reversal', $this->user);
    $allocation->refresh();

    expect($allocation->reversed_at)->not->toBeNull();
    expect($allocation->reversal_reason)->toBe('Test reversal');
    expect($allocation->reversed_by_user_id)->toBe($this->user->id);
    expect($allocation->updated_at->gt($allocation->created_at))->toBeTrue();
});
