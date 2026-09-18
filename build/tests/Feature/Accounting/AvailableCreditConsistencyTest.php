<?php

use App\Modules\Accounting\Models\PaymentAllocation;
use App\Modules\Accounting\Services\CustomerStatementService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;

/**
 * A payment's own page computes unapplied credit arithmetically (amount minus what was
 * applied to invoices), while the buyer's statement reads the stored null-invoice
 * allocation rows. Those two have to agree, or a buyer shows "Rs 40,000 unapplied credit"
 * on the payment and "available credit Rs 0" with no Apply Credit action on their
 * overview -- reported from browser testing, phase 4.3.
 *
 * Reuses statementFixture(), allocationInvoice() and dispatchPayment() from
 * PaymentAllocationTest.php, which Pest loads when the Accounting directory is run
 * together (module scope), per project convention.
 */
test('an overpayment leaves a stored on-account row, so the payment page and the statement agree', function () {
    $f = statementFixture();
    allocationInvoice($f, 10000, '2026-09-10');

    // Owes 10,000, pays 50,000: 40,000 has nowhere to go but the buyer's account.
    dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 50000, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);

    // What the payment page shows: amount minus what actually reached an invoice.
    $applied = (float) PaymentAllocation::where('company_id', $f['company']->id)
        ->whereNotNull('invoice_id')->sum('amount_allocated');
    $unappliedOnPaymentPage = round(50000 - $applied, 2);

    // What the buyer's overview shows.
    $statement = app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(CustomerStatementService::class)->statement($f['customer']->fresh())
    );

    expect($unappliedOnPaymentPage)->toBe(40000.0)
        ->and((float) $statement['available_credit'])->toBe($unappliedOnPaymentPage);
});

test('a payment that settles its invoices exactly leaves no phantom credit', function () {
    $f = statementFixture();
    allocationInvoice($f, 10000, '2026-09-10');

    dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 10000, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);

    $statement = app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(CustomerStatementService::class)->statement($f['customer']->fresh())
    );

    expect((float) $statement['available_credit'])->toBe(0.0);
});

test('every allocation of a payment sums back to the payment amount', function () {
    $f = statementFixture();
    allocationInvoice($f, 10000, '2026-09-10');

    dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 50000, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);

    // PostingService::postPayment relies on this invariant to post the payment at all,
    // and it is what makes the two surfaces agree by construction.
    $total = (float) PaymentAllocation::where('company_id', $f['company']->id)->sum('amount_allocated');
    expect($total)->toBe(50000.0);
});
