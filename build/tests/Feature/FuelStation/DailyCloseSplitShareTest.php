<?php

use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Services\CorrectionService;
use App\Modules\FuelStation\Http\Controllers\DailyCloseController;
use App\Modules\Accounting\Models\Transaction;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * A credit sale split between customers by a correction shows on its day's close as each
 * owner's share -- the same total, but not the whole sale on the first customer.
 */
test('a split credit sale shows on the close as each owner\'s share', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-20 10:00:00'));
    $f = creditCloseFixture();
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    $close = creditClosePost($f);
    $metadata = Transaction::findOrFail($close['transaction_id'])->metadata;
    $invoice = Invoice::findOrFail($metadata['credit_sale_details'][0]['invoice_id']);
    $other = Customer::create(['company_id' => $f['company']->id, 'customer_number' => 'C-2', 'name' => 'Second owner', 'base_currency' => 'PKR', 'ar_account_id' => $f['accounts']['1100']->id, 'is_active' => true]);

    app(CorrectionService::class)->invoiceSplit($invoice, [
        ['customer_id' => $other->id, 'amount' => 2000],
        ['customer_id' => $f['customer']->id, 'amount' => 4000],
    ], 'Two owners', true);

    $method = new ReflectionMethod(DailyCloseController::class, 'creditSaleInvoicesFor');
    $rows = collect($method->invoke(app(DailyCloseController::class), $f['company']->id, $metadata));

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['customer_name'])->toBe('Second owner')
        ->and((float) $rows[0]['amount'])->toBe(2000.0)
        ->and($rows[1]['customer_name'])->toBe('Truck owner')
        ->and((float) $rows[1]['amount'])->toBe(4000.0)
        ->and($rows[0]['split_from'])->toBe($invoice->invoice_number)   // split in full: only the shares show
        ->and((float) $rows->sum('amount'))->toBe(6000.0);
});
