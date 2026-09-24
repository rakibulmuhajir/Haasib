<?php

use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Carbon\Carbon;

require_once __DIR__.'/BillPaymentEditFixtures.php';

/**
 * A bill payment entered dated today (24 Sep) instead of the day it was
 * actually paid (1 Sep) previously had no way to be corrected other than
 * voiding it. These cover BillPayment\UpdateAction, BillPaymentController
 * and bill-payments/Edit.vue.
 */
beforeEach(function () {
    test()->travelTo(Carbon::parse('2026-09-24 10:00:00'));
});

test('changing the date reverses and reposts on the new date and the bill stays paid', function () {
    $f = billPaymentEditFixture();
    $payment = payBillForEditTest($f, $f['bill'], date: '2026-09-01');
    $oldTransactionId = $payment->transaction_id;

    $response = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bill-payments/{$payment->id}", [
        'payment_date' => '2026-09-02',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();
    expect(session('error'))->toBeNull();

    $payment->refresh();
    expect($payment->payment_date->toDateString())->toBe('2026-09-02')
        ->and($payment->transaction_id)->not->toBe($oldTransactionId);

    $oldTransaction = Transaction::find($oldTransactionId);
    expect($oldTransaction->reversed_by_id)->not->toBeNull();

    $newTransaction = Transaction::find($payment->transaction_id);
    expect($newTransaction->transaction_date->toDateString())->toBe('2026-09-02');

    $bill = $f['bill']->fresh();
    expect($bill->status)->toBe('paid')
        ->and((float) $bill->balance)->toBe(0.0)
        ->and((float) $bill->paid_amount)->toBe(20000.0);
});

test('changing the amount within the bill balance updates the bill balance and status', function () {
    $f = billPaymentEditFixture();
    $payment = payBillForEditTest($f, $f['bill'], amount: 15000, date: '2026-09-01');

    $f['bill']->refresh();
    expect($f['bill']->status)->toBe('partial')
        ->and((float) $f['bill']->balance)->toBe(5000.0);

    $response = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bill-payments/{$payment->id}", [
        'amount' => 18000,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();
    expect(session('error'))->toBeNull();

    $payment->refresh();
    expect((float) $payment->amount)->toBe(18000.0);

    $bill = $f['bill']->fresh();
    expect((float) $bill->paid_amount)->toBe(18000.0)
        ->and((float) $bill->balance)->toBe(2000.0)
        ->and($bill->status)->toBe('partial');

    $transaction = Transaction::find($payment->transaction_id);
    $entries = $transaction->journalEntries;
    expect((float) $entries->sum('debit_amount'))->toBe(18000.0)
        ->and((float) $entries->sum('credit_amount'))->toBe(18000.0);
});

test('an amount above the bill balance is refused', function () {
    $f = billPaymentEditFixture();
    $payment = payBillForEditTest($f, $f['bill'], amount: 15000, date: '2026-09-01');

    $response = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bill-payments/{$payment->id}", [
        'amount' => 25000,
    ]);

    expect(session('error'))->toContain("That's more than is owed on {$f['bill']->bill_number}");

    $payment->refresh();
    expect((float) $payment->amount)->toBe(15000.0);
});

test('changing the paid-from account moves the credit to the new account', function () {
    $f = billPaymentEditFixture();
    $payment = payBillForEditTest($f, $f['bill'], date: '2026-09-01', account: $f['bank']);
    $oldTransactionId = $payment->transaction_id;

    $response = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bill-payments/{$payment->id}", [
        'payment_account_id' => $f['cash']->id,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();
    expect(session('error'))->toBeNull();

    $payment->refresh();
    expect($payment->payment_account_id)->toBe($f['cash']->id)
        ->and($payment->transaction_id)->not->toBe($oldTransactionId);

    $newEntries = Transaction::find($payment->transaction_id)->journalEntries;
    expect((float) $newEntries->where('account_id', $f['cash']->id)->sum('credit_amount'))->toBe(20000.0)
        ->and((float) $newEntries->where('account_id', $f['bank']->id)->sum('credit_amount'))->toBe(0.0);

    $oldTransaction = Transaction::find($oldTransactionId);
    expect($oldTransaction->reversed_by_id)->not->toBeNull();
});

test('a notes-only edit leaves the journal alone', function () {
    $f = billPaymentEditFixture();
    $payment = payBillForEditTest($f, $f['bill'], date: '2026-09-01');
    $oldTransactionId = $payment->transaction_id;

    $response = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bill-payments/{$payment->id}", [
        'notes' => 'Paid by the branch manager',
        'reference_number' => 'REF-99',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();
    expect(session('error'))->toBeNull();

    $payment->refresh();
    expect($payment->notes)->toBe('Paid by the branch manager')
        ->and($payment->reference_number)->toBe('REF-99')
        ->and($payment->transaction_id)->toBe($oldTransactionId);
});

test('a void payment cannot be edited', function () {
    $f = billPaymentEditFixture();
    $payment = payBillForEditTest($f, $f['bill'], date: '2026-09-01');

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill_payment.void', [
        'id' => $payment->id,
    ], $f['user'], true));

    $response = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bill-payments/{$payment->id}", [
        'notes' => 'Should not be allowed',
    ]);

    $response->assertNotFound();
});

test('a payment dated in a closed accounting period cannot be edited', function () {
    $f = billPaymentEditFixture();
    $payment = payBillForEditTest($f, $f['bill'], date: '2026-09-01');
    $f['sepPeriod']->update(['is_closed' => true]);

    $response = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bill-payments/{$payment->id}", [
        'notes' => 'Trying to edit inside a closed period',
    ]);

    $response->assertSessionHasErrors('date');

    $payment->refresh();
    expect($payment->notes)->not->toBe('Trying to edit inside a closed period');
});

test('a payment split across several bills refuses an amount change but allows other edits', function () {
    $f = billPaymentEditFixture();

    $result = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill_payment.create', [
        'vendor_id' => $f['vendor']->id,
        'payment_date' => '2026-09-01',
        'amount' => 6000,
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_method' => 'bank_transfer',
        'payment_account_id' => $f['bank']->id,
        'allocations' => [
            ['bill_id' => $f['bill']->id, 'amount_allocated' => 5000],
            ['bill_id' => $f['bill2']->id, 'amount_allocated' => 1000],
        ],
    ], $f['user'], true));

    $payment = BillPayment::find($result['data']['id']);

    $refusal = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bill-payments/{$payment->id}", [
        'amount' => 7000,
    ]);
    expect(session('error'))->toContain('split across several bills');

    $allowed = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bill-payments/{$payment->id}", [
        'notes' => 'Reference note only',
    ]);
    $allowed->assertSessionHasNoErrors()->assertRedirect();
    expect(session('error'))->toBeNull();

    $payment->refresh();
    expect($payment->notes)->toBe('Reference note only');
});
