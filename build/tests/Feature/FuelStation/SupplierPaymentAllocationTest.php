<?php

use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPaymentAllocation;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/PendingDeliveryFixtures.php';

/*
 * A supplier payment from the close pays the bill picked on it first; what is left (or all of it,
 * with no bill picked) follows the station setting: the oldest bills first, or kept as credit.
 * A payment can be taken off a bill again, and the money stays with the supplier as credit.
 */
function supplierAllocationFixture(): array
{
    $f = pendingDeliveryFixture();
    $f['old'] = pendingDeliveryBill($f, '2026-09-10', 100, 0, null, 'BILL-OLD'); // 24,000
    $f['new'] = pendingDeliveryBill($f, '2026-09-20', 50, 0, null, 'BILL-NEW');  // 12,000
    foreach (['old', 'new'] as $k) {
        $f[$k]->update(['status' => 'received']);
    }

    return $f;
}

test('a picked bill is paid first and the rest goes to the oldest bills', function () {
    $f = supplierAllocationFixture();
    $r = app(DailyCloseService::class)->allocateOldestFirst($f['company']->id, $f['vendor']->id, 30000, $f['new']->id);

    expect($r['allocations'])->toBe([
        ['bill_id' => $f['new']->id, 'amount_allocated' => 12000.0],
        ['bill_id' => $f['old']->id, 'amount_allocated' => 18000.0],
    ])->and($r['advance_amount'])->toBe(0.0);
});

test('with keep as credit, only the picked bill is paid and the rest stays as credit', function () {
    $f = supplierAllocationFixture();
    DB::table('fuel.station_settings')->where('company_id', $f['company']->id)->update(['supplier_payment_allocation' => 'keep_as_credit']);
    $service = app(DailyCloseService::class);

    $picked = $service->allocateOldestFirst($f['company']->id, $f['vendor']->id, 30000, $f['new']->id);
    expect($picked['allocations'])->toBe([['bill_id' => $f['new']->id, 'amount_allocated' => 12000.0]])
        ->and($picked['advance_amount'])->toBe(18000.0);

    $none = $service->allocateOldestFirst($f['company']->id, $f['vendor']->id, 5000);
    expect($none['allocations'])->toBe([])->and($none['advance_amount'])->toBe(5000.0);
});

test('a payment taken off a bill leaves the bill owed again and the money as credit', function () {
    $f = supplierAllocationFixture();
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    $payment = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill_payment.create', [
        'vendor_id' => $f['vendor']->id, 'payment_date' => '2026-09-21', 'amount' => 24000, 'currency' => 'PKR', 'base_currency' => 'PKR',
        'payment_method' => 'cash', 'payment_account_id' => $f['accounts']['1050']->id, 'ap_account_id' => $f['vendor']->ap_account_id,
        'allocations' => [['bill_id' => $f['old']->id, 'amount_allocated' => 24000]],
    ], $f['user'], true));
    expect((float) $f['old']->fresh()->balance)->toBe(0.0);

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill.unapply_payment', [
        'id' => $f['old']->id, 'payment_id' => $payment['data']['id'],
    ], $f['user'], true));

    $old = $f['old']->fresh();
    expect((float) $old->balance)->toBe(24000.0)
        ->and((float) $old->paid_amount)->toBe(0.0)
        ->and($old->status)->toBe('received')
        ->and(BillPaymentAllocation::where('bill_id', $old->id)->exists())->toBeFalse()
        ->and(app(\App\Modules\Accounting\Services\VendorAdvanceService::class)->totalUnapplied($f['company']->id, $f['vendor']->id))->toBe(24000.0);
});
