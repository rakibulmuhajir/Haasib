<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * A fuel bill entered from Accounting -> Bills doesn't move stock until someone
 * receives it (fuel items are delivery_mode 'requires_receiving' -- see
 * FuelStation\Actions\Product\SetupAction), and a bill only used to become
 * receivable once it was PAID (bills/Show.vue canReceiveGoods). Fuel routinely
 * arrives on credit, so the delivery was invisible to the tank dip and read as
 * a false "gain" of the delivered litres. The close must now receive every
 * pending delivery belonging to its business date -- however it was entered --
 * before computing the tank variance. See DailyCloseService::pendingDeliveries
 * and the block in processDailyClose right before the tank variance loop.
 *
 * Moved out of DailyClosePendingDeliveryTest.php so BillDirectQuantityTest.php
 * (a different test directory) can reuse it too, per project convention that a
 * shared fixture lives in its own *Fixtures.php file, never inside another
 * *Test.php.
 */
function pendingDeliveryFixture(): array
{
    $f = creditCloseFixture();

    // bill.receive_goods is dispatched through the CommandBus, which checks
    // permissions against the acting user -- creditCloseFixture()'s user has no
    // company role by default.
    app(CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    DB::table('auth.company_user')->insert([
        'company_id' => $f['company']->id, 'user_id' => $f['user']->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CompanyContextService::class)->assignRole($f['user'], 'owner'));

    $ap = Account::create([
        'company_id' => $f['company']->id, 'code' => '2000', 'name' => 'Accounts Payable',
        'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_active' => true,
    ]);
    $f['vendor'] = Vendor::create([
        'company_id' => $f['company']->id, 'vendor_number' => 'V-1', 'name' => 'Fuel Depot',
        'base_currency' => 'PKR', 'is_active' => true, 'ap_account_id' => $ap->id,
    ]);

    Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')
        ->update([
            'asset_account_id' => $f['accounts']['1200']->id,
            'expense_account_id' => $f['accounts']['5100']->id,
            'fuel_category' => 'petrol',
            'delivery_mode' => 'requires_receiving',
        ]);

    $f['item'] = Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();
    $f['tank'] = Warehouse::where('company_id', $f['company']->id)->where('code', 'T1')->sole();

    // Opening baseline for the tank, one day before the close date every test
    // in this file dips.
    StockMovement::create([
        'company_id' => $f['company']->id, 'warehouse_id' => $f['tank']->id, 'item_id' => $f['item']->id,
        'movement_date' => '2026-09-14', 'movement_type' => 'opening', 'quantity' => 5000,
        'unit_cost' => 250, 'total_cost' => 1250000, 'created_by_user_id' => $f['user']->id,
    ]);

    // creditCloseFixture's default payload sells 100L on 2026-09-15 for a
    // customer paying by card and on credit; keep that but let each test set
    // its own tank_readings.
    $f['payload']['date'] = '2026-09-15';
    $f['payload']['closing_cash'] = 25000;

    return $f;
}

function pendingDeliveryBill(array $f, string $billDate, float $quantity, float $received = 0.0, ?string $warehouseId = null, string $billNumber = 'BILL-0001', float $directQuantity = 0.0): Bill
{
    $unitPrice = 240.0;
    $total = $quantity * $unitPrice;

    $bill = Bill::create([
        'company_id' => $f['company']->id,
        'vendor_id' => $f['vendor']->id,
        'bill_number' => $billNumber,
        'bill_date' => $billDate,
        'due_date' => $billDate,
        'status' => 'received', // an unpaid but posted bill -- see BillEditAfterPaymentTest for the same shape
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'subtotal' => $total,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => $total,
        'paid_amount' => 0,
        'balance' => $total,
        'base_amount' => $total,
        'created_by_user_id' => $f['user']->id,
    ]);

    BillLineItem::create([
        'company_id' => $f['company']->id,
        'bill_id' => $bill->id,
        'line_number' => 1,
        'item_id' => $f['item']->id,
        'warehouse_id' => $warehouseId,
        'description' => 'Petrol delivery',
        'quantity' => $quantity,
        'direct_quantity' => $directQuantity,
        'quantity_received' => $received,
        'unit_price' => $unitPrice,
        'tax_rate' => 0,
        'discount_rate' => 0,
        'line_total' => $total,
        'tax_amount' => 0,
        'total' => $total,
        'expense_account_id' => $f['accounts']['1200']->id,
        'created_by_user_id' => $f['user']->id,
    ]);

    return $bill->fresh(['lineItems']);
}

function pendingDeliveryPost(array $f): array
{
    // bill.receive_goods checks permissions against the current company (Spatie
    // team) context, so it must stay set for the call -- mirrors
    // DailyCloseInlinePurchaseTest's inlinePurchasePost().
    app(CurrentCompany::class)->set($f['company']);

    return app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user'])
    );
}
