<?php

use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReceiptLine;
use App\Services\CommandBus;
use App\Services\CompanyContextService;

require_once __DIR__.'/BillLineTotalFixtures.php';

/**
 * A supplier's invoice is often priced to 3-4 decimal places per unit -- a
 * fuel bill of 2,000 L for Rs 676,543.21 is a rate of 338.271605, not the
 * 338.27 a 2-decimal rate field would have stored (a difference of several
 * hundred rupees over that quantity). A line may instead submit the total it
 * was actually billed and have unit_price derived from it exactly.
 * See BillLineTotals, Bill\CreateAction, Bill\UpdateAction, StoreBillRequest,
 * bills/Create.vue and Edit.vue.
 */
test('a line billed by its total instead of a rate derives the exact rate and posts the exact amount', function () {
    $f = billLineTotalFixture();

    $response = $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", [
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-10',
        'status' => 'received',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_terms' => 0,
        'line_items' => [
            [
                'description' => 'Diesel delivery',
                'quantity' => 2000,
                'unit_price' => 0,
                'line_total' => 676543.21,
                'expense_account_id' => $f['expense']->id,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $bill = Bill::where('company_id', $f['company']->id)->where('bill_number', 'BILL-00001')->firstOrFail();
    $line = $bill->lineItems->first();

    expect((float) $line->unit_price)->toEqualWithDelta(338.271605, 0.0000001)
        ->and((float) $line->line_total)->toBe(676543.21)
        ->and((float) $bill->total_amount)->toBe(676543.21);

    $transaction = Transaction::findOrFail($bill->transaction_id);
    $apCredit = $transaction->journalEntries->where('account_id', $f['ap']->id)->sum('credit_amount');
    expect((float) $apCredit)->toEqualWithDelta(676543.21, 0.01);
});

test('a line with only a unit_price behaves as before', function () {
    $f = billLineTotalFixture();

    $response = $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", [
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-10',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_terms' => 0,
        'line_items' => [
            [
                'description' => 'Miscellaneous purchase',
                'quantity' => 10,
                'unit_price' => 250,
                'expense_account_id' => $f['expense']->id,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $bill = Bill::where('company_id', $f['company']->id)->firstOrFail();
    $line = $bill->lineItems->first();

    expect((float) $line->unit_price)->toBe(250.0)
        ->and((float) $line->line_total)->toBe(2500.0)
        ->and((float) $bill->total_amount)->toBe(2500.0);
});

test('a line_total submitted against a zero quantity is refused', function () {
    $f = billLineTotalFixture();

    $response = $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", [
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-10',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_terms' => 0,
        'line_items' => [
            [
                'description' => 'Bad line',
                'quantity' => 0,
                'unit_price' => 0,
                'line_total' => 100,
                'expense_account_id' => $f['expense']->id,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('line_items.0.quantity');
    expect(Bill::where('company_id', $f['company']->id)->count())->toBe(0);
});

test("editing a posted bill's line to a new line_total reposts with the exact amount", function () {
    $f = billLineTotalFixture();

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/bills", [
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-10',
        'status' => 'received',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_terms' => 0,
        'line_items' => [
            [
                'description' => 'Miscellaneous purchase',
                'quantity' => 1,
                'unit_price' => 500,
                'expense_account_id' => $f['expense']->id,
            ],
        ],
    ]);

    $bill = Bill::where('company_id', $f['company']->id)->firstOrFail();
    $oldTransactionId = $bill->transaction_id;
    expect($oldTransactionId)->not->toBeNull();

    $response = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bills/{$bill->id}", [
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-10',
        'due_date' => '2026-09-10',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_terms' => 0,
        'line_items' => [
            [
                'description' => 'Miscellaneous purchase',
                'quantity' => 1,
                'unit_price' => 0,
                'line_total' => 555.55,
                'expense_account_id' => $f['expense']->id,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $bill->refresh();
    expect((float) $bill->lineItems->first()->line_total)->toBe(555.55)
        ->and((float) $bill->total_amount)->toBe(555.55)
        ->and($bill->transaction_id)->not->toBe($oldTransactionId);

    $oldTransaction = Transaction::find($oldTransactionId);
    expect($oldTransaction->reversed_by_id)->not->toBeNull();

    $newTransaction = Transaction::findOrFail($bill->transaction_id);
    $apCredit = $newTransaction->journalEntries->where('account_id', $f['ap']->id)->sum('credit_amount');
    expect((float) $apCredit)->toEqualWithDelta(555.55, 0.01);
});

/**
 * The owner's real case: a daily-close fuel purchase entered at a rate rounded
 * to 338.80 (the daily-close purchase row only takes 2 decimals of rate), with
 * the supplier's actual invoice pricing it to 2,371,749 -- a 149 rupee
 * difference on 7,000 L that the owner needs to correct on a bill whose stock
 * has already been received. UpdateAction ordinarily refuses any edit to a
 * received line ("Stock on this bill has already been received..."); a
 * money-only edit (same item/warehouse/quantity, new price) is the one
 * exception, and it revalues the stock receipt/movement and the item's
 * cost_price instead of refusing. See UpdateAction::isMoneyOnlyLineEdit /
 * reviseLineItemsInPlace and InventoryService::revalueReceivedLine.
 */
test('a received line can be revalued to a corrected line_total, but not requantified', function () {
    $f = billLineTotalFixture();

    $bill = app(CompanyContextService::class)->withContext($f['company'], function () use ($f) {
        $result = app(CommandBus::class)->dispatch('bill.create', [
            'vendor_id' => $f['vendor']->id,
            'bill_date' => '2026-09-10',
            'status' => 'received',
            'currency' => 'PKR',
            'base_currency' => 'PKR',
            'payment_terms' => 0,
            'line_items' => [
                [
                    'item_id' => $f['item']->id,
                    'warehouse_id' => $f['tank']->id,
                    'description' => 'Diesel delivery',
                    'quantity' => 7000,
                    'unit_price' => 338.80,
                ],
            ],
        ], $f['user'], true);

        $bill = Bill::findOrFail($result['data']['id']);

        app(CommandBus::class)->dispatch('bill.receive_goods', [
            'id' => $bill->id,
            'receipt_date' => '2026-09-10',
        ], $f['user'], true);

        return $bill->fresh(['lineItems']);
    });

    $line = $bill->lineItems->first();
    expect((float) $line->quantity_received)->toBe(7000.0);

    $oldTransactionId = $bill->transaction_id;
    expect($oldTransactionId)->not->toBeNull();

    $stockLevel = StockLevel::where('company_id', $f['company']->id)
        ->where('item_id', $f['item']->id)
        ->where('warehouse_id', $f['tank']->id)
        ->firstOrFail();
    expect((float) $stockLevel->quantity)->toBe(7000.0);

    // The correction: same item, same tank, same quantity -- only the price changes.
    $response = $this->actingAs($f['user'])->put("/{$f['company']->slug}/bills/{$bill->id}", [
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-10',
        'due_date' => '2026-09-10',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_terms' => 0,
        'line_items' => [
            [
                'item_id' => $f['item']->id,
                'warehouse_id' => $f['tank']->id,
                'description' => 'Diesel delivery',
                'quantity' => 7000,
                'unit_price' => 0,
                'line_total' => 2371749,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $bill->refresh();
    $line = $bill->lineItems->first();
    expect((float) $line->line_total)->toBe(2371749.0)
        ->and((float) $line->quantity_received)->toBe(7000.0)
        ->and($bill->transaction_id)->not->toBe($oldTransactionId);

    $oldTransaction = Transaction::find($oldTransactionId);
    expect($oldTransaction->reversed_by_id)->not->toBeNull();

    $newTransaction = Transaction::findOrFail($bill->transaction_id);
    $apCredit = $newTransaction->journalEntries->where('account_id', $f['ap']->id)->sum('credit_amount');
    expect((float) $apCredit)->toEqualWithDelta(2371749, 0.01);

    $receiptLine = StockReceiptLine::where('bill_line_item_id', $line->id)->firstOrFail();
    expect((float) $receiptLine->total_cost)->toEqualWithDelta(2371749, 0.01);

    $movement = StockMovement::findOrFail($receiptLine->stock_movement_id);
    expect((float) $movement->total_cost)->toEqualWithDelta(2371749, 0.01)
        ->and((float) $movement->quantity)->toBe(7000.0);

    $stockLevel->refresh();
    expect((float) $stockLevel->quantity)->toBe(7000.0);

    // Requantifying a received line is still refused outright.
    $this->actingAs($f['user'])->put("/{$f['company']->slug}/bills/{$bill->id}", [
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-10',
        'due_date' => '2026-09-10',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_terms' => 0,
        'line_items' => [
            [
                'item_id' => $f['item']->id,
                'warehouse_id' => $f['tank']->id,
                'description' => 'Diesel delivery',
                'quantity' => 7100,
                'unit_price' => 0,
                'line_total' => 2371749,
            ],
        ],
    ]);

    expect(session('error'))->toContain('Stock on this bill has already been received');
});
