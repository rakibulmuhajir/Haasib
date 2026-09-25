<?php

use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/CreditCloseFixtures.php';

/*
 * A fuel sale invoiced from Accounting -> Invoices (not Fuel -> Sales) for litres that
 * went through the pumps must be flagged by that day's close and included as credit --
 * never double-counting revenue or the receivable. See DailyCloseCreditSaleService::
 * pendingAccountingInvoiceDetails and the 'accounting_invoice' branch in
 * DailyCloseService::processDailyClose.
 */
function accountingInvoiceFor(array $f, float $amount, string $incomeAccountId, string $date = '2026-09-15', bool $direct = false): Invoice
{
    $result = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('invoice.create', [
        'customer' => $f['customer']->id,
        'currency' => 'PKR',
        'date' => $date,
        'draft' => false,
        'send_immediately' => true,
        'is_direct_delivery' => $direct,
        'line_items' => [[
            'description' => 'Fuel sold', 'quantity' => 1, 'unit_price' => $amount,
            'tax_rate' => 0, 'income_account_id' => $incomeAccountId,
        ]],
    ], $f['user'], true));

    return Invoice::findOrFail($result['data']['id']);
}

/**
 * The row the close page pre-loads for an Accounting invoice (reference = invoice number),
 * with the drawer moved to match.
 */
function includeAccountingInvoiceRow(array &$f, Invoice $invoice): void
{
    // Whatever credit rows the payload had go back into the drawer; the invoice's amount leaves it.
    $f['payload']['closing_cash'] += array_sum(array_column($f['payload']['credit_sales'] ?? [], 'amount'))
        - (float) $invoice->total_amount;
    $f['payload']['credit_sales'] = [[
        'customer_id' => $invoice->customer_id,
        'amount' => (float) $invoice->total_amount,
        'reference' => $invoice->invoice_number,
    ]];
}

function revenueLedgerNetForAccount(array $f, string $accountId, string $date): float
{
    return (float) \Illuminate\Support\Facades\DB::table('acct.journal_entries as je')
        ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
        ->where('t.company_id', $f['company']->id)
        ->where('t.transaction_date', $date)
        ->where('je.account_id', $accountId)
        ->selectRaw('COALESCE(SUM(je.credit_amount),0) - COALESCE(SUM(je.debit_amount),0) as net')
        ->value('net');
}

test('a posted Accounting invoice on the fuel revenue account is included, without double-counting revenue or AR', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = creditCloseFixture();
    // Drop the fixture's own default credit_sales row -- this test wants a clean read on
    // just the accounting invoice being folded in.
    $f['payload']['credit_sales'] = [];
    // The fixture's day had a 6,000 credit sale; without it, that money is in the drawer.
    $f['payload']['closing_cash'] += 6000;

    $invoice = accountingInvoiceFor($f, 5000, $f['accounts']['4100']->id);
    expect($invoice->transaction_id)->not->toBeNull();
    expect($invoice->included_in_close_id)->toBeNull();
    includeAccountingInvoiceRow($f, $invoice);

    $posted = app(\App\Modules\FuelStation\Services\DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($posted['transaction_id']);

    // Expected cash reduced by the invoice's amount, exactly like a credit row.
    expect((float) $close->metadata['credit_sales_total'])->toBe(5000.0);
    $included = $close->metadata['accounting_invoices_included'] ?? [];
    expect($included)->toHaveCount(1);
    expect($included[0]['invoice_id'])->toBe($invoice->id);
    expect((float) $included[0]['amount'])->toBe(5000.0);

    // Revenue on the fuel account for the day, across both transactions, is meter revenue
    // only -- the invoice's own credit is reversed by the close's debit.
    $meterRevenue = (float) $close->metadata['total_revenue'];
    expect(revenueLedgerNetForAccount($f, $f['accounts']['4100']->id, '2026-09-15'))->toBe($meterRevenue);

    // AR booked once -- by the invoice itself, never again by the close.
    $arNet = (float) \Illuminate\Support\Facades\DB::table('acct.journal_entries as je')
        ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
        ->where('t.company_id', $f['company']->id)
        ->where('je.account_id', $f['accounts']['1100']->id)
        ->selectRaw('COALESCE(SUM(je.debit_amount),0) - COALESCE(SUM(je.credit_amount),0) as net')
        ->value('net');
    expect($arNet)->toBe(5000.0);

    expect($invoice->fresh()->included_in_close_id)->toBe($close->id);
});

test('an already-included invoice is not picked up again by a second close/amend', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = creditCloseFixture();
    $f['payload']['credit_sales'] = [];
    // The fixture's day had a 6,000 credit sale; without it, that money is in the drawer.
    $f['payload']['closing_cash'] += 6000;

    $invoice = accountingInvoiceFor($f, 5000, $f['accounts']['4100']->id);
    includeAccountingInvoiceRow($f, $invoice);
    app(\App\Modules\FuelStation\Services\DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    $pending = app(\App\Modules\FuelStation\Services\DailyCloseCreditSaleService::class)
        ->pendingAccountingInvoiceDetails($f['company'], '2026-09-15', [$f['accounts']['4100']->id]);
    expect($pending)->toBeEmpty();
});

test('a direct-delivery invoice is left alone', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = creditCloseFixture();
    $f['payload']['credit_sales'] = [];
    // The fixture's day had a 6,000 credit sale; without it, that money is in the drawer.
    $f['payload']['closing_cash'] += 6000;

    $invoice = accountingInvoiceFor($f, 5000, $f['accounts']['4100']->id, '2026-09-15', true);

    $posted = app(\App\Modules\FuelStation\Services\DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($posted['transaction_id']);

    expect($close->metadata['accounting_invoices_included'] ?? [])->toBeEmpty();
    expect((float) $close->metadata['credit_sales_total'])->toBe(0.0);
    expect($invoice->fresh()->included_in_close_id)->toBeNull();
});

test('an invoice on a non-fuel income account is left alone', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = creditCloseFixture();
    $f['payload']['credit_sales'] = [];
    // The fixture's day had a 6,000 credit sale; without it, that money is in the drawer.
    $f['payload']['closing_cash'] += 6000;

    // 1020 is a bank asset account in the fixture's chart, not a revenue account, but a
    // non-fuel revenue account behaves the same: it is simply never in fuelRevenueAccountIds.
    $otherIncome = \App\Modules\Accounting\Models\Account::create([
        'company_id' => $f['company']->id, 'code' => '4900', 'name' => 'Other income',
        'type' => 'revenue', 'subtype' => 'other_income', 'normal_balance' => 'credit', 'is_active' => true,
    ]);
    $invoice = accountingInvoiceFor($f, 5000, $otherIncome->id);

    $posted = app(\App\Modules\FuelStation\Services\DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($posted['transaction_id']);

    expect($close->metadata['accounting_invoices_included'] ?? [])->toBeEmpty();
    expect($invoice->fresh()->included_in_close_id)->toBeNull();
});

test('an invoice dated another day is left alone', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = creditCloseFixture();
    $f['payload']['credit_sales'] = [];
    // The fixture's day had a 6,000 credit sale; without it, that money is in the drawer.
    $f['payload']['closing_cash'] += 6000;

    $invoice = accountingInvoiceFor($f, 5000, $f['accounts']['4100']->id, '2026-09-14');

    $posted = app(\App\Modules\FuelStation\Services\DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($posted['transaction_id']);

    expect($close->metadata['accounting_invoices_included'] ?? [])->toBeEmpty();
    expect($invoice->fresh()->included_in_close_id)->toBeNull();
});

test('posting a close whose submitted credit rows omit a pending accounting invoice is refused', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = creditCloseFixture();
    // Omit the accounting invoice from credit_sales entirely -- only the fixture's own
    // manual credit row (if any) survives, none of which echoes the pending invoice.
    $f['payload']['credit_sales'] = [];
    // The fixture's day had a 6,000 credit sale; without it, that money is in the drawer.
    $f['payload']['closing_cash'] += 6000;

    accountingInvoiceFor($f, 5000, $f['accounts']['4100']->id);

    $dispatch = fn () => app(\App\Modules\FuelStation\Services\DailyCloseService::class)
        ->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    expect($dispatch)->toThrow(ValidationException::class);
});

test('an invoice a posted close counted cannot be voided afterwards', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = creditCloseFixture();
    $invoice = accountingInvoiceFor($f, 5000, $f['accounts']['4100']->id);
    includeAccountingInvoiceRow($f, $invoice);
    app(\App\Modules\FuelStation\Services\DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    expect(fn () => app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch(
        'invoice.void', ['id' => $invoice->id, 'reason' => 'mistake'], $f['user'], true
    )))->toThrow(\RuntimeException::class, 'counted in a posted Daily Close');
});

test('cash paid into the drawer for a direct delivery is counted in the close\'s money in', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $close = fn (array $f) => app(\App\Modules\FuelStation\Services\DailyCloseService::class)
        ->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    // An ordinary day, for comparison.
    $plain = creditCloseFixture();
    $plainVariance = (float) Transaction::findOrFail($close($plain)['transaction_id'])->metadata['variance'];

    // The same day, plus a direct delivery sold for 5,000 and paid in cash into the drawer.
    $f = creditCloseFixture();
    $invoice = accountingInvoiceFor($f, 5000, $f['accounts']['4100']->id, '2026-09-15', true);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('payment.create', [
        'customer_id' => $invoice->customer_id,
        'amount' => 5000,
        'method' => 'cash',
        'date' => '2026-09-15',
        'deposit_account_id' => $f['accounts']['1050']->id,
    ], $f['user'], true));
    $f['payload']['closing_cash'] += 5000; // the drawer holds the 5,000

    $posted = Transaction::findOrFail($close($f)['transaction_id']);

    // Counted as money in: the extra 5,000 in the drawer is expected, not an overage...
    expect((float) $posted->metadata['variance'])->toBe($plainVariance)
        // ...and the direct sale is not a pump credit sale.
        ->and($posted->metadata['accounting_invoices_included'] ?? [])->toBeEmpty();
});
