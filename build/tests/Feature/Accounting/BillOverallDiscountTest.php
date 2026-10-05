<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReceiptLine;
use App\Services\CommandBus;
use App\Services\CompanyContextService;

require_once __DIR__.'/BillLineTotalFixtures.php';

/**
 * A bill's OVERALL discount (amount or percent) on top of per-line discount_rate.
 *
 *   subtotal (gross lines) - line discounts - overall discount + tax = total
 *   overall discount base  = subtotal after line discounts
 *   tax                    = per line on (line_total - its share of the overall discount)
 *   bills.discount_amount  = line discounts only; overall is bills.overall_discount_amount
 *
 * The overall discount is spread over lines pro rata by line net (last line takes the
 * rounding) and stored as bill_line_items.overall_discount_share; each line posts at
 * line_total - share and stock is received at unit_price - share/quantity.
 * See BillLineTotals::computeAll, PostingService::buildBillEntries, BillLineItem::effectiveUnitCost.
 */

/** Second expense account, so each line's own debit can be told apart. */
function billOdSecondExpense(array $f): Account
{
    return Account::create(['company_id' => $f['company']->id, 'code' => '5010', 'name' => 'Other Purchases', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit', 'currency' => null, 'is_active' => true]);
}

function billOdPayload(array $f, array $lines, array $extra = []): array
{
    return array_merge([
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-10',
        'status' => 'received',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_terms' => 0,
        'line_items' => $lines,
    ], $extra);
}

function billOdDebit(Transaction $t, string $accountId): float
{
    return round((float) $t->journalEntries->where('account_id', $accountId)->sum('debit_amount'), 2);
}

function billOdCredit(Transaction $t, string $accountId): float
{
    return round((float) $t->journalEntries->where('account_id', $accountId)->sum('credit_amount'), 2);
}

function billOdTwoLines(array $f, Account $second, array $firstExtra = []): array
{
    return [
        array_merge(['description' => 'Line one', 'quantity' => 1, 'unit_price' => 1000, 'expense_account_id' => $f['expense']->id], $firstExtra),
        ['description' => 'Line two', 'quantity' => 1, 'unit_price' => 3000, 'expense_account_id' => $second->id],
    ];
}

test('an overall amount discount lowers the total and spreads pro rata over the lines', function () {
    $f = billLineTotalFixture();
    $second = billOdSecondExpense($f);

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", billOdPayload($f, billOdTwoLines($f, $second), [
        'overall_discount_type' => 'amount',
        'overall_discount_value' => 400,
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $bill = Bill::where('company_id', $f['company']->id)->firstOrFail();
    $lines = $bill->lineItems()->orderBy('line_number')->get();

    expect((float) $bill->subtotal)->toBe(4000.0)
        ->and((float) $bill->discount_amount)->toBe(0.0)
        ->and((float) $bill->overall_discount_amount)->toBe(400.0)
        ->and($bill->overall_discount_type)->toBe('amount')
        ->and((float) $bill->total_amount)->toBe(3600.0)
        ->and((float) $bill->balance)->toBe(3600.0)
        ->and((float) $lines[0]->overall_discount_share)->toBe(100.0)
        ->and((float) $lines[1]->overall_discount_share)->toBe(300.0)
        ->and((float) $lines[0]->total)->toBe(900.0)
        ->and((float) $lines[1]->total)->toBe(2700.0);

    $t = Transaction::findOrFail($bill->transaction_id);
    expect(billOdCredit($t, $f['ap']->id))->toBe(3600.0)
        ->and(billOdDebit($t, $f['expense']->id))->toBe(900.0)
        ->and(billOdDebit($t, $second->id))->toBe(2700.0);
});

test('an overall percent discount is taken off the subtotal and spread the same way', function () {
    $f = billLineTotalFixture();
    $second = billOdSecondExpense($f);

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", billOdPayload($f, billOdTwoLines($f, $second), [
        'overall_discount_type' => 'percent',
        'overall_discount_value' => 10,
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $bill = Bill::where('company_id', $f['company']->id)->firstOrFail();
    $lines = $bill->lineItems()->orderBy('line_number')->get();

    expect($bill->overall_discount_type)->toBe('percent')
        ->and((float) $bill->overall_discount_value)->toBe(10.0)
        ->and((float) $bill->overall_discount_amount)->toBe(400.0)
        ->and((float) $bill->total_amount)->toBe(3600.0)
        ->and((float) $lines[0]->overall_discount_share + (float) $lines[1]->overall_discount_share)->toBe(400.0);

    $t = Transaction::findOrFail($bill->transaction_id);
    expect(billOdCredit($t, $f['ap']->id))->toBe(3600.0)
        ->and(billOdDebit($t, $f['expense']->id))->toBe(900.0)
        ->and(billOdDebit($t, $second->id))->toBe(2700.0);
});

test('tax is charged after the overall discount and the last line takes the rounding', function () {
    $f = billLineTotalFixture();
    $second = billOdSecondExpense($f);

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", billOdPayload($f, [
        ['description' => 'A', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 10, 'expense_account_id' => $f['expense']->id],
        ['description' => 'B', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 10, 'expense_account_id' => $second->id],
        ['description' => 'C', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 10, 'expense_account_id' => $f['expense']->id],
    ], [
        'status' => 'draft',
        'overall_discount_type' => 'amount',
        'overall_discount_value' => 10,
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $bill = Bill::where('company_id', $f['company']->id)->firstOrFail();
    $shares = $bill->lineItems()->orderBy('line_number')->pluck('overall_discount_share')->map(fn ($v) => (float) $v)->all();

    // 10 over three equal lines: 3.33, 3.33 and the last line takes 3.34.
    expect($shares)->toBe([3.33, 3.33, 3.34])
        ->and((float) $bill->overall_discount_amount)->toBe(10.0)
        ->and((float) $bill->tax_amount)->toEqualWithDelta(29.0, 0.000001)   // (300 - 10) x 10%
        ->and((float) $bill->total_amount)->toEqualWithDelta(319.0, 0.000001); // 300 - 10 + 29
});

test('an overall discount combined with a line discount posts each line net of its share', function () {
    $f = billLineTotalFixture();
    $second = billOdSecondExpense($f);
    // The line discount credits the Discount received role, which needs an account.
    $discountsReceived = Account::create(['company_id' => $f['company']->id, 'code' => '4300', 'name' => 'Discounts Received', 'type' => 'other_income', 'subtype' => 'other_income', 'normal_balance' => 'credit', 'currency' => null, 'is_active' => true]);

    // Line one 1000 less 10% (net 900), line two 3000: the base is 3900, overall 390.
    $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", billOdPayload($f, billOdTwoLines($f, $second, ['discount_rate' => 10]), [
        'overall_discount_type' => 'amount',
        'overall_discount_value' => 390,
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $bill = Bill::where('company_id', $f['company']->id)->firstOrFail();
    $lines = $bill->lineItems()->orderBy('line_number')->get();

    expect((float) $bill->subtotal)->toBe(4000.0)
        ->and((float) $bill->discount_amount)->toBe(100.0)
        ->and((float) $bill->overall_discount_amount)->toBe(390.0)
        ->and((float) $bill->total_amount)->toBe(3510.0)
        ->and((float) $lines[0]->overall_discount_share)->toBe(90.0)
        ->and((float) $lines[1]->overall_discount_share)->toBe(300.0);

    $t = Transaction::findOrFail($bill->transaction_id);
    expect(billOdCredit($t, $f['ap']->id))->toBe(3510.0)
        ->and(billOdDebit($t, $f['expense']->id))->toBe(910.0)   // 1000 - its 90 share
        ->and(billOdDebit($t, $second->id))->toBe(2700.0)        // 3000 - its 300 share
        ->and(billOdCredit($t, $discountsReceived->id))->toBe(100.0) // the line discount only
        ->and(round((float) $t->journalEntries->sum('debit_amount'), 2))->toBe(round((float) $t->journalEntries->sum('credit_amount'), 2));
});

test('stock is received at the unit cost less its share of the overall discount', function () {
    $f = billLineTotalFixture();

    $bill = app(CompanyContextService::class)->withContext($f['company'], function () use ($f) {
        $result = app(CommandBus::class)->dispatch('bill.create', billOdPayload($f, [
            ['item_id' => $f['item']->id, 'warehouse_id' => $f['tank']->id, 'description' => 'Diesel', 'quantity' => 1000, 'unit_price' => 10],
        ], [
            'overall_discount_type' => 'amount',
            'overall_discount_value' => 500,
        ]), $f['user'], true);

        $bill = Bill::findOrFail($result['data']['id']);
        app(CommandBus::class)->dispatch('bill.receive_goods', ['id' => $bill->id, 'receipt_date' => '2026-09-10'], $f['user'], true);

        return $bill->fresh(['lineItems']);
    });

    $line = $bill->lineItems->first();
    expect((float) $bill->total_amount)->toBe(9500.0)
        ->and((float) $line->overall_discount_share)->toBe(500.0)
        ->and((float) $line->unit_price)->toBe(10.0);

    $receiptLine = StockReceiptLine::where('bill_line_item_id', $line->id)->firstOrFail();
    expect((float) $receiptLine->unit_cost)->toEqualWithDelta(9.5, 0.0000001)
        ->and((float) $receiptLine->total_cost)->toEqualWithDelta(9500, 0.01);

    $movement = StockMovement::findOrFail($receiptLine->stock_movement_id);
    expect((float) $movement->unit_cost)->toEqualWithDelta(9.5, 0.0000001)
        ->and((float) $movement->total_cost)->toEqualWithDelta(9500, 0.01);

    // Stock value (inventory debit) matches what the movement valued the litres at.
    $t = Transaction::findOrFail($bill->transaction_id);
    expect(billOdDebit($t, $f['inventory']->id))->toBe(9500.0)
        ->and(billOdCredit($t, $f['ap']->id))->toBe(9500.0);
});

test('editing the overall discount reverses and reposts the bill', function () {
    $f = billLineTotalFixture();
    $second = billOdSecondExpense($f);
    $lines = billOdTwoLines($f, $second);

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", billOdPayload($f, $lines, [
        'overall_discount_type' => 'amount',
        'overall_discount_value' => 400,
    ]))->assertSessionHasNoErrors();

    $bill = Bill::where('company_id', $f['company']->id)->firstOrFail();
    $oldTransactionId = $bill->transaction_id;

    $this->actingAs($f['user'])->put("/{$f['company']->slug}/bills/{$bill->id}", billOdPayload($f, $lines, [
        'due_date' => '2026-09-10',
        'overall_discount_type' => 'percent',
        'overall_discount_value' => 25,
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $bill->refresh();
    expect($bill->overall_discount_type)->toBe('percent')
        ->and((float) $bill->overall_discount_amount)->toBe(1000.0)
        ->and((float) $bill->total_amount)->toBe(3000.0)
        ->and($bill->transaction_id)->not->toBe($oldTransactionId)
        ->and(Transaction::find($oldTransactionId)->reversed_by_id)->not->toBeNull();

    $shares = $bill->lineItems()->orderBy('line_number')->pluck('overall_discount_share')->map(fn ($v) => (float) $v)->all();
    expect($shares)->toBe([250.0, 750.0]);

    $t = Transaction::findOrFail($bill->transaction_id);
    expect(billOdCredit($t, $f['ap']->id))->toBe(3000.0)
        ->and(billOdDebit($t, $f['expense']->id))->toBe(750.0)
        ->and(billOdDebit($t, $second->id))->toBe(2250.0);

    // Clearing it puts the lines back to their full price.
    $this->actingAs($f['user'])->put("/{$f['company']->slug}/bills/{$bill->id}", billOdPayload($f, $lines, [
        'due_date' => '2026-09-10',
        'overall_discount_type' => 'amount',
        'overall_discount_value' => null,
    ]))->assertSessionHasNoErrors();

    $bill->refresh();
    expect($bill->overall_discount_type)->toBeNull()
        ->and((float) $bill->total_amount)->toBe(4000.0);
});

test('a percent over 100 is refused', function () {
    $f = billLineTotalFixture();
    $second = billOdSecondExpense($f);

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", billOdPayload($f, billOdTwoLines($f, $second), [
        'overall_discount_type' => 'percent',
        'overall_discount_value' => 101,
    ]))->assertSessionHasErrors('overall_discount_value');

    expect(Bill::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('an amount over the subtotal after line discounts is refused', function () {
    $f = billLineTotalFixture();
    $second = billOdSecondExpense($f);

    // 4000 less the 100 line discount leaves 3900; 3950 is too much, 3900 is fine.
    $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", billOdPayload($f, billOdTwoLines($f, $second, ['discount_rate' => 10]), [
        'overall_discount_type' => 'amount',
        'overall_discount_value' => 3950,
    ]))->assertSessionHasErrors('overall_discount_value');

    expect(Bill::where('company_id', $f['company']->id)->count())->toBe(0);

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", billOdPayload($f, billOdTwoLines($f, $second, ['discount_rate' => 10]), [
        'status' => 'draft',
        'overall_discount_type' => 'amount',
        'overall_discount_value' => 3900,
    ]))->assertSessionHasNoErrors();

    expect((float) Bill::where('company_id', $f['company']->id)->firstOrFail()->total_amount)->toBe(0.0);
});
