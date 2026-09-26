<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentAllocation;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * Covers the slimmed /payments form's server-side contract: invoice_ids (a ticked list,
 * not per-invoice amounts) plus method/currency/ar_account_id all resolving on their own
 * when the form leaves them out. Fixture mirrors PaymentAllocationTest's
 * httpAllocationFixture(), kept under a distinct name so both files can load in the same
 * Accounting module test run without redeclaring functions.
 */
function paymentSlimFormFixture(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Payment Slim Form Co '.str()->random(8),
        'slug' => 'payment-slim-form-'.str()->lower(str()->random(10)),
        'base_currency' => 'PKR',
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");

    app(CompanyRbacBootstrapper::class)->bootstrap($company);

    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($owner, 'owner'),
    );

    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);

    $ar = Account::create(['company_id' => $company->id, 'code' => '1100', 'name' => 'AR', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $cash = Account::create(['company_id' => $company->id, 'code' => '1050', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $bank = Account::create(['company_id' => $company->id, 'code' => '1060', 'name' => 'Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $revenue = Account::create(['company_id' => $company->id, 'code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue', 'subtype' => 'other_income', 'normal_balance' => 'credit']);

    foreach (['AR_INVOICE', 'AR_PAYMENT'] as $docType) {
        $template = PostingTemplate::create(['company_id' => $company->id, 'doc_type' => $docType, 'name' => $docType, 'is_active' => true, 'is_default' => true, 'effective_from' => '2026-01-01', 'version' => 1]);
        PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'AR', 'account_id' => $ar->id]);
        if ($docType === 'AR_INVOICE') {
            PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'REVENUE', 'account_id' => $revenue->id]);
        }
    }

    $customer = Customer::create(['company_id' => $company->id, 'customer_number' => 'C-1', 'name' => 'Slim form buyer', 'base_currency' => 'PKR', 'ar_account_id' => $ar->id, 'credit_limit' => 50000, 'is_active' => true]);

    return compact('owner', 'company', 'ar', 'cash', 'bank', 'customer');
}

function paymentSlimFormInvoice(array $f, float $total, string $date): Invoice
{
    return Invoice::create([
        'company_id' => $f['company']->id,
        'customer_id' => $f['customer']->id,
        'invoice_number' => 'INV-SLIM-'.str()->random(6),
        'invoice_date' => $date,
        'due_date' => $date,
        'status' => 'sent',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'subtotal' => $total,
        'total_amount' => $total,
        'paid_amount' => 0,
        'balance' => $total,
    ]);
}

test('invoice_ids settles the ticked invoices oldest-first and leaves the remainder on account', function () {
    $f = paymentSlimFormFixture();
    $older = paymentSlimFormInvoice($f, 1000, '2026-09-01');
    $newer = paymentSlimFormInvoice($f, 1000, '2026-09-05');

    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'invoice_ids' => [$newer->id, $older->id],
        'amount' => 1500,
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    // Oldest-first regardless of the order ids were submitted in.
    expect((float) $older->fresh()->balance)->toBe(0.0)
        ->and((float) $newer->fresh()->balance)->toBe(500.0);

    $payment = Payment::where('company_id', $f['company']->id)->sole();
    expect((float) $payment->amount)->toBe(1500.0)
        ->and(PaymentAllocation::where('payment_id', $payment->id)->whereNull('invoice_id')->exists())->toBeFalse();
});

test('no invoices ticked auto-allocates oldest-first across every open invoice', function () {
    $f = paymentSlimFormFixture();
    $older = paymentSlimFormInvoice($f, 1000, '2026-09-01');
    $newer = paymentSlimFormInvoice($f, 2000, '2026-09-05');

    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'amount' => 1500,
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
    ]);

    $response->assertSessionHasNoErrors();

    expect((float) $older->fresh()->balance)->toBe(0.0)
        ->and((float) $newer->fresh()->balance)->toBe(1500.0);
});

test('an unticked amount beyond what invoice_ids owe stays as customer credit', function () {
    $f = paymentSlimFormFixture();
    $invoice = paymentSlimFormInvoice($f, 500, '2026-09-01');

    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'invoice_ids' => [$invoice->id],
        'amount' => 800,
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
    ]);

    $response->assertSessionHasNoErrors();
    expect((float) $invoice->fresh()->balance)->toBe(0.0);

    $payment = Payment::where('company_id', $f['company']->id)->sole();
    $onAccountRow = PaymentAllocation::where('payment_id', $payment->id)->whereNull('invoice_id')->sole();
    expect((float) $onAccountRow->amount_allocated)->toBe(300.0);
});

test('payment_method left blank derives cash from a cash deposit account', function () {
    $f = paymentSlimFormFixture();

    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'amount' => 100,
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
    ]);

    $response->assertSessionHasNoErrors();
    expect(Payment::where('company_id', $f['company']->id)->sole()->payment_method)->toBe('cash');
});

test('payment_method left blank derives bank_transfer from a bank deposit account', function () {
    $f = paymentSlimFormFixture();

    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'amount' => 100,
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['bank']->id,
    ]);

    $response->assertSessionHasNoErrors();
    expect(Payment::where('company_id', $f['company']->id)->sole()->payment_method)->toBe('bank_transfer');
});

test('currency and ar_account_id left blank both default correctly', function () {
    $f = paymentSlimFormFixture();

    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'amount' => 250,
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
    ]);

    $response->assertSessionHasNoErrors();
    $payment = Payment::where('company_id', $f['company']->id)->sole();
    expect($payment->currency)->toBe($f['company']->base_currency)
        ->and($payment->transaction_id)->not->toBeNull(); // posted, so AR resolution succeeded
});
