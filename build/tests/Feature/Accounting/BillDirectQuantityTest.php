<?php

use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../FuelStation/PendingDeliveryFixtures.php';

/*
 * Fuel sometimes goes straight from the supplier's tanker to a customer and never
 * reaches the station's tank -- not in the meters, not in the dip. direct_quantity on
 * a bill line says how much of it did that; see docs/contracts/ap-schema.md and
 * PostingService::buildBillEntries, ReceiveGoodsAction and DailyCloseService::
 * pendingDeliveries.
 */
test('a 10,000 L line with 4,000 direct posts 4/10 to COGS and 6/10 to inventory', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = pendingDeliveryFixture();
    $bill = pendingDeliveryBill($f, '2026-09-15', 10000, 0, $f['tank']->id, 'BILL-0001', 4000);

    $transaction = app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(\App\Modules\Accounting\Services\PostingService::class)->postBill($bill->fresh(['lineItems', 'vendor']))
    );

    $entries = $transaction->journalEntries()->get(['account_id', 'debit_amount', 'credit_amount']);

    $cogsDebit = (float) $entries->where('account_id', $f['accounts']['5100']->id)->sum('debit_amount');
    $inventoryDebit = (float) $entries->where('account_id', $f['accounts']['1200']->id)->sum('debit_amount');
    $apCredit = (float) $entries->where('account_id', '!=', $f['accounts']['5100']->id)
        ->where('account_id', '!=', $f['accounts']['1200']->id)->sum('credit_amount');

    // 10,000 L @ 240 = 2,400,000. 4,000/10,000 direct = 960,000 to COGS, 1,440,000 to inventory.
    expect($cogsDebit)->toBe(960000.0);
    expect($inventoryDebit)->toBe(1440000.0);
    expect($apCredit)->toBe(2400000.0);
});

test("receiving takes only 6,000 L into the tank and marks the bill fully received", function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = pendingDeliveryFixture();
    $bill = pendingDeliveryBill($f, '2026-09-15', 10000, 0, $f['tank']->id, 'BILL-0001', 4000);

    app(CurrentCompany::class)->set($f['company']);
    $result = app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(CommandBus::class)->dispatch('bill.receive_goods', [
            'id' => $bill->id,
            'receipt_date' => '2026-09-15',
        ], $f['user'])
    );

    expect($result['fully_received'])->toBeTrue();

    $line = $bill->fresh(['lineItems'])->lineItems->sole();
    expect((float) $line->quantity_received)->toBe(6000.0);
    expect($bill->fresh()->goods_received_at)->not->toBeNull();

    $movement = DB::table('inv.stock_movements')
        ->where('company_id', $f['company']->id)
        ->where('reference_type', 'acct.bills')
        ->where('reference_id', $bill->id)
        ->first();
    expect((float) $movement->quantity)->toBe(6000.0);
});

test("a close's pendingDeliveries shows 6,000", function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = pendingDeliveryFixture();
    pendingDeliveryBill($f, '2026-09-15', 10000, 0, $f['tank']->id, 'BILL-0001', 4000);

    $pending = app(DailyCloseService::class)->pendingDeliveries(
        $f['company']->id, $f['tank']->id, $f['item']->id, '2026-09-14', '2026-09-15'
    );

    expect($pending)->toHaveCount(1);
    expect((float) $pending[0]['remaining'])->toBe(6000.0);
});

test('direct_quantity above the line quantity is refused', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = pendingDeliveryFixture();

    app(CurrentCompany::class)->set($f['company']);
    $dispatch = fn () => app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(CommandBus::class)->dispatch('bill.create', [
            'vendor_id' => $f['vendor']->id,
            'bill_date' => '2026-09-15',
            'currency' => 'PKR',
            'base_currency' => 'PKR',
            'status' => 'draft',
            'line_items' => [[
                'item_id' => $f['item']->id,
                'warehouse_id' => $f['tank']->id,
                'description' => 'Petrol delivery',
                'quantity' => 1000,
                'direct_quantity' => 1500,
                'unit_price' => 240,
            ]],
        ], $f['user'])
    );

    expect($dispatch)->toThrow(ValidationException::class);
});

test('a fully-direct line has nothing to receive', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = pendingDeliveryFixture();
    $bill = pendingDeliveryBill($f, '2026-09-15', 1000, 0, $f['tank']->id, 'BILL-0001', 1000);

    app(CurrentCompany::class)->set($f['company']);
    $dispatch = fn () => app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(CommandBus::class)->dispatch('bill.receive_goods', [
            'id' => $bill->id,
            'receipt_date' => '2026-09-15',
        ], $f['user'])
    );

    expect($dispatch)->toThrow(\InvalidArgumentException::class);

    $line = $bill->fresh(['lineItems'])->lineItems->sole();
    expect((float) $line->quantity_received)->toBe(0.0);
    expect($line->isFullyReceived())->toBeTrue();
});

test('the bill form request keeps direct_quantity', function () {
    // It was missing from StoreBillRequest, so validated() silently dropped it and the
    // whole line was received into the tank.
    $f = pendingDeliveryFixture();
    app(CurrentCompany::class)->set($f['company']);
    $payload = [
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-15',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'line_items' => [[
            'item_id' => $f['item']->id,
            'warehouse_id' => $f['tank']->id,
            'description' => 'Diesel delivery',
            'quantity' => 994,
            'direct_quantity' => 994,
            'unit_price' => 369,
        ]],
    ];
    $request = \App\Modules\Accounting\Http\Requests\StoreBillRequest::create('/bills', 'POST', $payload);
    $request->setContainer(app());

    $validated = \Illuminate\Support\Facades\Validator::make($payload, $request->rules())->validated();

    expect((float) $validated['line_items'][0]['direct_quantity'])->toBe(994.0);
});

test('marking received litres as sold directly takes them back out of the tank', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = pendingDeliveryFixture();
    $bill = pendingDeliveryBill($f, '2026-09-15', 10000, 0, $f['tank']->id, 'BILL-0001', 0);

    app(CurrentCompany::class)->set($f['company']);
    $run = fn (string $command, array $params) => app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(CommandBus::class)->dispatch($command, $params, $f['user'])
    );
    $run('bill.receive_goods', ['id' => $bill->id, 'receipt_date' => '2026-09-15']);
    $level = fn () => (float) DB::table('inv.stock_levels')->where('warehouse_id', $f['tank']->id)->where('item_id', $f['item']->id)->value('quantity');
    $before = $level();

    $run('bill.update', ['id' => $bill->id, 'line_items' => [[
        'item_id' => $f['item']->id,
        'warehouse_id' => $f['tank']->id,
        'description' => 'Petrol delivery',
        'quantity' => 10000,
        'direct_quantity' => 4000,
        'unit_price' => 240,
    ]]]);

    $line = $bill->fresh(['lineItems'])->lineItems->sole();
    expect((float) $line->direct_quantity)->toBe(4000.0)
        ->and((float) $line->quantity_received)->toBe(6000.0)
        ->and($level())->toBe($before - 4000)
        ->and($line->isFullyReceived())->toBeTrue();
});
