<?php

use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\DailyCloseReconciliationService;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

// Reuses creditCloseFixture() from DailyCloseCreditSalesTest.php (company, cash/bank/AR
// accounts, one customer with an AR account). Only loaded when the whole FuelStation
// directory is run together (module scope), per project convention.

function openInvoiceFixture(array $f, float $balance = 5000.0): Invoice
{
    return Invoice::create([
        'company_id' => $f['company']->id,
        'customer_id' => $f['customer']->id,
        'invoice_number' => 'INV-PAY-'.str()->random(6),
        'invoice_date' => '2026-09-14',
        'due_date' => '2026-10-14',
        'status' => 'sent',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'subtotal' => $balance,
        'total_amount' => $balance,
        'paid_amount' => 0,
        'balance' => $balance,
    ]);
}

test('parking payments-received rows keeps them pending: no payment, invoice untouched', function () {
    $f = creditCloseFixture();
    $invoice = openInvoiceFixture($f);
    $f['payload']['payments_received'] = [[
        'customer_id' => $f['customer']->id, 'invoice_id' => $invoice->id, 'amount' => 2000,
        'payment_account_id' => $f['accounts']['1050']->id, 'reference' => 'Rcpt-1',
    ]];

    app(DailyCloseReconciliationService::class)->park($f['company']->id, $f['payload'], $f['user']->id);

    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
    expect($invoice->fresh()->balance)->toEqual(5000.0);
    $draft = app(DailyCloseReconciliationService::class)->draft($f['company']->id, $f['payload']['date']);
    expect($draft['payments_received'][0]['amount'])->toBe(2000);
});

test('posting a cash-account payment received creates one canonical payment, reduces the invoice balance, and raises expected cash', function () {
    $f = creditCloseFixture();
    $invoice = openInvoiceFixture($f);
    $f['payload']['credit_sales'] = [];
    $f['payload']['payments_received'] = [[
        'customer_id' => $f['customer']->id, 'invoice_id' => $invoice->id, 'amount' => 2000,
        'payment_account_id' => $f['accounts']['1050']->id, 'reference' => 'Rcpt-1',
    ]];
    // Base close cash math (no credit_sales): opening 10000 + cash sales (30000-9000 card)=21000 -> 31000,
    // plus this payment's 2000 cash-in = 33000 expected.
    $f['payload']['closing_cash'] = 33000;

    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(1);
    expect((float) $invoice->fresh()->balance)->toBe(3000.0);
    $snapshot = $posted['metadata']['posting_snapshot'];
    expect((float) $snapshot['totals']['variance'])->toBe(0.0);
    $detail = $snapshot['payments_received'][0];
    expect($detail['affects_cash_drawer'])->toBeTrue()
        ->and((float) $detail['amount'])->toBe(2000.0);
});

test('posting a bank-account payment received does not change expected drawer cash', function () {
    $f = creditCloseFixture();
    $invoice = openInvoiceFixture($f);
    $f['payload']['credit_sales'] = [];
    $f['payload']['payments_received'] = [[
        'customer_id' => $f['customer']->id, 'invoice_id' => $invoice->id, 'amount' => 2000,
        'payment_account_id' => $f['accounts']['1020']->id, 'reference' => 'Rcpt-2',
    ]];
    // Bank-account payment never touches the drawer: expected closing cash is unaffected,
    // same as the base fixture with no payments.
    $f['payload']['closing_cash'] = 31000;

    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    expect((float) $invoice->fresh()->balance)->toBe(3000.0);
    $snapshot = $posted['metadata']['posting_snapshot'];
    expect((float) $snapshot['totals']['variance'])->toBe(0.0);
    expect($snapshot['payments_received'][0]['affects_cash_drawer'])->toBeFalse();
});

test('a payment amount exceeding the invoice balance is rejected', function () {
    $f = creditCloseFixture();
    $invoice = openInvoiceFixture($f, 1000.0);
    $f['payload']['credit_sales'] = [];
    $f['payload']['payments_received'] = [[
        'customer_id' => $f['customer']->id, 'invoice_id' => $invoice->id, 'amount' => 5000,
        'payment_account_id' => $f['accounts']['1050']->id,
    ]];

    expect(fn () => app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
    expect(Transaction::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('an other-company invoice or account is rejected', function () {
    $f = creditCloseFixture();
    $other = creditCloseFixture();
    $otherInvoice = openInvoiceFixture($other);
    $f['payload']['credit_sales'] = [];

    $f['payload']['payments_received'] = [[
        'customer_id' => $f['customer']->id, 'invoice_id' => $otherInvoice->id, 'amount' => 1000,
        'payment_account_id' => $f['accounts']['1050']->id,
    ]];
    expect(fn () => app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$f['company']->id]);
    $invoice = openInvoiceFixture($f);
    $f['payload']['payments_received'] = [[
        'customer_id' => $f['customer']->id, 'invoice_id' => $invoice->id, 'amount' => 1000,
        'payment_account_id' => $other['accounts']['1050']->id,
    ]];
    expect(fn () => app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('a standalone payment for the same date appears once in the close and is not double-counted', function () {
    $f = creditCloseFixture();
    $invoice = openInvoiceFixture($f);
    $paymentResult = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('payment.create', [
        'invoice' => $invoice->id, 'amount' => 2000, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['accounts']['1050']->id, 'ar_account_id' => $f['accounts']['1100']->id,
    ], $f['user'], true));
    $paymentId = $paymentResult['data']['id'];

    $f['payload']['credit_sales'] = [];
    // No inline row for the same invoice: the standalone payment is a canonical journal
    // already, and DailyCloseReconciliationService::sources() must pick it up exactly once.
    $f['payload']['payments_received'] = [];
    $f['payload']['closing_cash'] = 33000;

    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    expect((float) $posted['metadata']['posting_snapshot']['totals']['variance'])->toBe(0.0);

    $sources = app(DailyCloseReconciliationService::class)->sources($f['company']->id, '2026-09-15', $posted['transaction_id']);
    $paymentSource = collect($sources)->firstWhere('source_id', $paymentId);
    expect($paymentSource)->not->toBeNull()
        ->and((float) $paymentSource['cash_effect'])->toBe(2000.0);
});
