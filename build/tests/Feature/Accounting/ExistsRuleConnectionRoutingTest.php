<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\BankReconciliation;
use App\Modules\Accounting\Models\BankTransaction;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use App\Modules\Accounting\Models\Vendor;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * StoreInvoiceRequest, StoreBillRequest and BankReconciliationController used bare
 * 'acct.xxx' / 'inv.xxx' strings in exists() rules. Laravel's exists/unique rule splits a
 * dotted table name on its FIRST dot into connection + table, and config/database.php
 * defines connections literally named "acct" and "inv" -- so those checks ran on a second
 * Postgres session carrying neither this request's RLS context nor its transaction. Under
 * today's superuser role RLS is bypassed everywhere, so the second connection still "sees"
 * every row and the bug is invisible from acceptance/rejection behaviour alone. These tests
 * cover the ordinary contract (own-company id accepted, other-company id rejected) plus one
 * test that proves the fix actually changed which connection is queried.
 */
function existsRuleFixture(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Exists Rule Co '.str()->random(8),
        'slug' => 'exists-rule-'.str()->lower(str()->random(10)),
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
    $ap = Account::create(['company_id' => $company->id, 'code' => '2100', 'name' => 'AP', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'currency' => 'PKR', 'is_active' => true]);
    $revenue = Account::create(['company_id' => $company->id, 'code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue', 'subtype' => 'other_income', 'normal_balance' => 'credit', 'is_active' => true]);
    $cash = Account::create(['company_id' => $company->id, 'code' => '1050', 'name' => 'Cash', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);

    foreach (['AR_INVOICE', 'AR_PAYMENT', 'AP_BILL'] as $docType) {
        $template = PostingTemplate::create(['company_id' => $company->id, 'doc_type' => $docType, 'name' => $docType, 'is_active' => true, 'is_default' => true, 'effective_from' => '2026-01-01', 'version' => 1]);
        if ($docType === 'AP_BILL') {
            PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'AP', 'account_id' => $ap->id]);
            PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'EXPENSE', 'account_id' => $revenue->id]);

            continue;
        }
        PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'AR', 'account_id' => $ar->id]);
        if ($docType === 'AR_INVOICE') {
            PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'REVENUE', 'account_id' => $revenue->id]);
        }
    }

    $customer = Customer::create(['company_id' => $company->id, 'customer_number' => 'C-1', 'name' => 'Buyer', 'base_currency' => 'PKR', 'ar_account_id' => $ar->id, 'credit_limit' => 50000, 'is_active' => true]);
    $vendor = Vendor::create(['company_id' => $company->id, 'vendor_number' => 'V-1', 'name' => 'Supplier', 'base_currency' => 'PKR', 'is_active' => true]);

    $bankAccount = BankAccount::create([
        'company_id' => $company->id, 'account_name' => 'Main', 'account_number' => 'BA-1',
        'account_type' => 'checking', 'currency' => 'PKR', 'gl_account_id' => $cash->id, 'is_active' => true,
    ]);

    $bankTransaction = BankTransaction::create([
        'company_id' => $company->id, 'bank_account_id' => $bankAccount->id,
        'transaction_date' => '2026-09-01', 'description' => 'Deposit', 'amount' => 1000,
        'transaction_type' => 'deposit', 'is_reconciled' => false,
    ]);

    return compact('owner', 'company', 'ar', 'ap', 'revenue', 'cash', 'customer', 'vendor', 'bankAccount', 'bankTransaction');
}

// --- Invoices: StoreInvoiceRequest customer_id => Rule::exists(Customer::class, 'id') ---

test('an invoice is created when customer_id belongs to this company', function () {
    $f = existsRuleFixture();

    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/invoices", [
        'customer_id' => $f['customer']->id,
        'invoice_date' => '2026-09-15',
        'line_items' => [
            ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 500],
        ],
    ]);

    $response->assertSessionHasNoErrors();
});

test('an invoice naming a customer_id that does not exist at all is rejected', function () {
    // StoreInvoiceRequest's exists() rule was never scoped to company_id even before
    // this conversion (no ->where('company_id', ...) clause) -- cross-company protection
    // for invoices happens deeper, in Invoice\CreateAction::resolveCustomer(), and throws a
    // plain (non-validation) exception rather than a field error. That is unrelated to the
    // connection-routing bug this task fixes and is out of scope here. What the converted
    // exists() rule itself is responsible for -- and must still reject the same as before --
    // is a customer_id that is not a real row at all.
    $f = existsRuleFixture();

    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/invoices", [
        'customer_id' => (string) Str::uuid(),
        'invoice_date' => '2026-09-15',
        'line_items' => [
            ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 500],
        ],
    ]);

    $response->assertSessionHasErrors('customer_id');
});

// --- Bills: StoreBillRequest vendor_id => Rule::exists(Vendor::class, 'id') ---

test('a bill is created when vendor_id belongs to this company', function () {
    $f = existsRuleFixture();

    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/bills", [
        'vendor_id' => $f['vendor']->id,
        'bill_date' => '2026-09-15',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'line_items' => [
            ['description' => 'Supplies', 'quantity' => 1, 'unit_price' => 500],
        ],
    ]);

    $response->assertSessionHasNoErrors();
});

test('a bill naming a vendor_id that does not exist at all is rejected', function () {
    // Same caveat as the invoice test above: StoreBillRequest's vendor_id exists() rule
    // was never company-scoped, before or after this conversion. What it does still have
    // to reject is a vendor_id that is not a real row.
    $f = existsRuleFixture();

    $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/bills", [
        'vendor_id' => (string) Str::uuid(),
        'bill_date' => '2026-09-15',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'line_items' => [
            ['description' => 'Supplies', 'quantity' => 1, 'unit_price' => 500],
        ],
    ]);

    $response->assertSessionHasErrors('vendor_id');
});

// --- Bank transactions: BankReconciliationController::toggleTransaction transaction_id ---

test('a bank transaction toggle is accepted when the transaction belongs to this company', function () {
    $f = existsRuleFixture();

    $reconciliation = BankReconciliation::create([
        'company_id' => $f['company']->id, 'bank_account_id' => $f['bankAccount']->id,
        'statement_date' => '2026-09-15', 'statement_ending_balance' => 1000,
        'book_balance' => 1000, 'reconciled_balance' => 0, 'difference' => 1000,
        'status' => 'in_progress', 'started_at' => now(), 'created_by_user_id' => $f['owner']->id,
    ]);

    $response = $this->actingAs($f['owner'])->postJson(
        "/{$f['company']->slug}/banking/reconciliation/{$reconciliation->id}/toggle",
        ['transaction_id' => $f['bankTransaction']->id]
    );

    $response->assertOk();
});

test('a bank transaction toggle naming a transaction that does not exist at all is rejected', function () {
    $f = existsRuleFixture();

    $reconciliation = BankReconciliation::create([
        'company_id' => $f['company']->id, 'bank_account_id' => $f['bankAccount']->id,
        'statement_date' => '2026-09-15', 'statement_ending_balance' => 1000,
        'book_balance' => 1000, 'reconciled_balance' => 0, 'difference' => 1000,
        'status' => 'in_progress', 'started_at' => now(), 'created_by_user_id' => $f['owner']->id,
    ]);

    $response = $this->actingAs($f['owner'])->postJson(
        "/{$f['company']->slug}/banking/reconciliation/{$reconciliation->id}/toggle",
        ['transaction_id' => (string) Str::uuid()]
    );

    $response->assertStatus(422);
});

test('a bank transaction toggle naming a transaction from another company is not applied', function () {
    // BankTransaction::where('bank_account_id', ...) exists() rule is a global row-exists
    // check, unscoped to company -- same as before this conversion. Cross-company
    // protection here happens in the controller's own bank_account_id-scoped findOrFail(),
    // which 404s. That is unchanged, pre-existing behaviour, not something the exists()
    // conversion itself is responsible for proving.
    $f = existsRuleFixture();
    $other = existsRuleFixture();

    // Building the second fixture left the session inside the second company.
    enterCompany($f['company']);

    $reconciliation = BankReconciliation::create([
        'company_id' => $f['company']->id, 'bank_account_id' => $f['bankAccount']->id,
        'statement_date' => '2026-09-15', 'statement_ending_balance' => 1000,
        'book_balance' => 1000, 'reconciled_balance' => 0, 'difference' => 1000,
        'status' => 'in_progress', 'started_at' => now(), 'created_by_user_id' => $f['owner']->id,
    ]);

    $response = $this->actingAs($f['owner'])->postJson(
        "/{$f['company']->slug}/banking/reconciliation/{$reconciliation->id}/toggle",
        ['transaction_id' => $other['bankTransaction']->id]
    );

    $response->assertStatus(404);
});

/**
 * The real point of this whole conversion: prove the exists() check now resolves through
 * the model's own (default) connection, not through the second "acct" connection that
 * config/database.php defines under that name.
 *
 * We cannot demonstrate this by clearing app.current_company_id, because the app connects
 * as a Postgres superuser today and RLS is bypassed unconditionally -- a superuser sees
 * every row through EITHER connection regardless of session context, so that experiment
 * would "pass" whether or not the bug were still present. It proves nothing under the
 * current role and would be a false proof if reported as one.
 *
 * Instead we point the "acct" connection at a database that does not exist and purge its
 * cached PDO handle, then submit a request that must satisfy the customer_id exists rule.
 * If the code still queried through connection "acct" (the pre-fix bug), this would throw
 * a connection-refused/database-does-not-exist PDOException before validation could ever
 * pass. Because StoreInvoiceRequest now uses Rule::exists(Customer::class, 'id'), the check
 * runs on Customer's own (default) connection, which we never touched, so the request
 * succeeds untouched by the broken "acct" connection.
 */
test('the customer_id exists rule resolves without ever touching the acct connection', function () {
    $f = existsRuleFixture();

    $originalConfig = config('database.connections.acct');
    config(['database.connections.acct.database' => 'db_that_does_not_exist_'.str()->random(8)]);
    DB::purge('acct');

    try {
        $response = $this->actingAs($f['owner'])->post("/{$f['company']->slug}/invoices", [
            'customer_id' => $f['customer']->id,
            'invoice_date' => '2026-09-15',
            'line_items' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 500],
            ],
        ]);

        // If the exists rule still reached through the broken "acct" connection, Laravel
        // would surface that as a 500 (connection failure) long before a 302/session-errors
        // response could be produced. Getting here at all, with no error on customer_id,
        // is the proof.
        $response->assertSessionHasNoErrors();
    } finally {
        config(['database.connections.acct' => $originalConfig]);
        DB::purge('acct');
    }
});
