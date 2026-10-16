<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/WithholdingFixtures.php';

function whtPayableId(array $f): string
{
    return Account::where('company_id', $f['company']->id)->where('name', 'Withholding Tax Payable')->value('id');
}

test('the withholding columns exist', function () {
    expect(Schema::hasColumns('acct.tax_rates', ['section', 'rate_non_filer', 'applies_to']))->toBeTrue()
        ->and(Schema::hasColumns('acct.vendors', ['filer_status', 'default_wht_rate_id']))->toBeTrue()
        ->and(Schema::hasColumns('acct.customers', ['filer_status', 'default_wht_rate_id']))->toBeTrue()
        ->and(Schema::hasColumns('acct.bill_payments', ['wht_rate_id', 'wht_rate', 'wht_amount', 'wht_section', 'wht_filer_status']))->toBeTrue()
        ->and(Schema::hasColumns('acct.payments', ['wht_rate_id', 'wht_rate', 'wht_amount', 'wht_section', 'wht_certificate_number']))->toBeTrue()
        ->and(Schema::hasColumns('acct.company_tax_settings', ['wht_payable_account_id', 'advance_tax_account_id']))->toBeTrue();
});

test('paying 100,000 at 4% pays the vendor 96,000, owes 4,000 and settles the bill', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);
    $bill = whtBill($f, 100000);

    $payment = whtPayBill($f, $bill, 100000, $rate->id);

    $payable = whtPayableId($f);
    expect(whtLine($payment->transaction_id, $f['ap']->id, 'debit'))->toBe(100000.0)
        ->and(whtLine($payment->transaction_id, $f['bank']->id, 'credit'))->toBe(96000.0)
        ->and(whtLine($payment->transaction_id, $payable, 'credit'))->toBe(4000.0)
        ->and((float) $payment->wht_amount)->toBe(4000.0)
        ->and((float) $payment->wht_rate)->toBe(4.0)
        ->and($payment->wht_section)->toBe('153(1)(b)')
        ->and($bill->fresh()->status)->toBe('paid')
        ->and((float) $bill->fresh()->balance)->toBe(0.0);

    $entries = Transaction::find($payment->transaction_id)->journalEntries;
    expect((float) $entries->sum('debit_amount'))->toBe((float) $entries->sum('credit_amount'));
});

test('the payable account is created on first use and reused', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);
    $bill = whtBill($f, 10000);

    expect(Account::where('company_id', $f['company']->id)->where('name', 'Withholding Tax Payable')->count())->toBe(0);
    whtPayBill($f, $bill, 5000, $rate->id);
    whtPayBill($f, $bill, 5000, $rate->id);

    $accounts = Account::where('company_id', $f['company']->id)->where('name', 'Withholding Tax Payable')->get();
    expect($accounts)->toHaveCount(1)
        ->and($accounts->first()->code)->toBe('2310')
        ->and($accounts->first()->type)->toBe('liability');
});

test('two part-payments each withhold on what is paid now', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);
    $bill = whtBill($f, 100000);

    $first = whtPayBill($f, $bill, 40000, $rate->id);
    $second = whtPayBill($f, $bill, 30000, $rate->id);

    expect((float) $first->wht_amount)->toBe(1600.0)
        ->and((float) $second->wht_amount)->toBe(1200.0)
        ->and(whtLine($first->transaction_id, $f['bank']->id, 'credit'))->toBe(38400.0)
        ->and(whtLine($second->transaction_id, $f['bank']->id, 'credit'))->toBe(28800.0)
        ->and(round((float) BillPayment::where('company_id', $f['company']->id)->sum('wht_amount'), 2))->toBe(2800.0)
        ->and($bill->fresh()->status)->toBe('partial');
});

test('a non-filer vendor gets the non-filer rate', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $f['vendor']->update(['filer_status' => 'non_filer']);
    $rate = whtRate($f['company'], 4, 8);
    $bill = whtBill($f, 100000);

    $payment = whtPayBill($f, $bill, 100000, $rate->id);

    expect((float) $payment->wht_amount)->toBe(8000.0)
        ->and((float) $payment->wht_rate)->toBe(8.0)
        ->and($payment->wht_filer_status)->toBe('non_filer')
        ->and(whtLine($payment->transaction_id, $f['bank']->id, 'credit'))->toBe(92000.0);
});

test('a rate with no non-filer value is refused for a non-filer, not given the filer rate', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $f['vendor']->update(['filer_status' => 'non_filer']);
    $rate = whtRate($f['company'], 4, null);
    $bill = whtBill($f, 100000);

    expect(fn () => whtPayBill($f, $bill, 100000, $rate->id))->toThrow(ValidationException::class);
    expect(BillPayment::where('company_id', $f['company']->id)->count())->toBe(0)
        ->and($bill->fresh()->status)->toBe('received');
});

test('voiding the payment reverses the withholding line too', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);
    $bill = whtBill($f, 100000);
    $payment = whtPayBill($f, $bill, 100000, $rate->id);
    // The void is posted today; give today a period.
    AccountingPeriod::create(['company_id' => $f['company']->id, 'fiscal_year_id' => $f['sepPeriod']->fiscal_year_id, 'name' => 'October', 'period_number' => 10, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']);

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill_payment.void', ['id' => $payment->id], $f['user'], true));

    $lines = JournalEntry::where('company_id', $f['company']->id)->where('account_id', whtPayableId($f))->get();
    expect($lines)->toHaveCount(2)
        ->and((float) $lines->sum('credit_amount'))->toBe(4000.0)
        ->and((float) $lines->sum('debit_amount'))->toBe(4000.0)
        ->and((float) $bill->fresh()->balance)->toBe(100000.0);
});

test('with tax off a rate id is ignored and nothing is withheld', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], false);
    $rate = whtRate($f['company'], 4);
    $bill = whtBill($f, 100000);

    $payment = whtPayBill($f, $bill, 100000, $rate->id);

    expect((float) $payment->wht_amount)->toBe(0.0)
        ->and($payment->wht_rate_id)->toBeNull()
        ->and(whtLine($payment->transaction_id, $f['bank']->id, 'credit'))->toBe(100000.0)
        ->and(Transaction::find($payment->transaction_id)->journalEntries)->toHaveCount(2)
        ->and(Account::where('company_id', $f['company']->id)->where('name', 'Withholding Tax Payable')->exists())->toBeFalse();
});

test('a payment made without a rate is posted exactly as before', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $bill = whtBill($f, 1000);

    $payment = whtPayBill($f, $bill, 1000, null);

    expect(whtLine($payment->transaction_id, $f['bank']->id, 'credit'))->toBe(1000.0)
        ->and(Transaction::find($payment->transaction_id)->journalEntries)->toHaveCount(2);
});

test('a foreign payment withholds on the base-currency amount and posts it in base', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    DB::table('public.currencies')->insertOrIgnore(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    $rate = whtRate($f['company'], 4);

    $result = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill_payment.create', [
        'vendor_id' => $f['vendor']->id, 'payment_date' => '2026-09-01', 'amount' => 1000,
        'currency' => 'USD', 'exchange_rate' => '280.00000000', 'base_currency' => 'PKR', 'payment_method' => 'bank_transfer',
        'payment_account_id' => $f['bank']->id, 'wht_rate_id' => $rate->id,
    ], $f['user'], true));
    $payment = BillPayment::find($result['data']['id']);

    expect((float) $payment->wht_amount)->toBe(11200.0)
        ->and(whtLine($payment->transaction_id, $f['ap']->id, 'debit'))->toBe(280000.0)
        ->and(whtLine($payment->transaction_id, $f['bank']->id, 'credit'))->toBe(268800.0)
        ->and(whtLine($payment->transaction_id, whtPayableId($f), 'credit'))->toBe(11200.0);
});

test('editing the amount re-posts the withholding at the rate it was taken at', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);
    $bill = whtBill($f, 100000);
    $payment = whtPayBill($f, $bill, 100000, $rate->id);

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill_payment.update', ['id' => $payment->id, 'amount' => 50000], $f['user'], true));

    $payment = $payment->fresh();
    expect((float) $payment->wht_amount)->toBe(2000.0)
        ->and(whtLine($payment->transaction_id, $payment->payment_account_id, 'credit'))->toBe(48000.0)
        ->and(whtLine($payment->transaction_id, whtPayableId($f), 'credit'))->toBe(2000.0);
});

test('the certificate page shows the section, gross, rate and tax withheld', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);
    $f['vendor']->update(['tax_id' => '1234567-8']);
    $payment = whtPayBill($f, whtBill($f, 100000), 100000, $rate->id, ['reference_number' => 'CHQ-77']);

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/bill-payments/{$payment->id}/wht-certificate")
        ->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('accounting/bill-payments/WhtCertificate', false)
            ->where('certificate.section', '153(1)(b)')
            ->where('certificate.gross', 100000)
            ->where('certificate.wht', 4000)
            ->where('certificate.net', 96000)
            ->where('certificate.rate', 4)
            ->where('certificate.reference', 'CHQ-77')
            ->where('certificate.vendor.tax_id', '1234567-8')
            ->where('certificate.voided', false));
});

test('there is no certificate for a payment that withheld nothing', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $payment = whtPayBill($f, whtBill($f, 1000), 1000, null);

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/bill-payments/{$payment->id}/wht-certificate")->assertNotFound();
});

test('the payment form offers withholding rates only while tax is on', function () {
    $f = billPaymentEditFixture();
    whtRate($f['company'], 4);

    whtTax($f['company'], false);
    $this->actingAs($f['user'])->get("/{$f['company']->slug}/bill-payments/create")
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('auth.taxEnabled', false)->where('whtRates', []));

    whtTax($f['company'], true);
    $this->actingAs($f['user'])->get("/{$f['company']->slug}/bill-payments/create")
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('auth.taxEnabled', true)->has('whtRates', 1)->where('whtRates.0.section', '153(1)(b)'));
});

test('a rate for payments we receive is not offered on a vendor payment', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    whtRate($f['company'], 4, null, 'payments_received');

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/bill-payments/create")
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('whtRates', []));
});

test('the payment form posts the chosen rate through the FormRequest', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);
    $bill = whtBill($f, 100000);

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/bill-payments", [
        'vendor_id' => $f['vendor']->id, 'payment_date' => '2026-09-01', 'amount' => 100000,
        'currency' => 'PKR', 'base_currency' => 'PKR', 'payment_method' => 'bank_transfer',
        'payment_account_id' => $f['bank']->id, 'wht_rate_id' => $rate->id,
        'allocations' => [['bill_id' => $bill->id, 'amount_allocated' => 100000]],
    ])->assertSessionHasNoErrors();

    expect((float) BillPayment::where('company_id', $f['company']->id)->value('wht_amount'))->toBe(4000.0);
});

test('a payment that withheld tax cannot be split by a correction', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $payment = whtPayBill($f, whtBill($f, 100000), 100000, whtRate($f['company'], 4)->id);

    expect(fn () => app(\App\Modules\Accounting\Services\BillCorrectionService::class)->billPaymentSplit($payment, [
        ['vendor_id' => $f['vendor']->id, 'amount' => 50000], ['vendor_id' => $f['vendor']->id, 'amount' => 50000],
    ], 'test'))->toThrow(ValidationException::class);
});
