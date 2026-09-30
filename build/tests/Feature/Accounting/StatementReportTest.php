<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\BillPaymentAllocation;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\AccountStatementService;
use App\Modules\Accounting\Services\CustomerStatementService;
use App\Modules\Accounting\Services\VendorStatementService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Fixture shared by every test in this file: one company, an owner user with
 * every permission (so the report's REPORT_VIEW check passes), an open fiscal
 * year covering August and September 2026, a cash account, an AP account, an
 * AR account, a revenue account, one customer and one vendor.
 *
 * The clock is pinned to 2026-09-24 by every test via travelTo(), so "today"
 * (the report's default `to`) and "the first of this month" (the default
 * `from`) are both September 2026 -- the same range used explicitly below.
 */
function statementReportFixture(): array
{
    $user = User::factory()->withoutTwoFactor()->create();

    $company = Company::create([
        'name' => 'Statement Report Co',
        'slug' => 'statement-report-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    if (! DB::table('public.currencies')->where('code', 'PKR')->exists()) {
        DB::table('public.currencies')->insert(['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs']);
    }

    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'August', 'period_number' => 8, 'start_date' => '2026-08-01', 'end_date' => '2026-08-31']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);

    $mk = fn (string $code, string $name, string $type, string $subtype, string $normal, ?string $currency = 'PKR') => Account::create([
        'company_id' => $company->id, 'code' => $code, 'name' => $name, 'type' => $type,
        'subtype' => $subtype, 'normal_balance' => $normal, 'currency' => $currency, 'is_active' => true,
    ]);

    $cash = $mk('1050', 'Cash on Hand', 'asset', 'cash', 'debit');
    $bank = $mk('1000', 'HBL Current', 'asset', 'bank', 'debit');
    $ar = $mk('1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit');
    $ap = $mk('2100', 'Accounts Payable', 'liability', 'accounts_payable', 'credit');
    // Revenue accounts are not in the currency-allowed subtype list, so must be null.
    $revenue = $mk('4100', 'Sales Revenue', 'revenue', 'other_income', 'credit', null);

    foreach (['AR_INVOICE' => ['AR', $ar], 'AR_PAYMENT' => ['AR', $ar]] as $docType => [$role, $account]) {
        $template = PostingTemplate::create(['company_id' => $company->id, 'doc_type' => $docType, 'name' => $docType, 'is_active' => true, 'is_default' => true, 'effective_from' => '2026-01-01', 'version' => 1]);
        PostingTemplateLine::create(['template_id' => $template->id, 'role' => $role, 'account_id' => $account->id]);
        if ($docType === 'AR_INVOICE') {
            PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'REVENUE', 'account_id' => $revenue->id]);
        }
    }

    $customer = Customer::create([
        'company_id' => $company->id, 'customer_number' => 'CUST-0001', 'name' => 'Truck Owner',
        'base_currency' => 'PKR', 'ar_account_id' => $ar->id, 'is_active' => true, 'created_by_user_id' => $user->id,
    ]);

    $vendor = Vendor::create([
        'company_id' => $company->id, 'vendor_number' => 'VEND-0001', 'name' => 'Fuel Depot',
        'base_currency' => 'PKR', 'ap_account_id' => $ap->id, 'is_active' => true, 'created_by_user_id' => $user->id,
    ]);

    return compact('user', 'company', 'cash', 'bank', 'ar', 'ap', 'revenue', 'customer', 'vendor');
}

/** Posts a balanced, immediately-posted manual journal via the same command JournalController uses. */
function postJournal(array $f, string $date, array $entries): void
{
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('journal.create', [
        'transaction_date' => $date,
        'description' => 'Test journal',
        'post' => true,
        'entries' => $entries,
    ], $f['user'], true));
}

test('a bank statement shows the pre-range balance as opening, correct running balances, and a closing balance that matches the ledger', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();

    // Before the range: cash starts with Rs 5,000.
    postJournal($f, '2026-08-15', [
        ['account_id' => $f['cash']->id, 'type' => 'debit', 'amount' => 5000],
        ['account_id' => $f['ap']->id, 'type' => 'credit', 'amount' => 5000],
    ]);

    // Inside the range: Rs 2,000 in, then Rs 800 out.
    postJournal($f, '2026-09-05', [
        ['account_id' => $f['cash']->id, 'type' => 'debit', 'amount' => 2000],
        ['account_id' => $f['ap']->id, 'type' => 'credit', 'amount' => 2000],
    ]);
    postJournal($f, '2026-09-20', [
        ['account_id' => $f['ap']->id, 'type' => 'debit', 'amount' => 800],
        ['account_id' => $f['cash']->id, 'type' => 'credit', 'amount' => 800],
    ]);

    $statement = app(CompanyContextService::class)->withContext($f['company'], fn () => app(AccountStatementService::class)->statement($f['cash']->fresh(), '2026-09-01', '2026-09-24'));

    expect($statement['opening_balance'])->toBe(5000.0);
    expect($statement['closing_balance'])->toBe(6200.0);

    $rows = collect($statement['rows']);
    expect($rows->first()['type'])->toBe('opening_balance')
        ->and($rows->first()['balance'])->toBe(5000.0);
    expect($rows->last()['type'])->toBe('closing_balance')
        ->and($rows->last()['balance'])->toBe(6200.0);

    $movementRows = $rows->whereNotIn('type', ['opening_balance', 'closing_balance'])->values();
    expect($movementRows)->toHaveCount(2);
    expect($movementRows[0]['money_in'])->toBe(2000.0)->and($movementRows[0]['balance'])->toBe(7000.0);
    expect($movementRows[1]['money_out'])->toBe(800.0)->and($movementRows[1]['balance'])->toBe(6200.0);

    // The ledger balance the Balance Sheet would show for this account as of the
    // same cut-off: every posted debit less every posted credit, dated on or
    // before `to`. Must equal the statement's closing balance exactly.
    $ledger = DB::table('acct.journal_entries as je')
        ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
        ->where('t.company_id', $f['company']->id)
        ->where('je.account_id', $f['cash']->id)
        ->whereIn('t.status', ['posted', 'locked'])
        ->whereDate('t.transaction_date', '<=', '2026-09-24')
        ->selectRaw('COALESCE(SUM(je.debit_amount),0) - COALESCE(SUM(je.credit_amount),0) as balance')
        ->value('balance');

    expect(round((float) $ledger, 2))->toBe($statement['closing_balance']);
});

test('a customer statement collapses a pre-range invoice into the opening balance and applies an in-range payment', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();

    $invoice = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('invoice.create', [
        'customer' => $f['customer']->id, 'currency' => 'PKR', 'date' => '2026-08-20',
        'line_items' => [['description' => 'Fuel', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 0]],
    ], $f['user'], true));
    $invoiceModel = Invoice::findOrFail($invoice['data']['id']);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('invoice.send', ['id' => $invoiceModel->id], $f['user'], true));

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('payment.create', [
        'invoice' => $invoiceModel->id, 'amount' => 4000, 'method' => 'cash', 'date' => '2026-09-15',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ], $f['user'], true));

    $statement = app(CustomerStatementService::class)->statement($f['customer']->fresh(), '2026-09-01', '2026-09-24');

    expect($statement['opening_balance'])->toBe(10000.0);
    expect($statement['closing_balance'])->toBe(6000.0);

    $rows = collect($statement['rows']);
    expect($rows->first()['type'])->toBe('opening_balance')->and($rows->first()['balance'])->toBe(10000.0);
    expect($rows->last()['type'])->toBe('closing_balance')->and($rows->last()['balance'])->toBe(6000.0);
    $paymentRow = $rows->firstWhere('type', 'payment');
    expect($paymentRow)->not->toBeNull()->and((float) $paymentRow['balance'])->toBe(6000.0);
});

test('a supplier statement collapses a pre-range bill into the opening balance and applies an in-range payment', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();

    $bill = Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id, 'bill_number' => 'BILL-0001',
        'bill_date' => '2026-08-10', 'due_date' => '2026-09-10', 'status' => 'received',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => 15000, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 15000,
        'paid_amount' => 5000, 'balance' => 10000, 'base_amount' => 15000, 'created_by_user_id' => $f['user']->id,
    ]);

    BillPayment::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id, 'payment_number' => 'BPAY-0001',
        'payment_date' => '2026-09-12', 'amount' => 5000, 'currency' => 'PKR', 'base_currency' => 'PKR',
        'base_amount' => 5000, 'payment_method' => 'bank_transfer', 'payment_account_id' => $f['bank']->id,
        'created_by_user_id' => $f['user']->id,
    ]);

    $statement = app(VendorStatementService::class)->statement($f['vendor']->fresh(), '2026-09-01', '2026-09-24');

    expect($statement['opening_balance'])->toBe(15000.0);
    expect($statement['closing_balance'])->toBe(10000.0);

    $rows = collect($statement['rows']);
    expect($rows->first()['type'])->toBe('opening_balance')->and($rows->first()['balance'])->toBe(15000.0);
    expect($rows->last()['type'])->toBe('closing_balance')->and($rows->last()['balance'])->toBe(10000.0);
});

test('the statements report page renders with a 200, the right component, and the closing balance the engine computed', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();

    postJournal($f, '2026-08-15', [
        ['account_id' => $f['cash']->id, 'type' => 'debit', 'amount' => 5000],
        ['account_id' => $f['ap']->id, 'type' => 'credit', 'amount' => 5000],
    ]);
    postJournal($f, '2026-09-05', [
        ['account_id' => $f['cash']->id, 'type' => 'debit', 'amount' => 2000],
        ['account_id' => $f['ap']->id, 'type' => 'credit', 'amount' => 2000],
    ]);

    $response = test()->actingAs($f['user'])->get("/{$f['company']->slug}/reports/statements?kind=bank&id={$f['cash']->id}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        // The testing view-finder checks each module's page_paths root directly
        // against the component name, which does not know about the "accounting/"
        // prefix-stripping resolvePage() does client-side (see resources/js/app.ts).
        // The actual file lives at modules/Accounting/Resources/js/pages/reports/Statement.vue.
        ->component('accounting/reports/Statement', false)
        ->where('statement.closing_balance', 7000)
        ->where('filters.kind', 'bank')
    );
});

test('a supplier statement with no one picked lists every supplier by date, named, with totals for all', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();
    $second = Vendor::create(['company_id' => $f['company']->id, 'vendor_number' => 'V-9002', 'name' => 'Second Supplier', 'base_currency' => 'PKR', 'ap_account_id' => $f['ap']->id, 'is_active' => true, 'created_by_user_id' => $f['user']->id]);

    $bill = fn ($vendor, $number, $date, $amount) => Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $vendor->id, 'bill_number' => $number,
        'bill_date' => $date, 'due_date' => $date, 'status' => 'received',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => $amount, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => $amount,
        'paid_amount' => 0, 'balance' => $amount, 'base_amount' => $amount, 'created_by_user_id' => $f['user']->id,
    ]);
    $bill($f['vendor'], 'BILL-A1', '2026-08-10', 1000);
    $bill($second, 'BILL-B1', '2026-09-03', 2000);
    $bill($f['vendor'], 'BILL-A2', '2026-09-10', 500);

    test()->actingAs($f['user'])
        ->get("/{$f['company']->slug}/reports/statements?kind=supplier&from=2026-09-01&to=2026-09-24")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounting/reports/Statement', false)
            ->where('filters.id', 'all')
            ->where('statement.combined', true)
            ->where('statement.opening_balance', 1000)
            ->where('statement.closing_balance', 3500)
            ->where('statement.rows.1.party', 'Second Supplier')
            ->where('statement.rows.1.balance', 2000)
            ->where('statement.rows.2.party', $f['vendor']->name)
            ->where('statement.rows.2.balance', 1500)
            ->has('statement.rows', 4)
        );
});

// ---- Corrections (CorrectionService): moving and splitting posted invoices and payments ----

function correctionInvoice(array $f, $customer, float $amount, string $date = '2026-09-10'): Invoice
{
    $invoice = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('invoice.create', [
        'customer' => $customer->id, 'currency' => 'PKR', 'date' => $date,
        'line_items' => [['description' => 'Fuel', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0]],
    ], $f['user'], true));

    return Invoice::findOrFail($invoice['data']['id']);
}

function correctionCustomer(array $f, string $name): Customer
{
    return Customer::create([
        'company_id' => $f['company']->id, 'customer_number' => 'C-'.str()->random(6), 'name' => $name,
        'base_currency' => 'PKR', 'ar_account_id' => $f['ar']->id, 'is_active' => true, 'created_by_user_id' => $f['user']->id,
    ]);
}

function correct(array $f, string $command, array $params): array
{
    test()->actingAs($f['user']);

    return app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch($command, $params, $f['user'], true));
}

function owes(Customer $customer): float
{
    $rows = app(CustomerStatementService::class)->statement($customer->fresh())['rows'];

    return $rows ? round((float) end($rows)['balance'], 2) : 0.0;
}

test('moving an invoice to another customer takes the old customer\'s payment off it and leaves revenue alone', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();
    $other = correctionCustomer($f, 'Real Buyer');
    $invoice = correctionInvoice($f, $f['customer'], 1000);
    correct($f, 'payment.create', [
        'invoice' => $invoice->id, 'amount' => 400, 'method' => 'cash', 'date' => '2026-09-12',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);
    $revenueBefore = ledgerBalanceOf($f['revenue']);
    $arBefore = ledgerBalanceOf($f['ar']);

    $result = correct($f, 'correction.invoice_customer', ['invoice_id' => $invoice->id, 'customer_id' => $other->id, 'reason' => 'Wrong customer']);

    expect($invoice->fresh()->customer_id)->toBe($other->id)
        ->and((float) $invoice->fresh()->balance)->toBe(1000.0)
        ->and(owes($other))->toBe(1000.0)
        ->and(owes($f['customer']))->toBe(-400.0)          // their payment stays theirs, on account
        ->and(ledgerBalanceOf($f['revenue']))->toBe($revenueBefore)
        ->and(ledgerBalanceOf($f['ar']))->toBe($arBefore);
    expect($result['data']['number'])->toBe('COR-00001');
    expect(DB::table('acct.corrections')->where('company_id', $f['company']->id)->count())->toBe(1);
});

test('splitting an invoice gives each customer their share through a credit note and a new invoice', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();
    $b = correctionCustomer($f, 'Second Trolley');
    $invoice = correctionInvoice($f, $f['customer'], 1000);
    $revenueBefore = ledgerBalanceOf($f['revenue']);

    correct($f, 'correction.invoice_split', ['invoice_id' => $invoice->id, 'reason' => 'Two vehicles', 'shares' => [
        ['customer_id' => $f['customer']->id, 'amount' => 700],
        ['customer_id' => $b->id, 'amount' => 300],
    ]]);

    expect(owes($f['customer']))->toBe(700.0)
        ->and(owes($b))->toBe(300.0)
        ->and((float) $invoice->fresh()->total_amount)->toBe(1000.0)   // the original is never rewritten
        ->and((float) $invoice->fresh()->balance)->toBe(700.0)
        ->and(ledgerBalanceOf($f['revenue']))->toBe($revenueBefore);
    $new = Invoice::where('customer_id', $b->id)->firstOrFail();
    expect($new->invoice_date->toDateString())->toBe('2026-09-10');
});

test('moving a payment to the customer who paid it pays their oldest invoices, and a correction cannot be edited', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();
    $payer = correctionCustomer($f, 'Actual Payer');
    $mine = correctionInvoice($f, $f['customer'], 500);
    $theirs1 = correctionInvoice($f, $payer, 300, '2026-09-05');
    $theirs2 = correctionInvoice($f, $payer, 300, '2026-09-08');
    $payment = correct($f, 'payment.create', [
        'invoice' => $mine->id, 'amount' => 500, 'method' => 'cash', 'date' => '2026-09-12',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);

    correct($f, 'correction.payment_customer', ['payment_id' => $payment['data']['id'], 'customer_id' => $payer->id, 'reason' => 'Paid by the other one', 'apply_oldest_first' => true]);

    expect((float) $mine->fresh()->balance)->toBe(500.0)
        ->and((float) $theirs1->fresh()->balance)->toBe(0.0)
        ->and((float) $theirs2->fresh()->balance)->toBe(100.0)
        ->and(owes($payer))->toBe(100.0)
        ->and(owes($f['customer']))->toBe(500.0);

    expect(fn () => DB::table('acct.corrections')->update(['reason' => 'changed']))->toThrow(\Illuminate\Database\QueryException::class);
});

// ---- Corrections (BillCorrectionService): moving and splitting posted bills and bill payments ----

function correctionBill(array $f, $vendor, float $amount, string $date = '2026-09-10'): Bill
{
    return Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $vendor->id, 'bill_number' => 'BILL-COR-'.str()->random(8),
        'bill_date' => $date, 'due_date' => $date, 'status' => 'received',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => $amount, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => $amount,
        'paid_amount' => 0, 'balance' => $amount, 'base_amount' => $amount, 'created_by_user_id' => $f['user']->id,
    ]);
}

function correctionVendor(array $f, string $name): Vendor
{
    return Vendor::create([
        'company_id' => $f['company']->id, 'vendor_number' => 'V-'.str()->random(6), 'name' => $name,
        'base_currency' => 'PKR', 'ap_account_id' => $f['ap']->id, 'is_active' => true, 'created_by_user_id' => $f['user']->id,
    ]);
}

/** What is still owed to a supplier -- the payables mirror of owes(). */
function owedTo(Vendor $vendor): float
{
    $rows = app(VendorStatementService::class)->statement($vendor->fresh())['rows'];

    return $rows ? round((float) end($rows)['balance'], 2) : 0.0;
}

test('moving a bill to another supplier takes the old supplier\'s payment off it and leaves cost alone', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();
    $other = correctionVendor($f, 'Real Supplier');
    $bill = correctionBill($f, $f['vendor'], 1000);
    $expense = Account::create(['company_id' => $f['company']->id, 'code' => '6100', 'name' => 'Purchases', 'type' => 'expense', 'subtype' => 'other_expense', 'normal_balance' => 'debit', 'is_active' => true]);
    BillLineItem::create([
        'company_id' => $f['company']->id, 'bill_id' => $bill->id, 'line_number' => 1, 'description' => 'Fuel',
        'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0, 'discount_rate' => 0, 'line_total' => 1000,
        'tax_amount' => 0, 'total' => 1000, 'expense_account_id' => $expense->id, 'created_by_user_id' => $f['user']->id,
    ]);
    $payment = BillPayment::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id, 'payment_number' => 'BPAY-COR-1',
        'payment_date' => '2026-09-12', 'amount' => 400, 'currency' => 'PKR', 'base_currency' => 'PKR', 'base_amount' => 400,
        'payment_method' => 'cash', 'payment_account_id' => $f['cash']->id, 'created_by_user_id' => $f['user']->id,
    ]);
    BillPaymentAllocation::create([
        'company_id' => $f['company']->id, 'bill_payment_id' => $payment->id, 'bill_id' => $bill->id,
        'amount_allocated' => 400, 'base_amount_allocated' => 400, 'applied_at' => now(),
    ]);
    $bill->update(['paid_amount' => 400, 'balance' => 600, 'status' => 'partial']);
    $expenseBefore = ledgerBalanceOf($expense);

    $result = correct($f, 'correction.bill_supplier', ['bill_id' => $bill->id, 'vendor_id' => $other->id, 'reason' => 'Wrong supplier']);

    expect($bill->fresh()->vendor_id)->toBe($other->id)
        ->and((float) $bill->fresh()->balance)->toBe(1000.0)
        ->and(owedTo($other))->toBe(1000.0)
        ->and(owedTo($f['vendor']))->toBe(-400.0)          // their payment stays theirs, as an advance
        ->and(ledgerBalanceOf($expense))->toBe($expenseBefore);
    expect($result['data']['number'])->toBe('COR-00001');
    expect(DB::table('acct.corrections')->where('company_id', $f['company']->id)->where('entity_type', 'bill')->count())->toBe(1);
});

test('splitting a bill gives each supplier their share through a vendor credit and a new bill', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();
    $b = correctionVendor($f, 'Second Depot');
    $bill = correctionBill($f, $f['vendor'], 1000);
    $expense = Account::create(['company_id' => $f['company']->id, 'code' => '6100', 'name' => 'Purchases', 'type' => 'expense', 'subtype' => 'other_expense', 'normal_balance' => 'debit', 'is_active' => true]);
    BillLineItem::create([
        'company_id' => $f['company']->id, 'bill_id' => $bill->id, 'line_number' => 1, 'description' => 'Fuel',
        'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0, 'discount_rate' => 0, 'line_total' => 1000,
        'tax_amount' => 0, 'total' => 1000, 'expense_account_id' => $expense->id, 'created_by_user_id' => $f['user']->id,
    ]);
    $expenseBefore = ledgerBalanceOf($expense);

    correct($f, 'correction.bill_split', ['bill_id' => $bill->id, 'reason' => 'Two loads', 'shares' => [
        ['vendor_id' => $f['vendor']->id, 'amount' => 700],
        ['vendor_id' => $b->id, 'amount' => 300],
    ]]);

    expect(owedTo($f['vendor']))->toBe(700.0)
        ->and(owedTo($b))->toBe(300.0)
        ->and((float) $bill->fresh()->total_amount)->toBe(1000.0)   // the original is never rewritten
        ->and((float) $bill->fresh()->balance)->toBe(700.0)
        ->and(ledgerBalanceOf($expense))->toBe($expenseBefore);
    $new = Bill::where('vendor_id', $b->id)->firstOrFail();
    expect($new->bill_date->toDateString())->toBe('2026-09-10');
});

test('moving a bill payment to the supplier who was paid it pays their oldest bills, and a correction cannot be edited', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();
    $payee = correctionVendor($f, 'Actual Supplier');
    $mine = correctionBill($f, $f['vendor'], 500);
    $theirs1 = correctionBill($f, $payee, 300, '2026-09-05');
    $theirs2 = correctionBill($f, $payee, 300, '2026-09-08');

    $payment = BillPayment::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id, 'payment_number' => 'BPAY-COR-2',
        'payment_date' => '2026-09-12', 'amount' => 500, 'currency' => 'PKR', 'base_currency' => 'PKR', 'base_amount' => 500,
        'payment_method' => 'cash', 'payment_account_id' => $f['cash']->id, 'created_by_user_id' => $f['user']->id,
    ]);
    BillPaymentAllocation::create([
        'company_id' => $f['company']->id, 'bill_payment_id' => $payment->id, 'bill_id' => $mine->id,
        'amount_allocated' => 500, 'base_amount_allocated' => 500, 'applied_at' => now(),
    ]);
    $mine->update(['paid_amount' => 500, 'balance' => 0, 'status' => 'paid']);

    correct($f, 'correction.bill_payment_supplier', ['bill_payment_id' => $payment->id, 'vendor_id' => $payee->id, 'reason' => 'Paid the other supplier', 'apply_oldest_first' => true]);

    expect((float) $mine->fresh()->balance)->toBe(500.0)
        ->and((float) $theirs1->fresh()->balance)->toBe(0.0)
        ->and((float) $theirs2->fresh()->balance)->toBe(100.0)
        ->and(owedTo($payee))->toBe(100.0)
        ->and(owedTo($f['vendor']))->toBe(500.0);

    expect(fn () => DB::table('acct.corrections')->update(['reason' => 'changed']))->toThrow(\Illuminate\Database\QueryException::class);
});

function ledgerBalanceOf(Account $account): float
{
    $row = DB::table('acct.journal_entries')->where('account_id', $account->id)
        ->selectRaw('COALESCE(SUM(debit_amount),0) d, COALESCE(SUM(credit_amount),0) c')->first();

    return round((float) $row->d - (float) $row->c, 2);
}

test('a paid invoice splits only once its payments are taken off, and the money stays with the payer on account', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();
    $b = correctionCustomer($f, 'Other Trolley');
    $invoice = correctionInvoice($f, $f['customer'], 1000);
    correct($f, 'payment.create', [
        'invoice' => $invoice->id, 'amount' => 1000, 'method' => 'cash', 'date' => '2026-09-12',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);
    $shares = [['customer_id' => $f['customer']->id, 'amount' => 600], ['customer_id' => $b->id, 'amount' => 400]];

    expect(fn () => correct($f, 'correction.invoice_split', ['invoice_id' => $invoice->id, 'reason' => 'Two', 'shares' => $shares]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    correct($f, 'correction.invoice_split', ['invoice_id' => $invoice->id, 'reason' => 'Two', 'shares' => $shares, 'unapply_payments' => true]);

    expect((float) $invoice->fresh()->balance)->toBe(600.0)
        ->and(owes($f['customer']))->toBe(-400.0)   // 600 owed, 1000 paid: 400 credit left to apply
        ->and(owes($b))->toBe(400.0)
        ->and((float) DB::table('acct.payment_allocations')->whereNull('invoice_id')->sum('amount_allocated'))->toBe(1000.0);
});

test('moving an invoice from its page saves even when the dialog also sends its empty split rows', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();
    $other = correctionCustomer($f, 'Page Buyer');
    $invoice = correctionInvoice($f, $f['customer'], 1000);

    test()->actingAs($f['user'])->post("/{$f['company']->slug}/invoices/{$invoice->id}/correct", [
        'action' => 'change_customer',
        'customer_id' => $other->id,
        'shares' => [['customer_id' => '', 'amount' => 1000], ['customer_id' => '', 'amount' => null]],
        'reason' => 'Wrong customer',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    expect($invoice->fresh()->customer_id)->toBe($other->id);
});

test('splitting a payment gives each customer their share on account, and the cash received is unchanged', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();
    $b = correctionCustomer($f, 'Co-payer');
    $invoice = correctionInvoice($f, $f['customer'], 1300);
    $payment = correct($f, 'payment.create', [
        'invoice' => $invoice->id, 'amount' => 1300, 'method' => 'cash', 'date' => '2026-09-21',
        'deposit_account_id' => $f['cash']->id, 'ar_account_id' => $f['ar']->id,
    ]);
    $cashBefore = ledgerBalanceOf($f['cash']);

    correct($f, 'correction.payment_split', ['payment_id' => $payment['data']['id'], 'reason' => 'Two paid together', 'shares' => [
        ['customer_id' => $f['customer']->id, 'amount' => 1000],
        ['customer_id' => $b->id, 'amount' => 300],
    ]]);

    $new = \App\Modules\Accounting\Models\Payment::where('customer_id', $b->id)->firstOrFail();
    expect((float) \App\Modules\Accounting\Models\Payment::find($payment['data']['id'])->amount)->toBe(1000.0)
        ->and((float) $new->amount)->toBe(300.0)
        ->and($new->payment_date->toDateString())->toBe('2026-09-21')
        ->and((float) $invoice->fresh()->balance)->toBe(1300.0)          // taken off; applied again by hand
        ->and(owes($f['customer']))->toBe(300.0)
        ->and(owes($b))->toBe(-300.0)
        ->and(ledgerBalanceOf($f['cash']))->toBe($cashBefore);
});

test('splitting a supplier payment leaves each share as that supplier\'s advance', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();
    $other = correctionVendor($f, 'Second Depot');
    $payment = BillPayment::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id, 'payment_number' => 'PMT-00001',
        'payment_date' => '2026-09-12', 'amount' => 900, 'currency' => 'PKR', 'base_currency' => 'PKR', 'base_amount' => 900,
        'payment_method' => 'cash', 'payment_account_id' => $f['cash']->id, 'created_by_user_id' => $f['user']->id,
    ]);

    correct($f, 'correction.bill_payment_split', ['bill_payment_id' => $payment->id, 'reason' => 'Paid two depots', 'shares' => [
        ['vendor_id' => $f['vendor']->id, 'amount' => 600],
        ['vendor_id' => $other->id, 'amount' => 300],
    ]]);

    expect((float) $payment->fresh()->amount)->toBe(600.0)
        ->and(owedTo($f['vendor']))->toBe(-600.0)
        ->and(owedTo($other))->toBe(-300.0)
        ->and(BillPayment::where('vendor_id', $other->id)->value('payment_number'))->toBe('PMT-00002');
});

test('a customer can join a group one level deep, and a statement of chosen people shows only them', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));
    $f = statementReportFixture();
    $group = correctionCustomer($f, 'Trolley');
    $a = correctionCustomer($f, 'GAL-1804');
    $b = correctionCustomer($f, 'TLF-866');
    $outsider = correctionCustomer($f, 'Someone Else');
    correctionInvoice($f, $a, 300, '2026-09-05');
    correctionInvoice($f, $b, 200, '2026-09-06');
    correctionInvoice($f, $outsider, 999, '2026-09-07');

    foreach ([$a, $b] as $member) {
        correct($f, 'customer.update', ['id' => $member->id, 'parent_customer_id' => $group->id]);
    }
    expect($a->fresh()->parent_customer_id)->toBe($group->id);
    // One level: a member cannot be a group, and a group cannot join another.
    expect(fn () => correct($f, 'customer.update', ['id' => $outsider->id, 'parent_customer_id' => $a->id]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => correct($f, 'customer.update', ['id' => $group->id, 'parent_customer_id' => $outsider->id]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    test()->actingAs($f['user'])
        ->get("/{$f['company']->slug}/reports/statements?kind=customer&ids={$a->id},{$b->id}&from=2026-09-01&to=2026-09-24")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.id', 'some')
            ->where('statement.combined', true)
            ->where('statement.closing_balance', 500)
            ->has('statement.rows', 4)
            ->where('options.groups.0.id', $group->id)
            ->has('options.groups.0.member_ids', 3)
        );
});
