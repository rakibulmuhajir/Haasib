<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\SaleMetadata;
use App\Modules\FuelStation\Services\DailyCloseReconciliationService;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\FuelStation\Services\FuelSaleService;
use Illuminate\Support\Facades\DB;

// Reuses creditCloseFixture() from DailyCloseCreditSalesTest.php (company, customer with
// an AR account, a fuel item/tank/nozzle, and the base chart of accounts). Only loaded
// when the whole FuelStation directory is run together (module scope), per project convention.

function fuelInvoiceRate(array $f): void
{
    RateChange::create([
        'company_id' => $f['company']->id,
        'item_id' => \App\Modules\Inventory\Models\Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->value('id'),
        'effective_date' => '2020-01-01',
        'purchase_rate' => 250,
        'sale_rate' => 300,
    ]);
}

function createStandaloneFuelInvoice(array $f, string $saleDate = '2026-09-15', float $quantity = 20): Invoice
{
    $item = \App\Modules\Inventory\Models\Item::where('company_id', $f['company']->id)->where('sku', 'PETROL')->sole();
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    return app(FuelSaleService::class)->createSale([
        'sale_type' => SaleMetadata::TYPE_CREDIT,
        'customer_id' => $f['customer']->id,
        'item_id' => $item->id,
        'quantity' => $quantity,
        'sale_date' => $saleDate,
    ]);
}

test('a pending fuel-sale invoice is pre-loaded and attached on post, reducing expected cash without adding sales', function () {
    $f = creditCloseFixture();
    fuelInvoiceRate($f);
    $f['payload']['credit_sales'] = [];
    $invoice = createStandaloneFuelInvoice($f);
    expect($invoice->transaction_id)->toBeNull();

    $pending = app(DailyCloseService::class)->cashAccountId($f['company']->id);
    expect($pending)->not->toBeNull();

    $before = $f['payload'];
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $before, $f['user']);

    $invoice->refresh();
    expect($invoice->transaction_id)->toBe($posted['transaction_id'])
        ->and($invoice->status)->toBe('sent');

    $snapshot = $posted['metadata']['posting_snapshot'];
    $creditDetail = collect($snapshot['credit_sales'])->firstWhere('invoice_id', $invoice->id);
    expect($creditDetail)->not->toBeNull()
        ->and($creditDetail['source'])->toBe('fuel_sale_invoice')
        ->and((float) $creditDetail['amount'])->toBe(6000.0);

    // 100L * 300 sale_rate = 30000 total revenue; the invoice is a channel split, not extra sales.
    expect((float) $snapshot['totals']['total_revenue'])->toBe(30000.0);
    // opening 10000 + cash sales (30000 - 6000 credit) = 34000 expected before other flows configured in fixture.
    $entries = Transaction::findOrFail($posted['transaction_id'])->journalEntries;
    expect((float) $entries->where('account_id', $f['accounts']['1100']->id)->sum('debit_amount'))->toBe(6000.0);
});

test('a manual credit line duplicating a pending invoice reference is rejected', function () {
    $f = creditCloseFixture();
    fuelInvoiceRate($f);
    $invoice = createStandaloneFuelInvoice($f);
    $f['payload']['credit_sales'] = [
        ['customer_id' => $f['customer']->id, 'amount' => 1000, 'reference' => $invoice->invoice_number],
    ];

    expect(fn () => app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(Transaction::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('an invoice created after the close posts produces a reclassification journal as late channel activity', function () {
    $f = creditCloseFixture();
    fuelInvoiceRate($f);
    // No pending invoice and no manual credit line at post time: closing_cash must balance
    // against gross revenue (30000 sales + 9000 card = cash change 21000 on top of opening 10000).
    $f['payload']['credit_sales'] = [];
    $f['payload']['closing_cash'] = 31000;
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    $invoice = createStandaloneFuelInvoice($f, '2026-09-15', 10);
    expect($invoice->transaction_id)->not->toBeNull();

    $reclass = Transaction::findOrFail($invoice->transaction_id);
    expect($reclass->transaction_type)->toBe('fuel_sale_reclass')
        ->and($reclass->reference_type)->toBe('acct.invoices')
        ->and($reclass->reference_id)->toBe($invoice->id);

    $view = app(DailyCloseReconciliationService::class)->view(Transaction::findOrFail($posted['transaction_id']));
    $activityRow = collect($view['activity'])->firstWhere('source_id', $invoice->id);
    expect($activityRow)->not->toBeNull()
        ->and((float) $activityRow['reconciliation_effect'])->toBe(-3000.0)
        ->and((float) $activityRow['after']['sales'])->toBe(0.0);
});

test('a fuel-sale invoice for a different date or company is left untouched by the close', function () {
    $f = creditCloseFixture();
    fuelInvoiceRate($f);
    $f['payload']['credit_sales'] = [];
    $f['payload']['closing_cash'] = 31000;
    $otherDateInvoice = createStandaloneFuelInvoice($f, '2026-09-14', 5);

    app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    expect($otherDateInvoice->fresh()->transaction_id)->toBeNull();
});

test('parking with a pending fuel invoice keeps it pending: no link, no journal', function () {
    $f = creditCloseFixture();
    fuelInvoiceRate($f);
    $f['payload']['credit_sales'] = [];
    $invoice = createStandaloneFuelInvoice($f);

    app(DailyCloseReconciliationService::class)->park($f['company']->id, $f['payload'], $f['user']->id);

    expect($invoice->fresh()->transaction_id)->toBeNull();
    expect(Transaction::where('company_id', $f['company']->id)->count())->toBe(0);
});
