<?php

use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\VendorStatementService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Carbon\Carbon;

require_once __DIR__.'/SupplierAdvanceFixtures.php';

/**
 * A fuel station pays its oil company in advance, before the fuel and its bill arrive.
 * These cover BillPayment\CreateAction (allocations may fall short of the amount),
 * PostingService::postBillPayment (still posts the FULL amount either way),
 * VendorAdvanceService (applies the leftover to bills automatically and by hand),
 * BillPayment\UpdateAction and VoidAction, and VendorStatementService.
 */
beforeEach(function () {
    test()->travelTo(Carbon::parse('2026-09-24 10:00:00'));
});

test('a payment with no bills posts the full amount and is entirely unapplied', function () {
    $f = supplierAdvanceFixture();

    $payment = paySupplierAdvance($f, 100000, date: '2026-09-01');

    expect((float) $payment->amount)->toBe(100000.0)
        ->and($payment->allocations)->toHaveCount(0)
        ->and($payment->unappliedAmount())->toBe(100000.0);

    $transaction = Transaction::find($payment->transaction_id);
    $entries = $transaction->journalEntries;
    expect((float) $entries->sum('debit_amount'))->toBe(100000.0)
        ->and((float) $entries->sum('credit_amount'))->toBe(100000.0)
        ->and((float) $entries->where('account_id', $f['ap']->id)->sum('debit_amount'))->toBe(100000.0)
        ->and((float) $entries->where('account_id', $f['bank']->id)->sum('credit_amount'))->toBe(100000.0);

    // Right after the payment and before any bill exists, the vendor's statement already
    // shows the full advance: a -100,000 balance (the company is owed money, not the other
    // way round).
    $statement = app(CompanyContextService::class)->withContext($f['company'], fn () => app(VendorStatementService::class)->statement($f['vendor']->fresh()));
    expect((float) $statement['closing_balance'])->toBe(-100000.0);
});

test('a later bill for that vendor is auto-paid from the advance, with no new journal', function () {
    $f = supplierAdvanceFixture();
    $payment = paySupplierAdvance($f, 100000, date: '2026-09-01');

    $bill = postSupplierBill($f, 60000, date: '2026-09-05', billNumber: 'BILL-0001');

    $bill->refresh();
    expect($bill->status)->toBe('paid')
        ->and((float) $bill->paid_amount)->toBe(60000.0)
        ->and((float) $bill->balance)->toBe(0.0);

    $allocation = $bill->paymentAllocations()->sole();
    expect($allocation->bill_payment_id)->toBe($payment->id)
        ->and((float) $allocation->amount_allocated)->toBe(60000.0);

    // No new Transaction was posted for the auto-application -- the only journal entry
    // referencing this payment is still the one posted when the payment itself was recorded.
    expect(Transaction::where('reference_type', 'acct.bill_payments')->where('reference_id', $payment->id)->count())->toBe(1);

    $payment->refresh();
    expect($payment->unappliedAmount())->toBe(40000.0);
});

test('a second bill draws down what is left of the advance and stays partly owing', function () {
    $f = supplierAdvanceFixture();
    $payment = paySupplierAdvance($f, 100000, date: '2026-09-01');
    $bill1 = postSupplierBill($f, 60000, date: '2026-09-05', billNumber: 'BILL-0001');
    $bill2 = postSupplierBill($f, 50000, date: '2026-09-06', billNumber: 'BILL-0002');

    $bill2->refresh();
    expect($bill2->status)->toBe('partial')
        ->and((float) $bill2->paid_amount)->toBe(40000.0)
        ->and((float) $bill2->balance)->toBe(10000.0);

    $payment->refresh();
    expect($payment->unappliedAmount())->toBe(0.0);

    $statement = app(CompanyContextService::class)->withContext($f['company'], fn () => app(VendorStatementService::class)->statement($f['vendor']->fresh()));
    expect((float) $statement['closing_balance'])->toBe(10000.0);

    // Editing the amount is refused once the payment is matched across two bills.
    $edit = fn () => app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill_payment.update', [
        'id' => $payment->id,
        'amount' => 90000,
    ], $f['user'], true));
    expect($edit)->toThrow(\InvalidArgumentException::class, 'split across several bills');

    // Voiding restores both bills' balances and un-applies the advance.
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill_payment.void', [
        'id' => $payment->id,
    ], $f['user'], true));

    $bill1->refresh();
    $bill2->refresh();
    expect((float) $bill1->balance)->toBe(60000.0)
        ->and($bill1->status)->toBe('received')
        ->and((float) $bill2->balance)->toBe(50000.0)
        ->and($bill2->status)->toBe('received');

    expect(BillPayment::withTrashed()->find($payment->id)->trashed())->toBeTrue();
});

test('editing a partially-applied payment below what is already applied is refused', function () {
    $f = supplierAdvanceFixture();
    $payment = paySupplierAdvance($f, 100000, date: '2026-09-01');
    postSupplierBill($f, 60000, date: '2026-09-05', billNumber: 'BILL-0001');
    // Now $payment has exactly one allocation (60000) out of its 100000 amount -- a partial
    // application, not a fully-applied single-bill payment.

    $edit = fn () => app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill_payment.update', [
        'id' => $payment->id,
        'amount' => 50000,
    ], $f['user'], true));

    expect($edit)->toThrow(\InvalidArgumentException::class);

    $payment->refresh();
    expect((float) $payment->amount)->toBe(100000.0);
});
