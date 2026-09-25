<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
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
