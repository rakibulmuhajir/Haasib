<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\FuelStation\Models\CustomerFuelDiscount;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Services\CustomerFuelDiscountService;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\FuelStation\Services\FuelSaleService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * A customer's negotiated fuel discount (Rs/L or percent, per fuel item) has to price
 * identically wherever a credit sale is entered: the standalone Fuel -> Sales form and a
 * manual Daily Close credit row. Both go through CustomerFuelDiscountService, never their
 * own maths.
 *
 * Follows the same direct-service fixture pattern as FuelSaleDiscountPostingTest's
 * discountFixture() (creditCloseFixture() + CurrentCompany::set(), no HTTP/RBAC needed for
 * calling FuelSaleService/DailyCloseService directly) -- defined locally here rather than
 * depended on from that file, per project convention against cross-Test.php helpers.
 */
function discountedCustomerFixture(): array
{
    $f = creditCloseFixture();
    app(CurrentCompany::class)->set($f['company']);

    $petrol = Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();

    $diesel = Item::create([
        'company_id' => $f['company']->id, 'sku' => 'DIESEL', 'name' => 'Diesel',
        'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR',
        'avg_cost' => 250, 'fuel_category' => 'diesel',
    ]);
    RateChange::create([
        'company_id' => $f['company']->id, 'item_id' => $diesel->id,
        'effective_date' => '2026-09-01', 'purchase_rate' => 250, 'sale_rate' => 300,
    ]);
    $dieselTank = Warehouse::create(['company_id' => $f['company']->id, 'code' => 'T2', 'name' => 'Diesel Tank', 'linked_item_id' => $diesel->id]);
    $dieselPump = Pump::create(['company_id' => $f['company']->id, 'name' => 'Pump2', 'tank_id' => $dieselTank->id]);
    $dieselNozzle = Nozzle::create(['company_id' => $f['company']->id, 'pump_id' => $dieselPump->id, 'tank_id' => $dieselTank->id, 'item_id' => $diesel->id, 'code' => 'N2', 'label' => 'Diesel']);

    // A close carrying any variance needs somewhere to post the balancing line.
    $f['accounts']['6180'] = Account::create([
        'company_id' => $f['company']->id, 'code' => '6180', 'name' => 'Cash Short/Over',
        'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_active' => true,
    ]);

    $f['petrol'] = $petrol;
    $f['diesel'] = $diesel;
    $f['dieselNozzle'] = $dieselNozzle;

    return $f;
}

function fuelDiscountsAccount(string $companyId): ?Account
{
    return Account::where('company_id', $companyId)->where('code', '4210')->first();
}

test('a per-litre diesel discount and a percent petrol discount can be set on a customer', function () {
    $f = discountedCustomerFixture();

    CustomerFuelDiscount::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'item_id' => $f['diesel']->id, 'discount_type' => 'per_litre', 'value' => 3]);
    CustomerFuelDiscount::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'item_id' => $f['petrol']->id, 'discount_type' => 'percent', 'value' => 5]);

    $service = app(CustomerFuelDiscountService::class);
    $diesel = $service->for($f['company']->id, $f['customer']->id, $f['diesel']->id);
    $petrol = $service->for($f['company']->id, $f['customer']->id, $f['petrol']->id);

    expect($diesel)->toBe(['discount_type' => 'per_litre', 'value' => 3.0])
        ->and($petrol)->toBe(['discount_type' => 'percent', 'value' => 5.0])
        ->and($service->amount($diesel, 100, 30000))->toBe(300.0)
        ->and($service->amount($petrol, 100, 30000))->toBe(1500.0)
        // Never more than the gross, even for a nonsense value.
        ->and($service->amount(['discount_type' => 'per_litre', 'value' => 999], 100, 30000))->toBe(30000.0);
});

test('a standalone credit sale applies the stored per-litre discount automatically when not overridden', function () {
    $f = discountedCustomerFixture();
    CustomerFuelDiscount::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'item_id' => $f['diesel']->id, 'discount_type' => 'per_litre', 'value' => 3]);

    $invoice = app(FuelSaleService::class)->createSale([
        'sale_type' => 'credit', 'item_id' => $f['diesel']->id, 'quantity' => 100,
        'sale_date' => '2026-09-15', 'customer_id' => $f['customer']->id,
    ]);

    expect((float) $invoice->discount_amount)->toBe(300.0)
        ->and((float) $invoice->total_amount)->toBe(29700.0);

    $discountAccount = fuelDiscountsAccount($f['company']->id);
    expect((float) DB::table('acct.journal_entries')->where('account_id', $discountAccount->id)->sum('debit_amount'))->toBe(300.0);
});

test('an explicit discount on the sale overrides the customers stored discount', function () {
    $f = discountedCustomerFixture();
    CustomerFuelDiscount::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'item_id' => $f['diesel']->id, 'discount_type' => 'per_litre', 'value' => 3]);

    $invoice = app(FuelSaleService::class)->createSale([
        'sale_type' => 'credit', 'item_id' => $f['diesel']->id, 'quantity' => 100,
        'sale_date' => '2026-09-15', 'customer_id' => $f['customer']->id,
        'discount_per_liter' => 5,
    ]);

    expect((float) $invoice->discount_amount)->toBe(500.0);
});

test('a manual daily close credit row prices a per-litre discount, invoices net, and debits sales discounts once', function () {
    $f = discountedCustomerFixture();
    CustomerFuelDiscount::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'item_id' => $f['diesel']->id, 'discount_type' => 'per_litre', 'value' => 3]);

    $payload = $f['payload'];
    $payload['nozzle_readings'] = [[
        'nozzle_id' => $f['dieselNozzle']->id, 'item_id' => $f['diesel']->id,
        'opening_electronic' => 0, 'closing_electronic' => 1000, 'liters_sold' => 1000, 'sale_rate' => 290,
    ]];
    $payload['payment_receipts'] = [];
    $payload['credit_sales'] = [[
        'customer_id' => $f['customer']->id, 'amount' => 290000, 'reference' => 'Diesel credit',
        'item_id' => $f['diesel']->id, 'litres' => 1000,
    ]];
    // All 290,000 of revenue is credit; nothing landed in the drawer beyond the opening float.
    $payload['opening_cash'] = 10000;
    $payload['closing_cash'] = 10000;

    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $payload, $f['user']);

    // Expected cash removes the full 290,000 gross exactly once (not 287,000, and not twice) --
    // a plain close of the same nozzle revenue with no credit row at all would expect
    // 10,000 + 290,000 = 300,000 closing; this one expects exactly 10,000.
    $totals = $posted['metadata']['posting_snapshot']['totals'];
    expect((float) $totals['expected_closing'])->toBe(10000.0)
        ->and((float) $totals['variance'])->toBe(0.0);

    $creditDetail = collect($posted['metadata']['credit_sale_details'])->sole();
    expect((float) $creditDetail['amount'])->toBe(290000.0)
        ->and((float) $creditDetail['discount_amount'])->toBe(3000.0)
        ->and((float) $creditDetail['net_amount'])->toBe(287000.0);

    $invoice = Invoice::findOrFail($creditDetail['invoice_id']);
    expect((float) $invoice->total_amount)->toBe(287000.0);

    $discountAccount = fuelDiscountsAccount($f['company']->id);
    expect((float) DB::table('acct.journal_entries')->where('account_id', $discountAccount->id)->sum('debit_amount'))->toBe(3000.0)
        ->and((float) DB::table('acct.journal_entries')->where('account_id', $f['accounts']['1100']->id)->sum('debit_amount'))->toBe(287000.0);
});

test('a per-litre discount row without litres is refused', function () {
    $f = discountedCustomerFixture();
    CustomerFuelDiscount::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'item_id' => $f['diesel']->id, 'discount_type' => 'per_litre', 'value' => 3]);

    $payload = $f['payload'];
    $payload['nozzle_readings'] = [[
        'nozzle_id' => $f['dieselNozzle']->id, 'item_id' => $f['diesel']->id,
        'opening_electronic' => 0, 'closing_electronic' => 1000, 'liters_sold' => 1000, 'sale_rate' => 290,
    ]];
    $payload['payment_receipts'] = [];
    $payload['credit_sales'] = [[
        'customer_id' => $f['customer']->id, 'amount' => 290000, 'reference' => 'Diesel credit',
        'item_id' => $f['diesel']->id,
        // litres omitted
    ]];
    $payload['opening_cash'] = 10000;
    $payload['closing_cash'] = 300000;

    expect(fn () => app(DailyCloseService::class)->processDailyClose($f['company']->id, $payload, $f['user']))
        ->toThrow(ValidationException::class);
});

test('a percent discount row needs only the amount', function () {
    $f = discountedCustomerFixture();
    CustomerFuelDiscount::create(['company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'item_id' => $f['petrol']->id, 'discount_type' => 'percent', 'value' => 5]);

    $payload = $f['payload'];
    // Base fixture's own petrol nozzle: 100 L @ 300/L = 30,000 gross.
    $payload['payment_receipts'] = [];
    $payload['credit_sales'] = [[
        'customer_id' => $f['customer']->id, 'amount' => 30000, 'reference' => 'Petrol credit',
        'item_id' => $f['petrol']->id,
        // no litres needed for a percent discount
    ]];
    $payload['opening_cash'] = 10000;
    $payload['closing_cash'] = 10000;

    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $payload, $f['user']);

    $creditDetail = collect($posted['metadata']['credit_sale_details'])->sole();
    expect((float) $creditDetail['amount'])->toBe(30000.0)
        ->and((float) $creditDetail['discount_amount'])->toBe(1500.0)
        ->and((float) $creditDetail['net_amount'])->toBe(28500.0);

    $invoice = Invoice::findOrFail($creditDetail['invoice_id']);
    expect((float) $invoice->total_amount)->toBe(28500.0);
});

test('a customer with no discount behaves exactly as before', function () {
    $f = discountedCustomerFixture();

    // Standalone sale, on a different date so it is not itself picked up as a pending
    // channel by the manual-row close below.
    $invoice = app(FuelSaleService::class)->createSale([
        'sale_type' => 'credit', 'item_id' => $f['diesel']->id, 'quantity' => 100,
        'sale_date' => '2026-09-16', 'customer_id' => $f['customer']->id,
    ]);
    expect((float) $invoice->discount_amount)->toBe(0.0)
        ->and((float) $invoice->total_amount)->toBe(30000.0);

    // Manual daily close row on a fuel item with litres given but no discount on file: priced
    // at the full gross, same as before this feature existed.
    $payload = $f['payload'];
    $payload['nozzle_readings'] = [[
        'nozzle_id' => $f['dieselNozzle']->id, 'item_id' => $f['diesel']->id,
        'opening_electronic' => 0, 'closing_electronic' => 1000, 'liters_sold' => 1000, 'sale_rate' => 290,
    ]];
    $payload['payment_receipts'] = [];
    $payload['credit_sales'] = [[
        'customer_id' => $f['customer']->id, 'amount' => 290000, 'reference' => 'Diesel credit',
        'item_id' => $f['diesel']->id, 'litres' => 1000,
    ]];
    $payload['opening_cash'] = 10000;
    $payload['closing_cash'] = 10000;

    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $payload, $f['user']);
    $creditDetail = collect($posted['metadata']['credit_sale_details'])->sole();
    $variance = (float) $posted['metadata']['posting_snapshot']['totals']['variance'];

    expect((float) $creditDetail['discount_amount'])->toBe(0.0)
        ->and((float) $creditDetail['net_amount'])->toBe(290000.0)
        ->and($variance)->toBe(0.0);

    $discountAccount = fuelDiscountsAccount($f['company']->id);
    $discountPosted = $discountAccount
        ? (float) DB::table('acct.journal_entries')->where('account_id', $discountAccount->id)->sum('debit_amount')
        : 0.0;
    expect($discountPosted)->toBe(0.0);
});
