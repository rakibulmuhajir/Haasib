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
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create();
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $this->service = new PaymentAllocationService(new AllocationStrategyService());
});

test('can allocate payment across multiple invoices manually', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'amount' => 1000,
        'remaining_amount' => 1000,
    ]);

    $invoice1 = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 400,
        'balance_due' => 400,
    ]);

    $invoice2 = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 800,
        'balance_due' => 800,
    ]);

    $allocations = [
        ['invoice_id' => $invoice1->id, 'amount' => 300],
        ['invoice_id' => $invoice2->id, 'amount' => 500],
    ];

    $result = $this->service->allocatePaymentAcrossInvoices(
        $payment,
        $allocations,
        $this->user,
        'manual'
    );

    expect($result['success'])->toBeTrue();
    expect($result['allocated_count'])->toBe(2);
    expect($result['total_allocated'])->toBe(800.0);

    $payment->refresh();
    expect($payment->remaining_amount)->toBe(200.0);

    $invoice1->refresh();
    expect($invoice1->balance_due)->toBe(100.0);
    expect($invoice1->total_allocated)->toBe(300.0);

    $invoice2->refresh();
    expect($invoice2->balance_due)->toBe(300.0);
    expect($invoice2->total_allocated)->toBe(500.0);

    expect(PaymentAllocation::count())->toBe(2);
});

test('validates allocation amounts correctly', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'amount' => 500,
        'remaining_amount' => 500,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 300,
        'balance_due' => 300,
    ]);

    // Test allocation amount exceeding balance due
    $allocations = [['invoice_id' => $invoice->id, 'amount' => 400]];

    $result = $this->service->allocatePaymentAcrossInvoices(
        $payment,
        $allocations,
        $this->user,
        'manual'
    );

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('exceeds balance due');
});

test('validates total allocation does not exceed payment amount', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'amount' => 500,
        'remaining_amount' => 500,
    ]);

    $invoice1 = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 400,
        'balance_due' => 400,
    ]);

    $invoice2 = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 400,
        'balance_due' => 400,
    ]);

    $allocations = [
        ['invoice_id' => $invoice1->id, 'amount' => 300],
        ['invoice_id' => $invoice2->id, 'amount' => 300], // Total 600 > 500
    ];

    $result = $this->service->allocatePaymentAcrossInvoices(
        $payment,
        $allocations,
        $this->user,
        'manual'
    );

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('exceeds available payment amount');
});

test('can allocate using fifo strategy', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'amount' => 1000,
        'remaining_amount' => 1000,
    ]);

    // Create invoices with different due dates
    $invoice1 = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 300,
        'balance_due' => 300,
        'due_date' => now()->subDays(10), // Oldest
    ]);

    $invoice2 = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 400,
        'balance_due' => 400,
        'due_date' => now()->subDays(5),
    ]);

    $invoice3 = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 500,
        'balance_due' => 500,
        'due_date' => now()->addDays(5), // Newest
    ]);

    $result = $this->service->allocateWithStrategy(
        $payment,
        'fifo',
        $this->user
    );

    expect($result['success'])->toBeTrue();
    expect($result['allocated_count'])->toBe(2); // Should allocate to first two invoices

    $payment->refresh();
    expect($payment->remaining_amount)->toBe(300.0); // 1000 - 300 - 400

    // FIFO should pay oldest first
    $invoice1->refresh();
    expect($invoice1->balance_due)->toBe(0.0);
    expect($invoice1->status)->toBe('paid');

    $invoice2->refresh();
    expect($invoice2->balance_due)->toBe(0.0);
    expect($invoice2->status)->toBe('paid');

    $invoice3->refresh();
    expect($invoice3->balance_due)->toBe(500.0); // Should not be touched
});

test('can allocate using proportional strategy', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'amount' => 600,
        'remaining_amount' => 600,
    ]);

    $invoice1 = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 300,
        'balance_due' => 300,
    ]);

    $invoice2 = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 600,
        'balance_due' => 600,
    ]);

    $invoice3 = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 300,
        'balance_due' => 300,
    ]);

    $result = $this->service->allocateWithStrategy(
        $payment,
        'proportional',
        $this->user
    );

    expect($result['success'])->toBeTrue();

    // Total balance due: 1200
    // Payment: 600
    // Proportional allocation:
    // Invoice 1: 600 * (300/1200) = 150
    // Invoice 2: 600 * (600/1200) = 300
    // Invoice 3: 600 * (300/1200) = 150

    $invoice1->refresh();
    expect($invoice1->balance_due)->toBe(150.0);

    $invoice2->refresh();
    expect($invoice2->balance_due)->toBe(300.0);

    $invoice3->refresh();
    expect($invoice3->balance_due)->toBe(150.0);
});

test('can allocate using overdue_first strategy', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'amount' => 800,
        'remaining_amount' => 800,
    ]);

    $overdueInvoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 500,
        'balance_due' => 500,
        'due_date' => now()->subDays(10), // Overdue
    ]);

    $currentInvoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 600,
        'balance_due' => 600,
        'due_date' => now()->addDays(10), // Not overdue
    ]);

    $result = $this->service->allocateWithStrategy(
        $payment,
        'overdue_first',
        $this->user
    );

    expect($result['success'])->toBeTrue();

    // Should pay overdue invoice first
    $overdueInvoice->refresh();
    expect($overdueInvoice->balance_due)->toBe(0.0);
    expect($overdueInvoice->status)->toBe('paid');

    $currentInvoice->refresh();
    expect($currentInvoice->balance_due)->toBe(300.0); // 800 - 500 = 300 remaining
});

test('handles allocation within database transaction', function () {
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

    // Mock a database error during allocation
    DB::shouldReceive('transaction')
        ->once()
        ->andThrow(new \Exception('Database error'));

    $allocations = [['invoice_id' => $invoice->id, 'amount' => 300]];

    expect(fn() => $this->service->allocatePaymentAcrossInvoices(
        $payment,
        $allocations,
        $this->user,
        'manual'
    ))->toThrow('Database error');

    // Ensure no changes were committed
    $payment->refresh();
    $invoice->refresh();
    expect($payment->remaining_amount)->toBe(1000.0);
    expect($invoice->balance_due)->toBe(500.0);
    expect(PaymentAllocation::count())->toBe(0);
});

test('gets allocation statistics correctly', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    // Create some allocations
    PaymentAllocation::factory()->count(3)->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocation_strategy' => 'fifo',
    ]);

    PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocation_strategy' => 'proportional',
        'reversed_at' => now(),
    ]);

    $stats = $this->service->getAllocationStatistics($this->company);

    expect($stats['total_allocations'])->toBe(4);
    expect($stats['active_allocations'])->toBe(3);
    expect($stats['reversed_allocations'])->toBe(1);
    expect($stats['total_allocated_amount'])->toBeGreaterThan(0);
    expect($stats['strategy_usage']['fifo'])->toBe(3);
    expect($stats['strategy_usage']['proportional'])->toBe(1);
});

test('validates invoice ownership during allocation', function () {
    $otherCompany = Company::factory()->create();
    $otherCustomer = Customer::factory()->create(['company_id' => $otherCompany->id]);

    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'amount' => 1000,
        'remaining_amount' => 1000,
    ]);

    $otherInvoice = Invoice::factory()->create([
        'company_id' => $otherCompany->id,
        'customer_id' => $otherCustomer->id,
        'total_amount' => 500,
        'balance_due' => 500,
    ]);

    $allocations = [['invoice_id' => $otherInvoice->id, 'amount' => 300]];

    $result = $this->service->allocatePaymentAcrossInvoices(
        $payment,
        $allocations,
        $this->user,
        'manual'
    );

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('different customer');
});
