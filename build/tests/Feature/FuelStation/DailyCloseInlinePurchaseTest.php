<?php

use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Services\DailyCloseReconciliationService;
use App\Modules\FuelStation\Services\DailyCloseService;
use Illuminate\Support\Facades\DB;

// Reuses creditCloseFixture() from DailyCloseCreditSalesTest.php (company, chart of
// accounts, a fuel item/tank/nozzle) — only available when the whole FuelStation
// directory is run together (module scope), per project convention.

function inlinePurchaseFixture(): array
{
    $f = creditCloseFixture();
    // DailyCloseEntryService::purchase() dispatches bill.create / bill_payment.create
    // through the CommandBus, which enforces permissions against the acting user;
    // creditCloseFixture()'s user has no company role by default.
    app(\App\Services\CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    DB::table('auth.company_user')->insert(['company_id' => $f['company']->id, 'user_id' => $f['user']->id, 'role' => 'owner', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    app(\App\Services\CompanyContextService::class)->withContext($f['company'], fn () => app(\App\Services\CompanyContextService::class)->assignRole($f['user'], 'owner'));

    $ap = \App\Modules\Accounting\Models\Account::create([
        'company_id' => $f['company']->id, 'code' => '2000', 'name' => 'Accounts Payable',
        'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_active' => true,
    ]);
    $f['vendor'] = Vendor::create([
        'company_id' => $f['company']->id,
        'vendor_number' => 'V-1',
        'name' => 'Fuel Supplier',
        'base_currency' => 'PKR',
        'is_active' => true,
        'ap_account_id' => $ap->id,
    ]);
    // delivery_mode deliberately left at its real default ('requires_receiving', see
    // App\Modules\FuelStation\Actions\Product\SetupAction) — an inline close purchase
    // must receive goods regardless of the item's delivery_mode.
    \App\Modules\Inventory\Models\Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')
        ->update(['asset_account_id' => $f['accounts']['1200']->id, 'fuel_category' => 'petrol', 'delivery_mode' => 'requires_receiving']);
    $f['payload']['purchases'] = [];
    $f['payload']['credit_sales'] = [];

    return $f;
}

function inlinePurchasePost(array $f): array
{
    // DailyCloseEntryService::purchase() checks bill.create/bill_payment.create permissions
    // against the current company (Spatie team) context, so it must stay set for the call.
    app(\App\Services\CurrentCompany::class)->set($f['company']);

    return app(\App\Services\CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user'])
    );
}

test('parking with a purchase row keeps the draft only: no bill exists', function () {
    $f = inlinePurchaseFixture();
    $f['payload']['purchases'] = [[
        'supplier_id' => $f['vendor']->id,
        'item_id' => \App\Modules\Inventory\Models\Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->value('id'),
        'quantity' => 500,
        'unit_cost' => 240,
        'tank_id' => \App\Modules\Inventory\Models\Warehouse::where('company_id', $f['company']->id)->where('code', 'T1')->value('id'),
        'supplier_invoice_number' => 'SUP-1',
    ]];

    app(DailyCloseReconciliationService::class)->park($f['company']->id, $f['payload'], $f['user']->id);

    expect(Bill::where('company_id', $f['company']->id)->count())->toBe(0);
    $draft = app(DailyCloseReconciliationService::class)->draft($f['company']->id, $f['payload']['date']);
    expect($draft['purchases'])->toHaveCount(1);
});

test('posting a fuel purchase creates one canonical bill, receives stock into the tank, and leaves cash unchanged when unpaid', function () {
    $f = inlinePurchaseFixture();
    $item = \App\Modules\Inventory\Models\Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();
    $tank = \App\Modules\Inventory\Models\Warehouse::where('company_id', $f['company']->id)->where('code', 'T1')->sole();
    $f['payload']['credit_sales'] = [];
    $f['payload']['closing_cash'] = 31000; // card-only balance, see DailyCloseCreditSalesTest
    $f['payload']['purchases'] = [[
        'supplier_id' => $f['vendor']->id,
        'item_id' => $item->id,
        'quantity' => 500,
        'unit_cost' => 240,
        'tank_id' => $tank->id,
        'supplier_invoice_number' => 'SUP-1',
    ]];

    $posted = inlinePurchasePost($f);

    $bill = Bill::where('company_id', $f['company']->id)->sole();
    expect($bill->vendor_id)->toBe($f['vendor']->id)
        ->and($bill->bill_date->toDateString())->toBe('2026-09-15')
        ->and((float) $bill->total_amount)->toBe(120000.0)
        ->and($bill->status)->toBe('received');

    $movement = DB::table('inv.stock_movements')
        ->where('company_id', $f['company']->id)
        ->where('warehouse_id', $tank->id)
        ->where('reference_type', 'acct.bills')
        ->first();
    expect($movement)->not->toBeNull()
        ->and((float) $movement->quantity)->toBe(500.0);

    $snapshot = $posted['metadata']['posting_snapshot'];
    $purchaseSource = collect($snapshot['sources'])->firstWhere('source', 'close_purchase');
    expect($purchaseSource)->not->toBeNull();

    // Unpaid: the bill is a payable, not a cash outflow. Expected cash is untouched by it.
    expect((float) $snapshot['totals']['money_out'])->toBe(9000.0);

    // The receipt must set quantity_received on the bill line, or supplier reconciliation
    // and "pending receipts" reporting would still see this bill as unreceived.
    $line = $bill->lineItems()->sole();
    expect((float) $line->quantity_received)->toBe(500.0)
        ->and((float) $line->quantity_received)->toBe((float) $line->quantity)
        ->and($bill->goods_received_at)->not->toBeNull();
});

test('an inline purchase actually receives stock into the tank, so the close only reconciles the real variance, not the whole delivery', function () {
    $f = inlinePurchaseFixture();
    $item = \App\Modules\Inventory\Models\Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();
    $tank = \App\Modules\Inventory\Models\Warehouse::where('company_id', $f['company']->id)->where('code', 'T1')->sole();

    // Yesterday's closing dip establishes the opening baseline: 1000 L already in the tank.
    \App\Modules\FuelStation\Models\TankReading::create([
        'company_id' => $f['company']->id,
        'tank_id' => $tank->id,
        'item_id' => $item->id,
        'reading_date' => '2026-09-14',
        'reading_type' => 'closing',
        'dip_measurement_liters' => 1000,
        'system_calculated_liters' => 1000,
        'variance_liters' => 0,
    ]);
    \App\Modules\Inventory\Models\StockLevel::create([
        'company_id' => $f['company']->id,
        'warehouse_id' => $tank->id,
        'item_id' => $item->id,
        'quantity' => 1000,
    ]);

    // No pump sales today; only the purchase and the closing dip matter for this assertion.
    $f['payload']['nozzle_readings'][0]['opening_electronic'] = 0;
    $f['payload']['nozzle_readings'][0]['closing_electronic'] = 0;
    $f['payload']['nozzle_readings'][0]['liters_sold'] = 0;
    $f['payload']['credit_sales'] = [];
    $f['payload']['payment_receipts'] = [];
    $f['payload']['closing_cash'] = 10000;
    $f['payload']['purchases'] = [[
        'supplier_id' => $f['vendor']->id,
        'item_id' => $item->id,
        'quantity' => 2000,
        'unit_cost' => 240,
        'tank_id' => $tank->id,
        'supplier_invoice_number' => 'SUP-2000',
    ]];
    // Physical dip today: 1000 opening + 2000 received - 300 unexplained loss = 2700.
    $f['payload']['tank_readings'] = [[
        'tank_id' => $tank->id,
        'liters' => 2700,
    ]];

    $posted = inlinePurchasePost($f);

    // The delivery must be received exactly once: one +2000 movement into this tank,
    // referenced to the bill (not to the daily close).
    $receiptMovements = DB::table('inv.stock_movements')
        ->where('company_id', $f['company']->id)
        ->where('warehouse_id', $tank->id)
        ->where('item_id', $item->id)
        ->where('reference_type', 'acct.bills')
        ->get();
    expect($receiptMovements)->toHaveCount(1)
        ->and((float) $receiptMovements->first()->quantity)->toBe(2000.0);

    $bill = Bill::where('company_id', $f['company']->id)->sole();
    expect((float) $bill->lineItems()->sole()->quantity_received)->toBe(2000.0);

    // The close's own reconciling movement is now the small real variance (dip 2700 vs
    // ledger 1000 opening + 2000 received = 3000 -> -300), not the netted +1700 the bug
    // produced when the 2000 L delivery was silently absorbed as "adjustment_in".
    $reconciling = DB::table('inv.stock_movements')
        ->where('company_id', $f['company']->id)
        ->where('warehouse_id', $tank->id)
        ->where('reference_type', 'fuel.daily_close')
        ->get();
    expect($reconciling)->toHaveCount(1)
        ->and($reconciling->first()->movement_type)->toBe('adjustment_out')
        ->and((float) $reconciling->first()->quantity)->toBe(-300.0);

    $snapshot = $posted['metadata']['posting_snapshot'];
    $tankSnapshot = collect($snapshot['tank_variances'] ?? [])->first();
    // Expected litres reflect the receipt counted exactly once: 1000 + 2000 - 0 = 3000.
    $tankReading = \App\Modules\FuelStation\Models\TankReading::where('company_id', $f['company']->id)
        ->where('tank_id', $tank->id)->where('reading_date', '2026-09-15')->sole();
    expect((float) $tankReading->system_calculated_liters)->toBe(3000.0);
});

test('paying the purchase now from cash reduces expected cash by its amount', function () {
    $f = inlinePurchaseFixture();
    $item = \App\Modules\Inventory\Models\Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();
    $tank = \App\Modules\Inventory\Models\Warehouse::where('company_id', $f['company']->id)->where('code', 'T1')->sole();
    $f['payload']['credit_sales'] = [];
    $f['payload']['closing_cash'] = 31000 - 5000;
    $f['payload']['purchases'] = [[
        'supplier_id' => $f['vendor']->id,
        'item_id' => $item->id,
        'quantity' => 20,
        'unit_cost' => 250,
        'tank_id' => $tank->id,
        'paid_now' => true,
    ]];

    $posted = inlinePurchasePost($f);

    $payment = BillPayment::where('company_id', $f['company']->id)->sole();
    expect((float) $payment->amount)->toBe(5000.0)
        ->and($payment->transaction_id)->not->toBeNull();

    // Paid now: expected cash drops by the payment amount (9000 card - 5000 paid = 4000 net money_out... but
    // money_out sums card receipts + cash bill payment here).
    $snapshot = $posted['metadata']['posting_snapshot'];
    expect((float) $snapshot['totals']['money_out'])->toBe(14000.0)
        ->and((float) $snapshot['totals']['expected_closing'])->toBe(26000.0);
});

test('a purchase paid inside the close does not double count as a pending bill payment', function () {
    $f = inlinePurchaseFixture();
    $item = \App\Modules\Inventory\Models\Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();
    $tank = \App\Modules\Inventory\Models\Warehouse::where('company_id', $f['company']->id)->where('code', 'T1')->sole();
    $f['payload']['credit_sales'] = [];
    $f['payload']['closing_cash'] = 31000 - 5000;
    $f['payload']['purchases'] = [[
        'supplier_id' => $f['vendor']->id,
        'item_id' => $item->id,
        'quantity' => 20,
        'unit_cost' => 250,
        'tank_id' => $tank->id,
        'paid_now' => true,
    ]];

    inlinePurchasePost($f);

    expect(BillPayment::where('company_id', $f['company']->id)->whereNull('transaction_id')->count())->toBe(0);
});

test('a fuel purchase without a tank is rejected before posting', function () {
    $f = inlinePurchaseFixture();
    $item = \App\Modules\Inventory\Models\Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();
    $f['payload']['purchases'] = [[
        'supplier_id' => $f['vendor']->id,
        'item_id' => $item->id,
        'quantity' => 20,
        'unit_cost' => 250,
    ]];

    expect(fn () => inlinePurchasePost($f))->toThrow(\InvalidArgumentException::class);
    expect(Bill::where('company_id', $f['company']->id)->count())->toBe(0);
});

