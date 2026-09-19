<?php

use App\Modules\Accounting\Models\Invoice;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

// Accounting directory fixtures provide ordinary users, company membership and posting templates.
function numberingInvoice(array $f, string $number): Invoice
{
    return Invoice::create([
        'company_id' => $f['company']->id, 'customer_id' => $f['customer']->id,
        'invoice_number' => $number, 'invoice_date' => '2026-09-18', 'due_date' => '2026-09-18',
        'status' => 'draft', 'currency' => 'PKR', 'base_currency' => 'PKR',
        'subtotal' => 1000, 'total_amount' => 1000, 'balance' => 1000,
    ]);
}

function postNumberedInvoice(array $f): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($f['owner'])->post("/{$f['company']->slug}/invoices", [
        'customer_id' => $f['customer']->id, 'currency' => 'PKR',
        'invoice_date' => '2026-09-18', 'status' => 'sent',
        'line_items' => [['description' => 'Fuel', 'quantity' => 1, 'unit_price' => 1000]],
    ]);
}

test('ordinary invoice numbering survives a newer fuel invoice and permits applying existing credit', function () {
    $f = httpAllocationFixture();
    numberingInvoice($f, 'INV-01001')->forceFill(['created_at' => now()->subDay()])->save();
    numberingInvoice($f, 'FS-20260918-ABCD');
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('payment.create', [
        'customer_id' => $f['customer']->id, 'amount' => 5000, 'method' => 'cash', 'date' => '2026-09-18',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ], $f['owner'], true));

    postNumberedInvoice($f)->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    $invoice = Invoice::where('company_id', $f['company']->id)->where('invoice_number', 'INV-01002')->sole();
    expect($invoice->transaction_id)->not->toBeNull();

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('payment.apply_credit', [
        'invoice_id' => $invoice->id, 'amount' => 1000,
    ], $f['owner'], true));
    expect((float) $invoice->fresh()->balance)->toBe(0.0)->and($invoice->fresh()->status)->toBe('paid');
});

test('invoice numbering uses the numeric maximum including deleted numbers rather than timestamps or lexical order', function () {
    $f = httpAllocationFixture();
    numberingInvoice($f, 'INV-100000')->forceFill(['created_at' => now()->subDays(3), 'deleted_at' => now()])->save();
    numberingInvoice($f, 'INV-99999')->forceFill(['created_at' => now()->subDay()])->save();
    numberingInvoice($f, 'INV-01001');
    numberingInvoice($f, 'FS-20260918-999999');
    expect(Invoice::generateInvoiceNumber($f['company']->id))->toBe('INV-100001');
});

test('invoice numbering treats a configured prefix literally and respects its starting number', function () {
    $f = httpAllocationFixture();
    $f['company']->update(['invoice_prefix' => 'A%_-', 'invoice_start_number' => 2000]);
    numberingInvoice($f, 'ABC-999999');
    expect(Invoice::generateInvoiceNumber($f['company']->id))->toBe('A%_-02000');
    numberingInvoice($f, 'A%_-02003');
    expect(Invoice::generateInvoiceNumber($f['company']->id))->toBe('A%_-02004');
});

test('inactive and credit-blocked buyers receive validation errors without creating invoices', function () {
    $f = httpAllocationFixture();
    $f['customer']->update(['is_active' => false]);
    postNumberedInvoice($f)->assertRedirect()->assertSessionHasErrors('customer_id');
    $f['customer']->update(['is_active' => true, 'is_credit_blocked' => true]);
    postNumberedInvoice($f)->assertRedirect()->assertSessionHasErrors('customer_id');
    expect(Invoice::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('invoice allocation holds a company lock even before the first invoice is inserted', function () {
    $f = httpAllocationFixture();
    config(['database.connections.numbering_contender' => config('database.connections.pgsql')]);
    $other = DB::connection('numbering_contender');
    $key = 'invoice-number:'.$f['company']->id;
    try {
        $other->beginTransaction();
        Invoice::generateInvoiceNumber($f['company']->id);
        expect($other->selectOne('SELECT pg_try_advisory_xact_lock(hashtext(?)) AS acquired', [$key])->acquired)->toBeFalse();
        expect($other->selectOne('SELECT pg_try_advisory_xact_lock(hashtext(?)) AS acquired', [$key.'-other'])->acquired)->toBeTrue();
    } finally {
        $other->rollBack();
        DB::purge('numbering_contender');
    }
});
