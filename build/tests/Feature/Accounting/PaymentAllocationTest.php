<?php

use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentAllocation;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\CustomerStatementService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

// Reuses statementFixture() from CustomerStatementTest.php (company, AR/cash/revenue
// accounts, one customer, AR_INVOICE/AR_PAYMENT posting templates). Only loaded when the
// whole Accounting directory is run together (module scope), per project convention.

function allocationInvoice(array $f, float $total, string $date): Invoice
{
    return Invoice::create([
        'company_id' => $f['company']->id,
        'customer_id' => $f['customer']->id,
        'invoice_number' => 'INV-ALLOC-' . str()->random(6),
        'invoice_date' => $date,
        'due_date' => $date,
        'status' => 'sent',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'subtotal' => $total,
        'total_amount' => $total,
        'paid_amount' => 0,
        'balance' => $total,
    ]);
}

function dispatchPayment(array $f, array $params): array
{
    return app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('payment.create', $params, $f['user'], true));
}

test('one payment settles three invoices oldest-first and the buyer balance falls by the full amount', function () {
    $f = statementFixture();
    $i1 = allocationInvoice($f, 1000, '2026-09-01');
    $i2 = allocationInvoice($f, 2000, '2026-09-05');
    $i3 = allocationInvoice($f, 5000, '2026-09-10');

    dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 4000, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);

    expect((float) $i1->fresh()->balance)->toBe(0.0)->and($i1->fresh()->status)->toBe('paid')
        ->and((float) $i2->fresh()->balance)->toBe(0.0)->and($i2->fresh()->status)->toBe('paid')
        ->and((float) $i3->fresh()->balance)->toBe(4000.0)->and($i3->fresh()->status)->toBe('partial');

    $statement = app(CustomerStatementService::class)->statement($f['customer']->fresh());
    expect($statement['closing_balance'])->toBe(4000.0); // 8000 invoiced - 4000 paid
});

test('explicit allocations are honoured over auto oldest-first order', function () {
    $f = statementFixture();
    $older = allocationInvoice($f, 1000, '2026-09-01');
    $newer = allocationInvoice($f, 1000, '2026-09-10');

    dispatchPayment($f, [
        'customer_id' => $f['customer']->id,
        'amount' => 1000,
        'allocations' => [['invoice_id' => $newer->id, 'amount' => 1000]],
        'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);

    expect((float) $newer->fresh()->balance)->toBe(0.0)
        ->and((float) $older->fresh()->balance)->toBe(1000.0);
});

test('a remainder beyond all open invoices sits on account and still reduces the buyer balance', function () {
    $f = statementFixture();
    $invoice = allocationInvoice($f, 1000, '2026-09-01');

    $result = dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 4000, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);

    expect((float) $invoice->fresh()->balance)->toBe(0.0)
        ->and((float) $result['data']['on_account'])->toBe(3000.0);

    $statement = app(CustomerStatementService::class)->statement($f['customer']->fresh());
    expect($statement['closing_balance'])->toBe(-3000.0) // paid 3000 more than owed
        ->and($statement['available_credit'])->toBe(3000.0);
});

test('an advance payment with no invoices at all is recorded entirely on account', function () {
    $f = statementFixture();

    $result = dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 2500, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);

    expect((float) $result['data']['on_account'])->toBe(2500.0);
    $payment = Payment::find($result['data']['id']);
    expect(PaymentAllocation::where('payment_id', $payment->id)->whereNull('invoice_id')->sole()->amount_allocated)
        ->toEqual(2500.0);

    $statement = app(CustomerStatementService::class)->statement($f['customer']->fresh());
    expect($statement['closing_balance'])->toBe(-2500.0)
        ->and($statement['available_credit'])->toBe(2500.0);
});

test('applying an on-account credit to a later invoice settles it and creates no new cash movement', function () {
    $f = statementFixture();
    $advance = dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 3000, 'method' => 'cash', 'date' => '2026-09-01',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);
    $transactionCountBefore = Transaction::where('company_id', $f['company']->id)->count();
    $paymentCountBefore = Payment::where('company_id', $f['company']->id)->count();

    $invoice = allocationInvoice($f, 1000, '2026-09-10');

    $applyResult = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('payment.apply_credit', [
        'customer_id' => $f['customer']->id,
        'invoice_id' => $invoice->id,
    ], $f['user'], true));

    expect((float) $invoice->fresh()->balance)->toBe(0.0)
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and((float) $applyResult['data']['applied'])->toBe(1000.0);

    // No new payment and no new journal - a pure subsidiary reclass.
    expect(Transaction::where('company_id', $f['company']->id)->count())->toBe($transactionCountBefore);
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe($paymentCountBefore);

    $statement = app(CustomerStatementService::class)->statement($f['customer']->fresh());
    expect($statement['available_credit'])->toBe(2000.0) // 3000 advance - 1000 just applied
        ->and($statement['closing_balance'])->toBe(-2000.0); // 1000 invoiced - 3000 paid
});

test('the buyer statement shows the payment, its allocations and remaining credit with a correct running balance', function () {
    $f = statementFixture();
    $i1 = allocationInvoice($f, 1000, '2026-09-01');
    $i2 = allocationInvoice($f, 500, '2026-09-05');

    dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 2000, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);

    $statement = app(CustomerStatementService::class)->statement($f['customer']->fresh());
    $paymentRow = collect($statement['rows'])->firstWhere('type', 'payment');
    expect($paymentRow)->not->toBeNull()
        ->and((float) $paymentRow['credit'])->toBe(2000.0)
        ->and((float) $paymentRow['balance'])->toBe(-500.0); // 1500 invoiced - 2000 paid
    expect($statement['closing_balance'])->toBe(-500.0)
        ->and($statement['available_credit'])->toBe(500.0);
});

test('allocations summing to more than the payment amount are rejected', function () {
    $f = statementFixture();
    $i1 = allocationInvoice($f, 1000, '2026-09-01');
    $i2 = allocationInvoice($f, 1000, '2026-09-05');

    expect(fn () => dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 500,
        'allocations' => [['invoice_id' => $i1->id, 'amount' => 400], ['invoice_id' => $i2->id, 'amount' => 400]],
        'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('an allocation naming an invoice of a different buyer is rejected', function () {
    $f = statementFixture();
    $otherCustomer = \App\Modules\Accounting\Models\Customer::create([
        'company_id' => $f['company']->id, 'customer_number' => 'C-2', 'name' => 'Other buyer',
        'base_currency' => 'PKR', 'ar_account_id' => $f['ar']->id, 'is_active' => true,
    ]);
    $mine = allocationInvoice($f, 1000, '2026-09-01');
    $theirs = Invoice::create([
        'company_id' => $f['company']->id, 'customer_id' => $otherCustomer->id,
        'invoice_number' => 'INV-OTHER-1', 'invoice_date' => '2026-09-01', 'due_date' => '2026-09-01',
        'status' => 'sent', 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => 1000, 'total_amount' => 1000, 'paid_amount' => 0, 'balance' => 1000,
    ]);

    expect(fn () => dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 1000,
        'allocations' => [['invoice_id' => $mine->id, 'amount' => 500], ['invoice_id' => $theirs->id, 'amount' => 500]],
        'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('an allocation naming an invoice of a different company is rejected', function () {
    $f = statementFixture();
    $other = statementFixture();
    $theirInvoice = allocationInvoice($other, 1000, '2026-09-01');

    expect(fn () => dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 500,
        'allocations' => [['invoice_id' => $theirInvoice->id, 'amount' => 500]],
        'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('a zero or negative payment amount is rejected', function () {
    $f = statementFixture();

    expect(fn () => dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 0, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(fn () => dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => -100, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('a fully-paid invoice cannot be allocated to', function () {
    $f = statementFixture();
    $invoice = allocationInvoice($f, 1000, '2026-09-01');
    dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 1000, 'method' => 'cash', 'date' => '2026-09-10',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);
    expect($invoice->fresh()->status)->toBe('paid');

    expect(fn () => dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 200,
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => 200]],
        'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('a cancelled invoice cannot be allocated to', function () {
    $f = statementFixture();
    $invoice = allocationInvoice($f, 1000, '2026-09-01');
    $invoice->update(['status' => 'cancelled']);

    expect(fn () => dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 200,
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => 200]],
        'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});
