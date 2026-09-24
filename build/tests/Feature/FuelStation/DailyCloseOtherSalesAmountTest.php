<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\Inventory\Models\Item;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * The lubricant amount is derived on the server, never taken from the request.
 *
 * It used to be taken from the request: quantity and unit_price were validated, stored, and
 * then ignored, while (float) $sale['amount'] - a figure computed in the browser - went
 * straight into a revenue posting.
 *
 * Day one of the manual E2E is what surfaced it. Two lubricant rows entered as 4 x 2,400 and
 * 3 x 1,100 posted 2,400 and 1,100, because the quantity field was the one field in that form
 * wired to a raw @input fallthrough instead of the component's declared emit, so the amount
 * kept the value it had at quantity 1. The close would have balanced at 3,500 against an
 * expected 12,900 - wrong, and self-consistent, which is the worst way for money to be wrong.
 *
 * The UI bug is fixed too, but that is not what these tests pin. They pin that the ledger no
 * longer depends on the UI being right, and that a request cannot name its own revenue.
 *
 * Fixtures come from DailyCloseCreditSalesTest.php, so run the directory:
 *   php artisan test tests/Feature/FuelStation
 */
function lubricantItemFor(array $f): array
{
    $income = Account::create([
        'company_id' => $f['company']->id,
        'code' => '4200',
        'name' => 'Lubricant sales',
        'type' => 'revenue',
        'subtype' => 'other_income',
        'normal_balance' => 'credit',
        'is_active' => true,
    ]);

    // No price on the item on purpose. The rate that matters is the one on the close line,
    // which is what the station actually sold at that day.
    $item = Item::create([
        'company_id' => $f['company']->id,
        'sku' => 'MOBIL-4L',
        'name' => 'Mobil Super 4L',
        'item_type' => 'product',
        'unit_of_measure' => 'unit',
        'currency' => 'PKR',
        'income_account_id' => $income->id,
    ]);

    return ['item' => $item, 'income' => $income];
}

/**
 * The base fixture sells 100 L at 300 for 30,000, all of it cash once the card and credit
 * allocations are cleared. Opening cash is 10,000, so closing is 40,000 plus whatever the
 * lubricant line adds - passed in, so each test states the figure it expects to be posted
 * rather than inheriting it.
 */
function lubricantClose(array $f, array $sale, float $closingCash): array
{
    $f['payload']['payment_receipts'] = [];
    $f['payload']['credit_sales'] = [];
    $f['payload']['closing_cash'] = $closingCash;
    $f['payload']['other_sales'] = [$sale];

    return app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
}

test('it posts quantity times unit price, not the amount the browser sent', function () {
    $f = creditCloseFixture();
    ['item' => $item, 'income' => $income] = lubricantItemFor($f);

    // 'amount' is exactly what the broken form submitted: the price, as though one unit had
    // been sold, while quantity plainly says four.
    $posted = lubricantClose($f, [
        'item_id' => $item->id,
        'item_name' => $item->name,
        'quantity' => 4,
        'unit_price' => 2400,
        'amount' => 2400,
    ], 49600);

    $entries = Transaction::findOrFail($posted['transaction_id'])->journalEntries;

    expect((float) $entries->where('account_id', $income->id)->sum('credit_amount'))->toBe(9600.0)
        ->and((float) $posted['metadata']['other_sales'])->toBe(9600.0);
});

test('a request cannot name its own revenue', function () {
    $f = creditCloseFixture();
    ['item' => $item, 'income' => $income] = lubricantItemFor($f);

    // One unit at one rupee, with an amount that says otherwise. Before, this posted 999,999.
    $posted = lubricantClose($f, [
        'item_id' => $item->id,
        'item_name' => $item->name,
        'quantity' => 1,
        'unit_price' => 1,
        'amount' => 999999,
    ], 40001);

    $entries = Transaction::findOrFail($posted['transaction_id'])->journalEntries;

    expect((float) $entries->where('account_id', $income->id)->sum('credit_amount'))->toBe(1.0)
        ->and((float) $posted['metadata']['other_sales'])->toBe(1.0);
});

test('the stored line multiplies out to the stored amount', function () {
    $f = creditCloseFixture();
    ['item' => $item] = lubricantItemFor($f);

    $posted = lubricantClose($f, [
        'item_id' => $item->id,
        'item_name' => $item->name,
        'quantity' => 3,
        'unit_price' => 1100,
        'amount' => 1100,
    ], 43300);

    // other_sales_details feeds ProductProfitabilityReportService. A line whose quantity and
    // price do not multiply to its amount produces margins from figures that never agreed.
    $line = $posted['metadata']['other_sales_details'][0];

    expect((float) $line['quantity'] * (float) $line['unit_price'])->toBe((float) $line['amount'])
        ->and((float) $line['amount'])->toBe(3300.0);
});

test('the close balances on the derived amount', function () {
    $f = creditCloseFixture();
    ['item' => $item] = lubricantItemFor($f);

    $posted = lubricantClose($f, [
        'item_id' => $item->id,
        'item_name' => $item->name,
        'quantity' => 4,
        'unit_price' => 2400,
        'amount' => 2400,
    ], 49600);

    // The figure the drawer is reconciled against has to be the figure that was posted,
    // otherwise the close reports a variance for a discrepancy that only exists on screen.
    $totals = $posted['metadata']['posting_snapshot']['totals'];

    expect((float) $totals['variance'])->toBe(0.0)
        ->and((float) $totals['expected_closing'])->toBe(49600.0);

    $entries = Transaction::findOrFail($posted['transaction_id'])->journalEntries;
    expect((float) $entries->sum('debit_amount'))->toBe((float) $entries->sum('credit_amount'));
});
