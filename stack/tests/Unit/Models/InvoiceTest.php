<?php

use App\Models\Company;
use App\Models\CreditNoteApplication;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('calculates totals from line items and updates balance due', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $customer = \Database\Factories\Invoicing\CustomerFactory::new()->create([
        'company_id' => $company->id,
        'created_by_user_id' => $user->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'created_by_user_id' => $user->id,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
        'balance_due' => 0,
        'status' => 'draft',
        'payment_status' => 'unpaid',
    ]);

    InvoiceLineItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => Str::uuid(),
        'description' => 'Consulting',
        'quantity' => 2,
        'unit_price' => 100,
        'discount_type' => 'fixed',
        'discount_value' => 20,
        'tax_rate' => 10,
        'tax_amount' => 18,
        'total' => 198,
    ]);

    InvoiceLineItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => Str::uuid(),
        'description' => 'Implementation',
        'quantity' => 1,
        'unit_price' => 50,
        'discount_type' => null,
        'discount_value' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 50,
    ]);

    $invoice->calculateTotals();

    $invoice->refresh();

    expect((float) $invoice->subtotal)->toBe(250.0);
    expect((float) $invoice->discount_amount)->toBe(20.0);
    expect((float) $invoice->tax_amount)->toBe(18.0);
    expect((float) $invoice->total_amount)->toBe(248.0);
    expect((float) $invoice->balance_due)->toBe(248.0);
    expect($invoice->payment_status)->toBe('unpaid');
});

it('applies payments and credits when calculating totals', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $customer = \Database\Factories\Invoicing\CustomerFactory::new()->create([
        'company_id' => $company->id,
        'created_by_user_id' => $user->id,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'created_by_user_id' => $user->id,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
        'balance_due' => 0,
        'status' => 'sent',
        'payment_status' => 'unpaid',
        'issue_date' => now()->subDays(40),
        'due_date' => now()->subDays(10),
    ]);

    InvoiceLineItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => Str::uuid(),
        'description' => 'Annual subscription',
        'quantity' => 1,
        'unit_price' => 1000,
        'discount_type' => null,
        'discount_value' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 1000,
    ]);

    PaymentAllocation::create([
        'company_id' => $company->id,
        'payment_id' => Str::uuid(),
        'invoice_id' => $invoice->id,
        'allocated_amount' => 400,
        'original_amount' => 400,
        'discount_amount' => 0,
        'discount_percent' => 0,
        'allocation_date' => now()->toDateString(),
        'allocation_method' => 'manual',
        'allocation_strategy' => 'oldest',
        'status' => 'active',
        'created_by_user_id' => $user->id,
    ]);

    CreditNoteApplication::create([
        'credit_note_id' => Str::uuid(),
        'invoice_id' => $invoice->id,
        'amount_applied' => 200,
        'applied_at' => now(),
        'user_id' => $user->id,
    ]);

    $invoice->calculateTotals();
    $invoice->refresh();

    expect((float) $invoice->total_amount)->toBe(1000.0);
    expect((float) $invoice->balance_due)->toBe(400.0);
    expect($invoice->payment_status)->toBe('partially_paid');
});

it('marks invoice as sent with timestamp', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'draft',
        'payment_status' => 'unpaid',
    ]);

    $invoice->markAsSent();

    expect($invoice->fresh()->status)->toBe('sent');
    expect($invoice->fresh()->sent_at)->not->toBeNull();
});

it('generates sequential invoice numbers per company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $customer = \Database\Factories\Invoicing\CustomerFactory::new()->create([
        'company_id' => $company->id,
        'created_by_user_id' => $user->id,
    ]);

    $firstNumber = null;
    DB::transaction(function () use ($company, $customer, $user, &$firstNumber) {
        $firstNumber = Invoice::generateInvoiceNumber($company->id);

        Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_number' => $firstNumber,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
            'balance_due' => 0,
            'created_by_user_id' => $user->id,
        ]);
    });

    $secondNumber = null;
    DB::transaction(function () use ($company, $customer, $user, &$secondNumber) {
        $secondNumber = Invoice::generateInvoiceNumber($company->id);

        Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_number' => $secondNumber,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
            'balance_due' => 0,
            'created_by_user_id' => $user->id,
        ]);
    });

    expect($firstNumber)->not->toBeNull();
    expect($secondNumber)->not->toBeNull();

    preg_match('/(\d+)$/', $firstNumber, $firstMatch);
    preg_match('/(\d+)$/', $secondNumber, $secondMatch);

    expect((int) $secondMatch[1])->toBe((int) $firstMatch[1] + 1);
});
