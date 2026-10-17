<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/WithholdingFixtures.php';

/** A PKR company with an open Sept and Oct 2026, an AR account, a bank, a customer and one 100,000 invoice. */
function whtReceivedFixture(float $invoiceTotal = 100000): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Wht Received Co', 'slug' => 'wht-received-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id, 'base_currency' => 'PKR',
    ]);
    // An owner, so the same fixture can be driven over HTTP.
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    enterCompany($company);
    DB::table('public.currencies')->insertOrIgnore(['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨']);

    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    foreach ([[9, 'September', '2026-09-01', '2026-09-30'], [10, 'October', '2026-10-01', '2026-10-31']] as [$n, $name, $from, $to]) {
        AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => $name, 'period_number' => $n, 'start_date' => $from, 'end_date' => $to]);
    }

    $ar = Account::create(['company_id' => $company->id, 'code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'currency' => 'PKR']);
    $bank = Account::create(['company_id' => $company->id, 'code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR']);
    $company->update(['ar_account_id' => $ar->id]);

    $customer = Customer::create(['company_id' => $company->id, 'customer_number' => 'CUST-'.str()->upper(str()->random(6)), 'name' => 'Big Buyer', 'base_currency' => 'PKR', 'ar_account_id' => $ar->id, 'is_active' => true]);
    $invoice = Invoice::create([
        'company_id' => $company->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-WHT-1',
        'invoice_date' => '2026-09-10', 'due_date' => '2026-09-30', 'status' => 'sent',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => $invoiceTotal, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => $invoiceTotal,
        'paid_amount' => 0, 'balance' => $invoiceTotal, 'base_amount' => $invoiceTotal,
    ]);

    return compact('user', 'company', 'ar', 'bank', 'customer', 'invoice');
}

function whtReceive(array $f, array $extra = [], float $amount = 100000): Payment
{
    $result = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('payment.create', [
        'invoice' => $f['invoice']->id, 'amount' => $amount, 'method' => 'bank_transfer', 'currency' => 'PKR',
        'date' => '2026-09-15', 'deposit_account_id' => $f['bank']->id, ...$extra,
    ], $f['user'], true));

    return Payment::find($result['data']['id']);
}

function whtAdvanceTaxId(array $f): ?string
{
    return Account::where('company_id', $f['company']->id)->where('name', 'like', 'Advance Income Tax%')->value('id');
}

test('a customer paying 96,000 with 4,000 withheld settles the 100,000 invoice', function () {
    $f = whtReceivedFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);

    $payment = whtReceive($f, ['wht_rate_id' => $rate->id, 'wht_certificate_number' => 'CPR-991']);

    $advance = whtAdvanceTaxId($f);
    expect(whtLine($payment->transaction_id, $f['bank']->id, 'debit'))->toBe(96000.0)
        ->and(whtLine($payment->transaction_id, $advance, 'debit'))->toBe(4000.0)
        ->and(whtLine($payment->transaction_id, $f['ar']->id, 'credit'))->toBe(100000.0)
        ->and(Account::find($advance)->code)->toBe('1196')
        ->and(Account::find($advance)->type)->toBe('asset')
        ->and((float) $payment->wht_amount)->toBe(4000.0)
        ->and((float) $payment->wht_rate)->toBe(4.0)
        ->and($payment->wht_section)->toBe('153(1)(b)')
        ->and($payment->wht_certificate_number)->toBe('CPR-991')
        ->and($f['invoice']->fresh()->status)->toBe('paid')
        ->and((float) $f['invoice']->fresh()->balance)->toBe(0.0);

    $entries = Transaction::find($payment->transaction_id)->journalEntries;
    expect((float) $entries->sum('debit_amount'))->toBe((float) $entries->sum('credit_amount'));
});

test('a customer can deduct an odd amount instead of a rate', function () {
    $f = whtReceivedFixture();
    whtTax($f['company'], true);

    $payment = whtReceive($f, ['wht_amount' => 3500]);

    expect(whtLine($payment->transaction_id, $f['bank']->id, 'debit'))->toBe(96500.0)
        ->and(whtLine($payment->transaction_id, whtAdvanceTaxId($f), 'debit'))->toBe(3500.0)
        ->and($payment->wht_rate_id)->toBeNull()
        ->and($f['invoice']->fresh()->status)->toBe('paid');
});

test('an explicit amount wins over a rate and keeps the rate for the register', function () {
    $f = whtReceivedFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);

    $payment = whtReceive($f, ['wht_rate_id' => $rate->id, 'wht_amount' => 3800]);

    expect((float) $payment->wht_amount)->toBe(3800.0)
        ->and($payment->wht_rate_id)->toBe($rate->id)
        ->and($payment->wht_rate)->toBeNull();
});

test('a non-filer customer is deducted at the non-filer rate', function () {
    $f = whtReceivedFixture();
    whtTax($f['company'], true);
    $f['customer']->update(['filer_status' => 'non_filer']);
    $rate = whtRate($f['company'], 4, 8);

    $payment = whtReceive($f, ['wht_rate_id' => $rate->id]);

    expect((float) $payment->wht_amount)->toBe(8000.0)->and($payment->wht_filer_status)->toBe('non_filer');
});

test('a rate with no non-filer value is refused for a non-filer customer', function () {
    $f = whtReceivedFixture();
    whtTax($f['company'], true);
    $f['customer']->update(['filer_status' => 'non_filer']);
    $rate = whtRate($f['company'], 4, null);

    expect(fn () => whtReceive($f, ['wht_rate_id' => $rate->id]))->toThrow(ValidationException::class);
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('a rate for payments we make is not accepted on a receipt', function () {
    $f = whtReceivedFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4, null, 'payments_made');

    expect(fn () => whtReceive($f, ['wht_rate_id' => $rate->id]))->toThrow(ValidationException::class);
});

test('voiding the receipt reverses the advance tax line and reopens the invoice', function () {
    $f = whtReceivedFixture();
    whtTax($f['company'], true);
    $payment = whtReceive($f, ['wht_rate_id' => whtRate($f['company'], 4)->id]);

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('payment.void', ['id' => $payment->id], $f['user'], true));

    $lines = JournalEntry::where('company_id', $f['company']->id)->where('account_id', whtAdvanceTaxId($f))->get();
    expect($lines)->toHaveCount(2)
        ->and((float) $lines->sum('debit_amount'))->toBe(4000.0)
        ->and((float) $lines->sum('credit_amount'))->toBe(4000.0)
        ->and((float) $f['invoice']->fresh()->balance)->toBe(100000.0);
});

test('with tax off the withheld amount and rate are ignored', function () {
    $f = whtReceivedFixture();
    whtTax($f['company'], false);
    $rate = whtRate($f['company'], 4);

    $payment = whtReceive($f, ['wht_rate_id' => $rate->id, 'wht_amount' => 4000]);

    expect((float) $payment->wht_amount)->toBe(0.0)
        ->and(whtLine($payment->transaction_id, $f['bank']->id, 'debit'))->toBe(100000.0)
        ->and(Transaction::find($payment->transaction_id)->journalEntries)->toHaveCount(2)
        ->and(whtAdvanceTaxId($f))->toBeNull();
});

test('a payment with no withholding posts exactly as before', function () {
    $f = whtReceivedFixture(1000);
    whtTax($f['company'], true);

    $payment = whtReceive($f, [], 1000);

    expect(whtLine($payment->transaction_id, $f['bank']->id, 'debit'))->toBe(1000.0)
        ->and(Transaction::find($payment->transaction_id)->journalEntries)->toHaveCount(2);
});

test('tax withheld cannot exceed what was paid', function () {
    $f = whtReceivedFixture();
    whtTax($f['company'], true);

    expect(fn () => whtReceive($f, ['wht_amount' => 100001]))->toThrow(ValidationException::class);
});

test('a bank charge and withholding both come off the deposit', function () {
    $f = whtReceivedFixture();
    whtTax($f['company'], true);
    $expense = Account::create(['company_id' => $f['company']->id, 'code' => '6500', 'name' => 'Bank Charges', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit']);
    $f['company']->update(['expense_account_id' => $expense->id]);

    $payment = whtReceive($f, ['wht_amount' => 4000, 'transaction_charge' => 100]);

    expect(whtLine($payment->transaction_id, $f['bank']->id, 'debit'))->toBe(95900.0)
        ->and(whtLine($payment->transaction_id, $expense->id, 'debit'))->toBe(100.0)
        ->and(whtLine($payment->transaction_id, $f['ar']->id, 'credit'))->toBe(100000.0);
});

test('the receive form posts the rate through the FormRequest and offers only receivable rates', function () {
    $f = whtReceivedFixture();
    whtTax($f['company'], true);
    $received = whtRate($f['company'], 4, null, 'payments_received', 'WHT-IN');
    whtRate($f['company'], 4, null, 'payments_made', 'WHT-OUT');

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/payments/create")
        ->assertInertia(fn (Assert $page) => $page->has('whtRates', 1)->where('whtRates.0.id', $received->id));

    $this->actingAs($f['user'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id, 'invoice_ids' => [$f['invoice']->id], 'amount' => 100000,
        'payment_date' => '2026-09-15', 'deposit_account_id' => $f['bank']->id, 'wht_rate_id' => $received->id,
        'wht_certificate_number' => 'C-1',
    ])->assertSessionHasNoErrors();

    $payment = Payment::where('company_id', $f['company']->id)->firstOrFail();
    expect((float) $payment->wht_amount)->toBe(4000.0)->and($payment->wht_certificate_number)->toBe('C-1');
});

test('the receive form offers no withholding while tax is off', function () {
    $f = whtReceivedFixture();
    whtTax($f['company'], false);
    whtRate($f['company'], 4);

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/payments/create")
        ->assertInertia(fn (Assert $page) => $page->where('auth.taxEnabled', false)->where('whtRates', []));
});

test('editing the amount re-posts the tax at the rate it was taken at', function () {
    $f = whtReceivedFixture();
    whtTax($f['company'], true);
    $payment = whtReceive($f, ['wht_rate_id' => whtRate($f['company'], 4)->id]);

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('payment.update', [
        'id' => $payment->id, 'invoice' => $f['invoice']->id, 'amount' => 50000, 'method' => 'bank_transfer',
    ], $f['user'], true));

    $payment = $payment->fresh();
    expect((float) $payment->wht_amount)->toBe(2000.0)
        ->and(whtLine($payment->transaction_id, $f['bank']->id, 'debit'))->toBe(48000.0)
        ->and(whtLine($payment->transaction_id, whtAdvanceTaxId($f), 'debit'))->toBe(2000.0);
});
