<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\ProfitLossReportService;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\SaleMetadata;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\FuelStation\Services\FuelSaleService;
use App\Modules\Inventory\Models\Item;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;

/**
 * The close posts revenue from the meters at the posted pump rate -- gross. A discounted
 * bulk sale only ever recorded the net figure on the invoice, so the discount went nowhere:
 * the drawer came up short by exactly the discount, every time, and that shortage was
 * indistinguishable from theft on the one report the manager uses to detect it.
 *
 * A discount is contra revenue, not an expense and not reduced revenue at source: the pump
 * did dispense those litres at the posted rate. Account 4210 Sales Discounts has existed in
 * the seeder (marked contra) since the module was written; nothing ever posted to it.
 *
 * Relies on creditCloseFixture() from DailyCloseCreditSalesTest.php, which Pest loads when
 * the FuelStation directory is run together (module scope), per project convention.
 */
function discountFixture(): array
{
    $f = creditCloseFixture();
    app(CurrentCompany::class)->set($f['company']);

    $item = Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();
    RateChange::create([
        'company_id' => $f['company']->id,
        'item_id' => $item->id,
        'effective_date' => '2026-09-01',
        'purchase_rate' => 250,
        'sale_rate' => 300,
    ]);
    $f['item'] = $item;

    // creditCloseFixture() has no expense account, so DailyCloseService resolves
    // cash_over_short to null and silently drops the balancing line — a close carrying any
    // variance then fails to post at all. These tests need a day that is short before the
    // fix and square after it, so give the company somewhere to put the difference.
    $f['accounts']['6180'] = Account::create([
        'company_id' => $f['company']->id, 'code' => '6180', 'name' => 'Cash Short/Over',
        'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_active' => true,
    ]);

    // A plain all-cash day: 100 L through the nozzle at the posted 300/L. No cards, no
    // credit, so the only thing that can move expected cash is the discount itself.
    $f['payload']['payment_receipts'] = [];
    $f['payload']['credit_sales'] = [];

    return $f;
}

function discountAccount(string $companyId): ?Account
{
    return Account::where('company_id', $companyId)->where('code', '4210')->first();
}

test('a discounted cash sale leaves the drawer reconciling instead of short by the discount', function () {
    $f = discountFixture();

    // The whole 100 L went to a bulk buyer at 10/L off: they paid 29,000, not 30,000.
    app(FuelSaleService::class)->createSale([
        'sale_type' => SaleMetadata::TYPE_BULK,
        'item_id' => $f['item']->id,
        'quantity' => 100,
        'discount_per_liter' => 10,
        'sale_date' => '2026-09-15',
        'customer_id' => $f['customer']->id,
    ]);

    $f['payload']['closing_cash'] = 10000 + 29000;
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    $totals = $posted['metadata']['posting_snapshot']['totals'];
    expect((float) $totals['variance'])->toBe(0.0)
        ->and((float) $totals['expected_closing'])->toBe(39000.0);
});

test('the discount is posted to sales discounts, leaving revenue gross', function () {
    $f = discountFixture();

    app(FuelSaleService::class)->createSale([
        'sale_type' => SaleMetadata::TYPE_BULK,
        'item_id' => $f['item']->id,
        'quantity' => 100,
        'discount_per_liter' => 10,
        'sale_date' => '2026-09-15',
        'customer_id' => $f['customer']->id,
    ]);

    $discountAccount = discountAccount($f['company']->id);
    expect($discountAccount)->not->toBeNull()
        ->and($discountAccount->type)->toBe('revenue')
        ->and($discountAccount->normal_balance)->toBe('debit');

    expect((float) DB::table('acct.journal_entries')->where('account_id', $discountAccount->id)->sum('debit_amount'))
        ->toBe(1000.0);

    // The contra posting takes the shortfall out of the drawer, not out of revenue.
    $cashAccountId = app(DailyCloseService::class)->cashAccountId($f['company']->id);
    expect((float) DB::table('acct.journal_entries')->where('account_id', $cashAccountId)->sum('credit_amount'))
        ->toBe(1000.0);
});

test('the profit and loss separates gross fuel sales from the discount given', function () {
    $f = discountFixture();

    app(FuelSaleService::class)->createSale([
        'sale_type' => SaleMetadata::TYPE_BULK,
        'item_id' => $f['item']->id,
        'quantity' => 100,
        'discount_per_liter' => 10,
        'sale_date' => '2026-09-15',
        'customer_id' => $f['customer']->id,
    ]);
    $f['payload']['closing_cash'] = 39000;
    app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    $pl = app(ProfitLossReportService::class)->run($f['company']->id, '2026-09-01', '2026-09-30');
    $discountRow = collect($pl['income'])->firstWhere('code', '4210');

    expect($discountRow)->not->toBeNull()
        ->and($discountRow['net'])->toBe(-1000.0)
        ->and(collect($pl['income'])->firstWhere('code', '4100')['net'])->toBe(30000.0)
        ->and($pl['totals']['income'])->toBe(29000.0);
});

test('a discounted credit sale bills the buyer net and still clears the drawer', function () {
    $f = discountFixture();

    $invoice = app(FuelSaleService::class)->createSale([
        'sale_type' => SaleMetadata::TYPE_CREDIT,
        'item_id' => $f['item']->id,
        'quantity' => 100,
        'discount_per_liter' => 10,
        'sale_date' => '2026-09-15',
        'customer_id' => $f['customer']->id,
    ]);

    // Nothing was collected: the buyer owes the net 29,000 and the 1,000 was given away.
    // The close requires each pending fuel-sale invoice to be echoed back unchanged.
    $f['payload']['closing_cash'] = 10000;
    $f['payload']['credit_sales'] = [[
        'customer_id' => $f['customer']->id,
        'amount' => 29000,
        'reference' => $invoice->invoice_number,
    ]];
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    $totals = $posted['metadata']['posting_snapshot']['totals'];
    expect((float) $totals['variance'])->toBe(0.0);

    $entries = Transaction::findOrFail($posted['transaction_id'])->journalEntries;
    expect((float) $entries->where('account_id', $f['accounts']['4100']->id)->sum('credit_amount'))->toBe(30000.0);
    expect((float) DB::table('acct.journal_entries')->where('account_id', $f['accounts']['1100']->id)->sum('debit_amount'))->toBe(29000.0);
});

test('a sale at the posted rate posts no discount at all', function () {
    $f = discountFixture();

    app(FuelSaleService::class)->createSale([
        'sale_type' => SaleMetadata::TYPE_BULK,
        'item_id' => $f['item']->id,
        'quantity' => 100,
        'sale_date' => '2026-09-15',
        'customer_id' => $f['customer']->id,
    ]);

    $discountAccount = discountAccount($f['company']->id);
    $posted = $discountAccount
        ? (float) DB::table('acct.journal_entries')->where('account_id', $discountAccount->id)->sum('debit_amount')
        : 0.0;
    expect($posted)->toBe(0.0);
});
