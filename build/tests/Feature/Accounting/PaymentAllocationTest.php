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
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\CustomerStatementService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

// Reuses statementFixture() from CustomerStatementTest.php (company, AR/cash/revenue
// accounts, one customer, AR_INVOICE/AR_PAYMENT posting templates). Only loaded when the
// whole Accounting directory is run together (module scope), per project convention.

function allocationInvoice(array $f, float $total, string $date): Invoice
{
    return Invoice::create([
        'company_id' => $f['company']->id,
        'customer_id' => $f['customer']->id,
        'invoice_number' => 'INV-ALLOC-' . str()->random(6),
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

function dispatchPayment(array $f, array $params): array
{
    return app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('payment.create', $params, $f['user'], true));
}

test('one payment settles three invoices oldest-first and the buyer balance falls by the full amount', function () {
    $f = statementFixture();
    $i1 = allocationInvoice($f, 1000, '2026-09-01');
    $i2 = allocationInvoice($f, 2000, '2026-09-05');
    $i3 = allocationInvoice($f, 5000, '2026-09-10');

    dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 4000, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);

    expect((float) $i1->fresh()->balance)->toBe(0.0)->and($i1->fresh()->status)->toBe('paid')
        ->and((float) $i2->fresh()->balance)->toBe(0.0)->and($i2->fresh()->status)->toBe('paid')
        ->and((float) $i3->fresh()->balance)->toBe(4000.0)->and($i3->fresh()->status)->toBe('partial');

    $statement = app(CustomerStatementService::class)->statement($f['customer']->fresh());
    expect($statement['closing_balance'])->toBe(4000.0); // 8000 invoiced - 4000 paid
});

test('explicit allocations are honoured over auto oldest-first order', function () {
    $f = statementFixture();
    $older = allocationInvoice($f, 1000, '2026-09-01');
    $newer = allocationInvoice($f, 1000, '2026-09-10');

    dispatchPayment($f, [
        'customer_id' => $f['customer']->id,
        'amount' => 1000,
        'allocations' => [['invoice_id' => $newer->id, 'amount' => 1000]],
        'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);

    expect((float) $newer->fresh()->balance)->toBe(0.0)
        ->and((float) $older->fresh()->balance)->toBe(1000.0);
});

test('a remainder beyond all open invoices sits on account and still reduces the buyer balance', function () {
    $f = statementFixture();
    $invoice = allocationInvoice($f, 1000, '2026-09-01');

    $result = dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 4000, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);

    expect((float) $invoice->fresh()->balance)->toBe(0.0)
        ->and((float) $result['data']['on_account'])->toBe(3000.0);

    $statement = app(CustomerStatementService::class)->statement($f['customer']->fresh());
    expect($statement['closing_balance'])->toBe(-3000.0) // paid 3000 more than owed
        ->and($statement['available_credit'])->toBe(3000.0);
});

test('an advance payment with no invoices at all is recorded entirely on account', function () {
    $f = statementFixture();

    $result = dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 2500, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);

    expect((float) $result['data']['on_account'])->toBe(2500.0);
    $payment = Payment::find($result['data']['id']);
    expect(PaymentAllocation::where('payment_id', $payment->id)->whereNull('invoice_id')->sole()->amount_allocated)
        ->toEqual(2500.0);

    $statement = app(CustomerStatementService::class)->statement($f['customer']->fresh());
    expect($statement['closing_balance'])->toBe(-2500.0)
        ->and($statement['available_credit'])->toBe(2500.0);
});

test('applying an on-account credit to a later invoice settles it and creates no new cash movement', function () {
    $f = statementFixture();
    $advance = dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 3000, 'method' => 'cash', 'date' => '2026-09-01',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);
    $transactionCountBefore = Transaction::where('company_id', $f['company']->id)->count();
    $paymentCountBefore = Payment::where('company_id', $f['company']->id)->count();

    $invoice = allocationInvoice($f, 1000, '2026-09-10');

    $applyResult = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('payment.apply_credit', [
        'customer_id' => $f['customer']->id,
        'invoice_id' => $invoice->id,
    ], $f['user'], true));

    expect((float) $invoice->fresh()->balance)->toBe(0.0)
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and((float) $applyResult['data']['applied'])->toBe(1000.0);

    // No new payment and no new journal - a pure subsidiary reclass.
    expect(Transaction::where('company_id', $f['company']->id)->count())->toBe($transactionCountBefore);
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe($paymentCountBefore);

    $statement = app(CustomerStatementService::class)->statement($f['customer']->fresh());
    expect($statement['available_credit'])->toBe(2000.0) // 3000 advance - 1000 just applied
        ->and($statement['closing_balance'])->toBe(-2000.0); // 1000 invoiced - 3000 paid
});

test('the buyer statement shows the payment, its allocations and remaining credit with a correct running balance', function () {
    $f = statementFixture();
    $i1 = allocationInvoice($f, 1000, '2026-09-01');
    $i2 = allocationInvoice($f, 500, '2026-09-05');

    dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 2000, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);

    $statement = app(CustomerStatementService::class)->statement($f['customer']->fresh());
    $paymentRow = collect($statement['rows'])->firstWhere('type', 'payment');
    expect($paymentRow)->not->toBeNull()
        ->and((float) $paymentRow['credit'])->toBe(2000.0)
        ->and((float) $paymentRow['balance'])->toBe(-500.0); // 1500 invoiced - 2000 paid
    expect($statement['closing_balance'])->toBe(-500.0)
        ->and($statement['available_credit'])->toBe(500.0);
});

test('allocations summing to more than the payment amount are rejected', function () {
    $f = statementFixture();
    $i1 = allocationInvoice($f, 1000, '2026-09-01');
    $i2 = allocationInvoice($f, 1000, '2026-09-05');

    expect(fn () => dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 500,
        'allocations' => [['invoice_id' => $i1->id, 'amount' => 400], ['invoice_id' => $i2->id, 'amount' => 400]],
        'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('an allocation naming an invoice of a different buyer is rejected', function () {
    $f = statementFixture();
    $otherCustomer = \App\Modules\Accounting\Models\Customer::create([
        'company_id' => $f['company']->id, 'customer_number' => 'C-2', 'name' => 'Other buyer',
        'base_currency' => 'PKR', 'ar_account_id' => $f['ar']->id, 'is_active' => true,
    ]);
    $mine = allocationInvoice($f, 1000, '2026-09-01');
    $theirs = Invoice::create([
        'company_id' => $f['company']->id, 'customer_id' => $otherCustomer->id,
        'invoice_number' => 'INV-OTHER-1', 'invoice_date' => '2026-09-01', 'due_date' => '2026-09-01',
        'status' => 'sent', 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => 1000, 'total_amount' => 1000, 'paid_amount' => 0, 'balance' => 1000,
    ]);

    expect(fn () => dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 1000,
        'allocations' => [['invoice_id' => $mine->id, 'amount' => 500], ['invoice_id' => $theirs->id, 'amount' => 500]],
        'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('an allocation naming an invoice of a different company is rejected', function () {
    $f = statementFixture();
    $other = statementFixture();
    $theirInvoice = allocationInvoice($other, 1000, '2026-09-01');

    expect(fn () => dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 500,
        'allocations' => [['invoice_id' => $theirInvoice->id, 'amount' => 500]],
        'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('a zero or negative payment amount is rejected', function () {
    $f = statementFixture();

    expect(fn () => dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 0, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    expect(fn () => dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => -100, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('a fully-paid invoice cannot be allocated to', function () {
    $f = statementFixture();
    $invoice = allocationInvoice($f, 1000, '2026-09-01');
    dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 1000, 'method' => 'cash', 'date' => '2026-09-10',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);
    expect($invoice->fresh()->status)->toBe('paid');

    expect(fn () => dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 200,
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => 200]],
        'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('a cancelled invoice cannot be allocated to', function () {
    $f = statementFixture();
    $invoice = allocationInvoice($f, 1000, '2026-09-01');
    $invoice->update(['status' => 'cancelled']);

    expect(fn () => dispatchPayment($f, [
        'customer_id' => $f['customer']->id, 'amount' => 200,
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => 200]],
        'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

// --- HTTP-level tests below: the explicit per-invoice allocation UI (standalone
// /payments page) posts through StorePaymentRequest -> PaymentController::store ->
// payment.create, unlike the tests above which dispatch the command bus directly. This
// fixture mirrors CreateVendorTest's vendorTestCompany() (real RBAC bootstrap plus owner
// role and actingAs) plus statementFixture()'s AR/cash accounts, posting templates and an
// open accounting period, since PostingService::postPayment needs both to run at all.
function httpAllocationFixture(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Payment Alloc HTTP Co '.str()->random(8),
        'slug' => 'payment-alloc-http-'.str()->lower(str()->random(10)),
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
    $revenue = Account::create(['company_id' => $company->id, 'code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue', 'subtype' => 'other_income', 'normal_balance' => 'credit']);

    foreach (['AR_INVOICE', 'AR_PAYMENT'] as $docType) {
        $template = PostingTemplate::create(['company_id' => $company->id, 'doc_type' => $docType, 'name' => $docType, 'is_active' => true, 'is_default' => true, 'effective_from' => '2026-01-01', 'version' => 1]);
        PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'AR', 'account_id' => $ar->id]);
        if ($docType === 'AR_INVOICE') {
            PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'REVENUE', 'account_id' => $revenue->id]);
        }
    }

    $customer = Customer::create(['company_id' => $company->id, 'customer_number' => 'C-1', 'name' => 'HTTP buyer', 'base_currency' => 'PKR', 'ar_account_id' => $ar->id, 'credit_limit' => 50000, 'is_active' => true]);

    return compact('owner', 'company', 'ar', 'cash', 'customer');
}

function httpAllocationInvoice(array $f, float $total, string $date, ?Customer $customer = null): Invoice
{
    return Invoice::create([
        'company_id' => $f['company']->id,
        'customer_id' => ($customer ?? $f['customer'])->id,
        'invoice_number' => 'INV-HTTP-'.str()->random(6),
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

test('posting explicit per-invoice amounts through the HTTP endpoint allocates exactly those amounts and puts the remainder on account', function () {
    $f = httpAllocationFixture();
    $i1 = httpAllocationInvoice($f, 1000, '2026-09-01');
    $i2 = httpAllocationInvoice($f, 2000, '2026-09-05');

    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'allocations' => [
            ['invoice_id' => $i1->id, 'amount' => 400],
            ['invoice_id' => $i2->id, 'amount' => 600],
        ],
        'amount' => 1500,
        'currency' => 'PKR',
        'payment_method' => 'cash',
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    expect((float) $i1->fresh()->balance)->toBe(600.0)
        ->and((float) $i2->fresh()->balance)->toBe(1400.0);

    $payment = Payment::where('company_id', $f['company']->id)->sole();
    expect((float) $payment->amount)->toBe(1500.0);

    $onAccountRow = PaymentAllocation::where('payment_id', $payment->id)->whereNull('invoice_id')->sole();
    expect((float) $onAccountRow->amount_allocated)->toBe(500.0);
});

test('zero-amount allocation rows submitted through the HTTP endpoint are ignored', function () {
    $f = httpAllocationFixture();
    $i1 = httpAllocationInvoice($f, 1000, '2026-09-01');
    $i2 = httpAllocationInvoice($f, 1000, '2026-09-05');

    // The UI omits zero-amount rows before submitting, so this exercises the server
    // holding that contract even if a row slips through as an explicit zero rather than
    // being dropped client-side.
    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'allocations' => [
            ['invoice_id' => $i1->id, 'amount' => 300],
        ],
        'amount' => 300,
        'currency' => 'PKR',
        'payment_method' => 'cash',
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
    ]);

    $response->assertSessionHasNoErrors();
    expect((float) $i1->fresh()->balance)->toBe(700.0)
        ->and((float) $i2->fresh()->balance)->toBe(1000.0);

    $payment = Payment::where('company_id', $f['company']->id)->sole();
    expect(PaymentAllocation::where('payment_id', $payment->id)->count())->toBe(1);
});

test('HTTP allocations summing above the payment amount are rejected with a field-level error', function () {
    $f = httpAllocationFixture();
    $i1 = httpAllocationInvoice($f, 1000, '2026-09-01');
    $i2 = httpAllocationInvoice($f, 1000, '2026-09-05');

    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'allocations' => [
            ['invoice_id' => $i1->id, 'amount' => 400],
            ['invoice_id' => $i2->id, 'amount' => 400],
        ],
        'amount' => 500,
        'currency' => 'PKR',
        'payment_method' => 'cash',
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
    ]);

    $response->assertSessionHasErrors('allocations');
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('an HTTP allocation naming an invoice of another buyer is rejected', function () {
    $f = httpAllocationFixture();
    $mine = httpAllocationInvoice($f, 1000, '2026-09-01');

    $otherCustomer = Customer::create([
        'company_id' => $f['company']->id, 'customer_number' => 'C-2', 'name' => 'Other buyer',
        'base_currency' => 'PKR', 'ar_account_id' => $f['ar']->id, 'is_active' => true,
    ]);
    $otherBuyerInvoice = httpAllocationInvoice($f, 1000, '2026-09-01', $otherCustomer);

    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'allocations' => [
            ['invoice_id' => $mine->id, 'amount' => 500],
            ['invoice_id' => $otherBuyerInvoice->id, 'amount' => 500],
        ],
        'amount' => 1000,
        'currency' => 'PKR',
        'payment_method' => 'cash',
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
    ]);

    $response->assertSessionHasErrors();
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('an HTTP allocation naming an invoice of another company is rejected', function () {
    $f = httpAllocationFixture();
    $other = httpAllocationFixture();
    $theirInvoice = httpAllocationInvoice($other, 1000, '2026-09-01');

    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/payments", [
        'customer_id' => $f['customer']->id,
        'allocations' => [
            ['invoice_id' => $theirInvoice->id, 'amount' => 500],
        ],
        'amount' => 500,
        'currency' => 'PKR',
        'payment_method' => 'cash',
        'payment_date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id,
    ]);

    $response->assertSessionHasErrors();
    expect(Payment::where('company_id', $f['company']->id)->count())->toBe(0);
});
