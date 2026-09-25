<?php

use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\DailyCloseReconciliationService;
use App\Modules\FuelStation\Services\DailyCloseService;

require_once __DIR__.'/PendingDeliveryFixtures.php';

/*
 * A "Pay supplier" row entered directly in the Daily Close's Cash Out section (see
 * DailyCloseController@create's Supplier Bill Payments section, DailyClosePaySupplierService
 * and DailyCloseService::processDailyClose) becomes an ordinary bill_payment.create dated the
 * business date, allocated to the vendor's open bills oldest-first up to the row's amount —
 * the remainder is left on the vendor as an advance (BillPayment::unappliedAmount).
 */
function paySupplierOpenBill(array $f, float $balance, string $billNumber = 'BILL-0001'): Bill
{
    $bill = Bill::create([
        'company_id' => $f['company']->id,
        'vendor_id' => $f['vendor']->id,
        'bill_number' => $billNumber,
        'bill_date' => '2026-09-10',
        'due_date' => '2026-09-10',
        'status' => 'received',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'subtotal' => $balance,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => $balance,
        'paid_amount' => 0,
        'balance' => $balance,
        'base_amount' => $balance,
        'created_by_user_id' => $f['user']->id,
    ]);

    BillLineItem::create([
        'company_id' => $f['company']->id,
        'bill_id' => $bill->id,
        'line_number' => 1,
        'description' => 'Fuel delivery',
        'quantity' => 1,
        'unit_price' => $balance,
        'tax_rate' => 0,
        'discount_rate' => 0,
        'line_total' => $balance,
        'tax_amount' => 0,
        'total' => $balance,
        'expense_account_id' => $f['accounts']['5100']->id,
        'created_by_user_id' => $f['user']->id,
    ]);

    return $bill->fresh();
}

test('a Pay Supplier row from the cash drawer pays the open bill and holds the rest as an advance', function () {
    $close = fn (array $f) => app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    // An ordinary day, for comparison — same fixture, no pay-supplier row.
    $plain = pendingDeliveryFixture();
    $plainVariance = (float) Transaction::findOrFail($close($plain)['transaction_id'])->metadata['variance'];

    $f = pendingDeliveryFixture();
    $bill = paySupplierOpenBill($f, 60000);

    $f['payload']['pay_suppliers'] = [[
        'vendor_id' => $f['vendor']->id,
        'amount' => 100000,
        'payment_account_id' => $f['accounts']['1050']->id, // station cash drawer
        'reference' => 'Cash paid to depot',
    ]];
    // There was 100,000 more cash in the drawer to start the day than the plain fixture
    // (this vendor keeps the station stocked on cash terms); the counted closing figure is
    // unchanged, so the whole 100,000 leaving for this payment must be expected, not a variance.
    $f['payload']['opening_cash'] += 100000;

    $result = $close($f);
    $posted = Transaction::findOrFail($result['transaction_id']);

    $bill->refresh();
    expect((float) $bill->balance)->toBe(0.0)
        ->and($bill->status)->toBe('paid');

    $payment = BillPayment::where('company_id', $f['company']->id)->where('vendor_id', $f['vendor']->id)->sole();
    expect((float) $payment->amount)->toBe(100000.0)
        ->and($payment->unappliedAmount())->toBe(40000.0)
        ->and($payment->payment_date->toDateString())->toBe('2026-09-15');

    // Cash dropped by exactly 100,000 once: the plain close's variance is unaffected because
    // closing_cash was reduced by the same amount the drawer actually paid out.
    expect((float) $posted->metadata['variance'])->toBe($plainVariance)
        ->and((float) $posted->metadata['cash_pay_suppliers'])->toBe(100000.0)
        ->and((float) $posted->metadata['pay_suppliers_total'])->toBe(100000.0);

    $detail = $posted->metadata['pay_supplier_details'][0];
    expect((float) $detail['applied_to_bills'])->toBe(60000.0)
        ->and((float) $detail['advance_amount'])->toBe(40000.0)
        ->and($detail['affects_cash_drawer'])->toBeTrue();

    // Not listed as "recorded on other screens" / external activity — it is this close's own posting.
    $view = app(DailyCloseReconciliationService::class)->view($posted);
    expect($view['activity'])->toBe([]);
});

test('a Pay Supplier row paid from a bank account does not change drawer cash', function () {
    $f = pendingDeliveryFixture();
    $bill = paySupplierOpenBill($f, 60000);

    $f['payload']['pay_suppliers'] = [[
        'vendor_id' => $f['vendor']->id,
        'amount' => 60000,
        'payment_account_id' => $f['accounts']['1020']->id, // bank, not the cash drawer
        'reference' => 'Bank transfer to depot',
    ]];
    // closing_cash left unchanged: a bank payment never touches the drawer.

    $result = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $posted = Transaction::findOrFail($result['transaction_id']);

    $bill->refresh();
    expect((float) $bill->balance)->toBe(0.0);

    expect((float) ($posted->metadata['cash_pay_suppliers'] ?? 0))->toBe(0.0)
        ->and((float) $posted->metadata['pay_suppliers_total'])->toBe(60000.0)
        ->and((float) $posted->metadata['variance'])->toBe(0.0);

    $detail = $posted->metadata['pay_supplier_details'][0];
    expect($detail['affects_cash_drawer'])->toBeFalse();
});
