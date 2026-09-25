<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;

// pendingDeliveryFixture(), pendingDeliveryBill() and pendingDeliveryPost() now live in
// PendingDeliveryFixtures.php, so BillDirectQuantityTest.php (a different test directory)
// can reuse them too -- a shared fixture belongs in its own *Fixtures.php file, never
// inside another *Test.php.
require_once __DIR__.'/PendingDeliveryFixtures.php';

test('posting a close receives a pending unpaid delivery dated the business date, so the dip shows no false gain', function () {
    $f = pendingDeliveryFixture();
    $bill = pendingDeliveryBill($f, '2026-09-15', 2000, 0, $f['tank']->id);

    // Opening 5000 + delivered 2000 - sold 100 = 6900. A dip of 6900 should
    // therefore post with zero tank variance once the delivery is received.
    $f['payload']['tank_readings'] = [[
        'tank_id' => $f['tank']->id, 'stick_reading' => 0, 'liters' => 6900,
    ]];

    $posted = pendingDeliveryPost($f);

    $bill->refresh();
    $line = $bill->lineItems()->sole();
    expect((float) $line->quantity_received)->toBe(2000.0);
    expect($bill->goods_received_at)->not->toBeNull();
    expect($bill->status)->not->toBe('paid');

    $movement = DB::table('inv.stock_movements')
        ->where('company_id', $f['company']->id)
        ->where('warehouse_id', $f['tank']->id)
        ->where('reference_type', 'acct.bills')
        ->where('reference_id', $bill->id)
        ->first();
    expect($movement)->not->toBeNull();
    expect((string) $movement->movement_date)->toBe('2026-09-15');
    expect((float) $movement->quantity)->toBe(2000.0);

    $close = Transaction::findOrFail($posted['transaction_id']);
    expect($close->metadata['tank_variances'] ?? [])->toBeEmpty();

    $deliveries = $close->metadata['deliveries_received'] ?? [];
    expect($deliveries)->toHaveCount(1);
    expect($deliveries[0]['bill_number'])->toBe($bill->bill_number);
    expect($deliveries[0]['bill_date'])->toBe('2026-09-15');
    expect((float) $deliveries[0]['litres'])->toBe(2000.0);
});

test('a bill dated the day after the close is not received or counted in that close', function () {
    $f = pendingDeliveryFixture();
    $bill = pendingDeliveryBill($f, '2026-09-16', 2000, 0, $f['tank']->id);

    // No delivery counted: opening 5000 - sold 100 = 4900 expected.
    $f['payload']['tank_readings'] = [[
        'tank_id' => $f['tank']->id, 'stick_reading' => 0, 'liters' => 4900,
    ]];

    $posted = pendingDeliveryPost($f);

    $bill->refresh();
    $line = $bill->lineItems()->sole();
    expect((float) $line->quantity_received)->toBe(0.0);
    expect($bill->goods_received_at)->toBeNull();

    $close = Transaction::findOrFail($posted['transaction_id']);
    expect($close->metadata['deliveries_received'] ?? [])->toBeEmpty();
    expect($close->metadata['tank_variances'] ?? [])->toBeEmpty();
});

test('a partly received bill only counts its remaining quantity toward the close', function () {
    $f = pendingDeliveryFixture();
    // 2000L on the bill, 500L already received earlier -- only the remaining
    // 1500L is still pending.
    $bill = pendingDeliveryBill($f, '2026-09-15', 2000, 500, $f['tank']->id);

    // Opening 5000 + remaining 1500 - sold 100 = 6400.
    $f['payload']['tank_readings'] = [[
        'tank_id' => $f['tank']->id, 'stick_reading' => 0, 'liters' => 6400,
    ]];

    $posted = pendingDeliveryPost($f);

    $bill->refresh();
    $line = $bill->lineItems()->sole();
    expect((float) $line->quantity_received)->toBe(2000.0); // 500 already + 1500 now
    expect($bill->goods_received_at)->not->toBeNull();

    $close = Transaction::findOrFail($posted['transaction_id']);
    expect($close->metadata['tank_variances'] ?? [])->toBeEmpty();

    $deliveries = $close->metadata['deliveries_received'] ?? [];
    expect($deliveries)->toHaveCount(1);
    expect((float) $deliveries[0]['litres'])->toBe(1500.0);
});

test('an unpaid bill\'s goods can be received', function () {
    $f = pendingDeliveryFixture();
    $bill = pendingDeliveryBill($f, '2026-09-10', 1000, 0, $f['tank']->id);
    expect($bill->status)->not->toBe('paid');

    app(CurrentCompany::class)->set($f['company']);
    $result = app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(\App\Services\CommandBus::class)->dispatch('bill.receive_goods', [
            'id' => $bill->id,
            'receipt_date' => '2026-09-10',
        ], $f['user'])
    );

    expect($result['fully_received'])->toBeTrue();
    $bill->refresh();
    expect($bill->goods_received_at)->not->toBeNull();
    expect($bill->status)->not->toBe('paid');
    expect((float) $bill->lineItems()->sole()->quantity_received)->toBe(1000.0);
});

test('receiving a delivery averages its cost with the stock already there, counted once', function () {
    $f = pendingDeliveryFixture();
    // As a fuel item leaves setup: the purchase rate sits in avg_cost, cost_price is still 0.
    $f['item']->update(['avg_cost' => 250, 'cost_price' => 0]);
    $bill = pendingDeliveryBill($f, '2026-09-10', 1000, 0, $f['tank']->id); // 1,000 L at 240

    app(CurrentCompany::class)->set($f['company']);
    app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(\App\Services\CommandBus::class)->dispatch('bill.receive_goods', [
            'id' => $bill->id,
            'receipt_date' => '2026-09-10',
        ], $f['user'])
    );

    // 5,000 L at 250 already in the tank, plus 1,000 L at 240: (1,250,000 + 240,000) / 6,000.
    // It used to read 240,000 / 7,000 = 34.29 - the old stock at a cost of 0, the new litres twice.
    expect(round((float) $f['item']->fresh()->cost_price, 4))->toBe(248.3333);
});
