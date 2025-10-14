<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create();
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
});

test('payment allocate command works with manual allocation', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'amount' => 1000,
        'remaining_amount' => 1000,
        'payment_number' => 'PAY-001',
    ]);

    $invoice1 = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 400,
        'balance_due' => 400,
        'invoice_number' => 'INV-001',
    ]);

    $invoice2 = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 600,
        'balance_due' => 600,
        'invoice_number' => 'INV-002',
    ]);

    $this->artisan('payment:allocate', [
        'payment' => $payment->payment_number,
        '--invoices' => "{$invoice1->id},{$invoice2->id}",
        '--amounts' => "300,500",
        '--force' => true,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Allocation completed successfully');

    $payment->refresh();
    expect($payment->remaining_amount)->toBe(200.0);

    expect(PaymentAllocation::count())->toBe(2);
});

test('payment allocate command works with automatic allocation', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'amount' => 800,
        'remaining_amount' => 800,
        'payment_number' => 'PAY-002',
    ]);

    // Create unpaid invoices
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 300,
        'balance_due' => 300,
        'due_date' => now()->subDays(5),
    ]);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 400,
        'balance_due' => 400,
        'due_date' => now()->subDays(2),
    ]);

    $this->artisan('payment:allocate', [
        'payment' => $payment->payment_number,
        '--strategy' => 'fifo',
        '--auto' => true,
        '--force' => true,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Automatic Allocation completed successfully');
});

test('payment allocate command validates payment existence', function () {
    $this->artisan('payment:allocate', [
        'payment' => 'NONEXISTENT',
        '--invoices' => '123',
        '--amounts' => '100',
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('not found');
});

test('payment allocation list command displays allocations', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'payment_number' => 'PAY-003',
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'invoice_number' => 'INV-003',
    ]);

    PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 250,
        'allocation_strategy' => 'fifo',
    ]);

    $this->artisan('payment:allocation:list')
        ->assertExitCode(0)
        ->expectsTable(['ID', 'Payment', 'Invoice', 'Customer', 'Amount', 'Strategy', 'Date', 'Status'], [
            [substr($payment->id, 0, 8), $payment->payment_number, $invoice->invoice_number, $this->customer->name, '250.00', 'fifo', now()->format('Y-m-d'), 'Active']
        ]);
});

test('payment allocation list command supports filtering', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'payment_number' => 'PAY-004',
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'invoice_number' => 'INV-004',
    ]);

    $allocation = PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 150,
        'allocation_strategy' => 'proportional',
    ]);

    // Test filtering by payment
    $this->artisan('payment:allocation:list', [
        '--payment' => $payment->payment_number,
    ])
        ->assertExitCode(0);

    // Test filtering by strategy
    $this->artisan('payment:allocation:list', [
        '--strategy' => 'proportional',
    ])
        ->assertExitCode(0);

    // Test filtering by status
    $this->artisan('payment:allocation:list', [
        '--status' => 'active',
    ])
        ->assertExitCode(0);
});

test('payment allocation reverse command reverses allocations', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 500,
        'balance_due' => 200, // After some allocation
    ]);

    $allocation = PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 300,
    ]);

    $this->artisan('payment:allocation:reverse', [
        'allocation' => $allocation->id,
        '--reason' => 'Test reversal',
        '--force' => true,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Reversal completed successfully');

    $allocation->refresh();
    expect($allocation->is_reversed)->toBeTrue();
    expect($allocation->reversal_reason)->toBe('Test reversal');
});

test('payment allocation reverse command validates reason', function () {
    $this->artisan('payment:allocation:reverse', [
        'allocation' => 'some-id',
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('--reason parameter is required');
});

test('payment allocation report command generates reports', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'allocated_amount' => 200,
        'allocation_strategy' => 'fifo',
    ]);

    $this->artisan('payment:allocation:report', [
        '--type' => 'comprehensive',
        '--format' => 'json',
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('"period"')
        ->expectsOutputToContain('"summary"');
});

test('payment allocation report command supports different formats', function () {
    PaymentAllocation::factory()->create([
        'allocated_amount' => 100,
        'allocation_strategy' => 'fifo',
    ]);

    // Test table format (default)
    $this->artisan('payment:allocation:report')
        ->assertExitCode(0)
        ->expectsOutputToContain('Report Summary');

    // Test JSON format
    $this->artisan('payment:allocation:report', [
        '--format' => 'json',
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('{');

    // Test CSV format
    $this->artisan('payment:allocation:report', [
        '--format' => 'csv',
    ])
        ->assertExitCode(0);
});

test('commands handle edge cases gracefully', function () {
    // Test allocate with insufficient payment amount
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'amount' => 100,
        'remaining_amount' => 100,
        'payment_number' => 'PAY-005',
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 200,
        'balance_due' => 200,
    ]);

    $this->artisan('payment:allocate', [
        'payment' => $payment->payment_number,
        '--invoices' => $invoice->id,
        '--amounts' => '150', // More than payment amount
        '--force' => true,
    ])
        ->assertExitCode(1);

    // Test reverse non-existent allocation
    $this->artisan('payment:allocation:reverse', [
        'allocation' => 'non-existent-id',
        '--reason' => 'Test',
        '--force' => true,
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('No valid allocations found');
});

test('commands validate input parameters', function () {
    // Test invalid date format
    $this->artisan('payment:allocation:report', [
        '--start' => 'invalid-date',
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('Invalid date format');

    // Test invalid report type
    $this->artisan('payment:allocation:report', [
        '--type' => 'invalid-type',
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('Invalid report type');

    // Test invalid output format
    $this->artisan('payment:allocation:list', [
        '--format' => 'invalid-format',
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('Invalid output format');
});

test('commands support dry run mode', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'payment_number' => 'PAY-006',
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    // Test allocation dry run
    $this->artisan('payment:allocate', [
        'payment' => $payment->payment_number,
        '--invoices' => $invoice->id,
        '--amounts' => '100',
        '--dry-run' => true,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('DRY RUN MODE');

    // Test reversal dry run
    $allocation = PaymentAllocation::factory()->create([
        'payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
    ]);

    $this->artisan('payment:allocation:reverse', [
        'allocation' => $allocation->id,
        '--reason' => 'Test',
        '--dry-run' => true,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('DRY RUN MODE');
});
