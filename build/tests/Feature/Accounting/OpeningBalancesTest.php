<?php

use App\Models\Company;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Models\SalaryAdvanceRecovery;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function openingBalanceFixture(): array
{
    $user = User::factory()->create();

    $company = Company::create([
        'name' => 'Opening Balance Test',
        'slug' => 'opening-balance-test-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ]);

    if (! DB::table('public.currencies')->where('code', 'PKR')->exists()) {
        DB::table('public.currencies')->insert(['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs']);
    }

    $fy = FiscalYear::create([
        'company_id' => $company->id,
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'open',
    ]);
    foreach ([8 => ['2026-08-01', '2026-08-31'], 9 => ['2026-09-01', '2026-09-30']] as $n => [$start, $end]) {
        AccountingPeriod::create([
            'company_id' => $company->id,
            'fiscal_year_id' => $fy->id,
            'name' => "P{$n} 2026",
            'period_number' => $n,
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    $mk = fn (string $code, string $name, string $type, string $subtype, string $normal) => Account::create([
        'company_id' => $company->id,
        'code' => $code,
        'name' => $name,
        'type' => $type,
        'subtype' => $subtype,
        'normal_balance' => $normal,
        'currency' => 'PKR',
        'is_active' => true,
    ]);

    $accounts = [
        'cash' => $mk('1050', 'Cash on Hand', 'asset', 'cash', 'debit'),
        'bank' => $mk('1000', 'HBL Current', 'asset', 'bank', 'debit'),
        'bank2' => $mk('1010', 'UBL Card Settlement', 'asset', 'bank', 'debit'),
        'ar' => $mk('1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit'),
        'advances' => $mk('1150', 'Employee Advances', 'asset', 'other_current_asset', 'debit'),
        'ap' => $mk('2100', 'Accounts Payable', 'liability', 'accounts_payable', 'credit'),
        'amanat' => $mk('2200', 'Customer Amanat Deposits', 'liability', 'other_current_liability', 'credit'),
        'partner' => $mk('2210', 'Investor Deposits', 'liability', 'other_current_liability', 'credit'),
    ];

    return compact('company', 'user', 'accounts');
}

function openingBalanceHttpFixture(): array
{
    $f = openingBalanceFixture();
    $company = $f['company'];
    $user = $f['user'];

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");

    app(CompanyRbacBootstrapper::class)->bootstrap($company);

    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($user, 'owner'),
    );

    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    return $f;
}

function dispatchOpeningBalance(array $fixture, array $params): array
{
    test()->actingAs($fixture['user']);

    return app(CompanyContextService::class)->withContext($fixture['company'], function () use ($fixture, $params) {
        return app(CommandBus::class)->dispatch('opening_balance.save', $params, $fixture['user'], true);
    });
}

function ledgerBalance(Account $account): float
{
    $rows = DB::table('acct.journal_entries')->where('account_id', $account->id)
        ->selectRaw('COALESCE(SUM(debit_amount),0) as d, COALESCE(SUM(credit_amount),0) as c')->first();
    return round((float) $rows->d - (float) $rows->c, 2);
}

test('saving cash and bank opening balances posts one balanced journal against opening balance equity', function () {
    $f = openingBalanceFixture();

    $result = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'cash' => ['amount' => 150000],
        'banks' => [
            ['account_id' => $f['accounts']['bank']->id, 'amount' => 900000],
            ['account_id' => $f['accounts']['bank2']->id, 'amount' => 50000],
        ],
    ]);

    $journal = Transaction::find($result['data']['journal_id']);
    expect($journal)->not->toBeNull()
        ->and($journal->transaction_type)->toBe('opening_balance')
        ->and($journal->reference_type)->toBe('acct.opening_balances')
        ->and($journal->transaction_date->toDateString())->toBe('2026-08-31');

    $entries = $journal->journalEntries;
    expect((float) $entries->sum('debit_amount'))->toBe((float) $entries->sum('credit_amount'));

    $equity = Account::where('company_id', $f['company']->id)->where('code', '3080')->first();
    expect($equity)->not->toBeNull()->and($equity->type)->toBe('equity');

    expect(ledgerBalance($f['accounts']['cash']))->toBe(150000.0)
        ->and(ledgerBalance($f['accounts']['bank']))->toBe(900000.0)
        ->and(ledgerBalance($f['accounts']['bank2']))->toBe(50000.0)
        ->and(ledgerBalance($equity))->toBe(-1100000.0);

    $settings = $f['company']->fresh()->settings;
    expect($settings['opening_balances']['as_of_date'])->toBe('2026-08-31')
        ->and($settings['opening_balances']['locked_at'])->toBeNull();
});

function openingCustomer(array $f, string $name): Customer
{
    return Customer::create([
        'company_id' => $f['company']->id,
        'customer_number' => 'CUST-'.str()->upper(str()->random(5)),
        'name' => $name,
        'customer_type' => 'business',
        'base_currency' => 'PKR',
        'ar_account_id' => $f['accounts']['ar']->id,
    ]);
}

test('amanat, employee advance and partner capital openings create sub-records linked to the journal', function () {
    $f = openingBalanceFixture();
    $depositor = openingCustomer($f, 'Haji Saab');
    $employee = Employee::create([
        'company_id' => $f['company']->id,
        'employee_number' => 'EMP-001',
        'first_name' => 'Ali',
        'last_name' => 'Khan',
        'hire_date' => '2025-01-01',
        'currency' => 'PKR',
    ]);
    $partner = Partner::create([
        'company_id' => $f['company']->id,
        'name' => 'Owner One',
        'profit_share_percentage' => 100,
    ]);

    $result = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'amanat' => [['customer_id' => $depositor->id, 'amount' => 30000]],
        'employees' => [['employee_id' => $employee->id, 'amount' => 5000]],
        'partners' => [['partner_id' => $partner->id, 'amount' => 1000000]],
    ]);

    $journalId = $result['data']['journal_id'];
    $entries = Transaction::find($journalId)->journalEntries;

    $amanatEntry = $entries->first(fn ($e) => $e->account_id === $f['accounts']['amanat']->id && (float) $e->credit_amount === 30000.0);
    $advanceEntry = $entries->first(fn ($e) => $e->account_id === $f['accounts']['advances']->id && (float) $e->debit_amount === 5000.0);
    $partnerEntry = $entries->first(fn ($e) => $e->account_id === $f['accounts']['partner']->id && (float) $e->credit_amount === 1000000.0);
    expect($amanatEntry)->not->toBeNull()
        ->and($advanceEntry)->not->toBeNull()
        ->and($partnerEntry)->not->toBeNull();

    $amanat = AmanatTransaction::where('customer_id', $depositor->id)->first();
    expect($amanat)->not->toBeNull()
        ->and($amanat->transaction_type)->toBe(AmanatTransaction::TYPE_DEPOSIT)
        ->and((float) $amanat->amount)->toBe(30000.0)
        ->and($amanat->reference)->toBe('OPENING')
        ->and($amanat->journal_entry_id)->toBe($amanatEntry->id);
    expect((float) CustomerProfile::where('customer_id', $depositor->id)->first()->amanat_balance)->toBe(30000.0);

    $advance = SalaryAdvance::where('employee_id', $employee->id)->first();
    expect($advance)->not->toBeNull()
        ->and((float) $advance->amount_outstanding)->toBe(5000.0)
        ->and($advance->status)->toBe('pending')
        ->and($advance->reference)->toBe('OPENING')
        ->and($advance->journal_entry_id)->toBe($advanceEntry->id);

    $capital = PartnerTransaction::where('partner_id', $partner->id)->first();
    expect($capital)->not->toBeNull()
        ->and($capital->transaction_type)->toBe('investment')
        ->and((float) $capital->amount)->toBe(1000000.0)
        ->and($capital->journal_entry_id)->toBe($partnerEntry->id);

    expect(ledgerBalance($f['accounts']['amanat']))->toBe(-30000.0)
        ->and(ledgerBalance($f['accounts']['advances']))->toBe(5000.0)
        ->and(ledgerBalance($f['accounts']['partner']))->toBe(-1000000.0);

    $equity = Account::where('company_id', $f['company']->id)->where('code', '3080')->first();
    // assets 5000 − liabilities 1,030,000 = −1,025,000 → 3080 carries a debit of 1,025,000
    expect(ledgerBalance($equity))->toBe(1025000.0);
});

test('a locked opening-balance employee advance can still be fully recovered through payroll but not cancelled or amended', function () {
    $f = openingBalanceFixture();
    $employee = Employee::create([
        'company_id' => $f['company']->id,
        'employee_number' => 'EMP-001',
        'first_name' => 'Ali',
        'last_name' => 'Khan',
        'hire_date' => '2025-01-01',
        'currency' => 'PKR',
    ]);

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'employees' => [['employee_id' => $employee->id, 'amount' => 5000]],
    ]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.lock', [], $f['user'], true));

    $advance = SalaryAdvance::where('employee_id', $employee->id)->firstOrFail();
    expect($advance->status)->toBe('pending');

    // Final recovery (via the payroll trigger path) must not be blocked by the
    // locked-opening-balance guard: it only mutates amount_recovered/amount_outstanding/status.
    SalaryAdvanceRecovery::create([
        'company_id' => $f['company']->id,
        'salary_advance_id' => $advance->id,
        'recovery_date' => '2026-09-05',
        'amount' => 5000,
        'recovery_type' => 'manual_repayment',
    ]);

    $advance->refresh();
    expect($advance->status)->toBe('fully_recovered')
        ->and((float) $advance->amount_recovered)->toBe(5000.0)
        ->and((float) $advance->amount_outstanding)->toBe(0.0);

    // Cancelling a locked opening-balance advance must still be rejected.
    expect(fn () => DB::table('pay.salary_advances')->where('id', $advance->id)->update(['status' => 'cancelled']))
        ->toThrow(\Illuminate\Database\QueryException::class);

    // Changing the advance amount on a locked opening-balance advance must still be rejected.
    expect(fn () => DB::table('pay.salary_advances')->where('id', $advance->id)->update(['amount' => 9999]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('an employee or partner belonging to another company is refused and nothing is written', function () {
    $f = openingBalanceFixture();

    $otherCompany = Company::create([
        'name' => 'Other Company',
        'slug' => 'other-company-'.str()->lower(str()->random(8)),
        'base_currency' => 'PKR',
    ]);
    $otherEmployee = Employee::create([
        'company_id' => $otherCompany->id,
        'employee_number' => 'EMP-OTHER-1',
        'first_name' => 'Not',
        'last_name' => 'Ours',
        'hire_date' => '2025-01-01',
        'currency' => 'PKR',
    ]);
    $otherPartner = Partner::create([
        'company_id' => $otherCompany->id,
        'name' => 'Not Our Partner',
        'profit_share_percentage' => 100,
    ]);

    expect(fn () => dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'employees' => [['employee_id' => $otherEmployee->id, 'amount' => 5000]],
    ]))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(fn () => dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'partners' => [['partner_id' => $otherPartner->id, 'amount' => 5000]],
    ]))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(SalaryAdvance::where('employee_id', $otherEmployee->id)->count())->toBe(0);
    expect(PartnerTransaction::where('partner_id', $otherPartner->id)->count())->toBe(0);
    expect($f['company']->fresh()->settings['opening_balances'] ?? null)->toBeNull();
});

test('credit customer and supplier openings become posted invoices and bills against opening balance equity', function () {
    $f = openingBalanceFixture();
    $customer = openingCustomer($f, 'Truck Company');
    $vendor = Vendor::create([
        'company_id' => $f['company']->id,
        'vendor_number' => 'VEND-0001',
        'name' => 'PSO Depot',
        'base_currency' => 'PKR',
        'is_active' => true,
        'ap_account_id' => $f['accounts']['ap']->id,
        'created_by_user_id' => $f['user']->id,
    ]);

    $result = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'credit_customers' => [['customer_id' => $customer->id, 'amount' => 42000]],
        'suppliers' => [['vendor_id' => $vendor->id, 'amount' => 250000]],
    ]);

    expect($result['data']['journal_id'])->toBeNull();

    $invoice = Invoice::find($result['data']['invoice_ids'][0]);
    expect($invoice->customer_id)->toBe($customer->id)
        ->and((float) $invoice->total_amount)->toBe(42000.0)
        ->and((float) $invoice->balance)->toBe(42000.0)
        ->and($invoice->internal_notes)->toBe('OPENING')
        ->and($invoice->transaction_id)->not->toBeNull()
        ->and($invoice->invoice_date->toDateString())->toBe('2026-08-31');
    $equity = Account::where('company_id', $f['company']->id)->where('code', '3080')->first();
    expect($invoice->lineItems->first()->income_account_id)->toBe($equity->id);

    $bill = Bill::find($result['data']['bill_ids'][0]);
    expect($bill->vendor_id)->toBe($vendor->id)
        ->and((float) $bill->balance)->toBe(250000.0)
        ->and($bill->internal_notes)->toBe('OPENING')
        ->and($bill->transaction_id)->not->toBeNull()
        ->and($bill->lineItems->first()->expense_account_id)->toBe($equity->id);

    expect(ledgerBalance($f['accounts']['ar']))->toBe(42000.0)
        ->and(ledgerBalance($f['accounts']['ap']))->toBe(-250000.0)
        ->and(ledgerBalance($equity))->toBe(208000.0);
});

test('saving three credit customers in one go numbers every opening invoice uniquely', function () {
    $f = openingBalanceFixture();
    $one = openingCustomer($f, 'Customer One');
    $two = openingCustomer($f, 'Customer Two');
    $three = openingCustomer($f, 'Customer Three');

    $result = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'credit_customers' => [
            ['customer_id' => $one->id, 'amount' => 10000],
            ['customer_id' => $two->id, 'amount' => 20000],
            ['customer_id' => $three->id, 'amount' => 30000],
        ],
    ]);

    $invoices = Invoice::whereIn('id', $result['data']['invoice_ids'])->where('status', '!=', 'void')->get();
    expect($invoices)->toHaveCount(3);

    $numbers = $invoices->pluck('invoice_number')->unique();
    expect($numbers)->toHaveCount(3);

    expect(ledgerBalance($f['accounts']['ar']))->toBe(60000.0);
});

test('re-saving replaces the previous opening records without duplicating balances', function () {
    $f = openingBalanceFixture();
    $customer = openingCustomer($f, 'Truck Company');

    // A normal invoice that happens to carry the same internal_notes marker text the feature
    // uses. Selection by stored id (not by marker) must never touch it.
    $strayInvoice = Invoice::create([
        'company_id' => $f['company']->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-STRAY-0001',
        'invoice_date' => '2026-08-15',
        'due_date' => '2026-08-15',
        'status' => 'sent',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'subtotal' => 5000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 5000,
        'paid_amount' => 0,
        'balance' => 5000,
        'internal_notes' => 'OPENING',
    ]);
    // Invoice::generateInvoiceNumber() orders by created_at, so keep the stray invoice
    // unambiguously oldest to avoid a timestamp tie with the invoices created below.
    $strayInvoice->forceFill(['created_at' => now()->subYears(2)])->save();

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'cash' => ['amount' => 150000],
        'credit_customers' => [['customer_id' => $customer->id, 'amount' => 42000]],
    ]);
    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'cash' => ['amount' => 120000],
        'credit_customers' => [['customer_id' => $customer->id, 'amount' => 40000]],
    ]);

    expect(ledgerBalance($f['accounts']['cash']))->toBe(120000.0)
        ->and(ledgerBalance($f['accounts']['ar']))->toBe(40000.0);
    $openingSettings = $f['company']->fresh()->settings['opening_balances'];
    expect(Invoice::whereIn('id', $openingSettings['invoice_ids'])->where('status', '!=', 'void')->count())->toBe(1);
    expect($strayInvoice->fresh()->status)->not->toBe('void');
    // reverseTransaction() preserves the original transaction_type, so the reversal is
    // identified by reversal_of_id rather than by a distinct 'opening_balance_reversal' type.
    $journal = Transaction::where('company_id', $f['company']->id)
        ->where('transaction_type', 'opening_balance')
        ->whereNotNull('reversed_by_id')
        ->first();
    expect($journal)->not->toBeNull();
    expect(Transaction::where('reversal_of_id', $journal->id)->count())->toBe(1);
    $view = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.view', [], $f['user'], true));
    expect($view['rows']['cash']['amount'])->toBe(120000.0)
        ->and($view['rows']['credit_customers'][0]['amount'])->toBe(40000.0)
        ->and($view['totals']['assets'])->toBe(160000.0);
});

test('re-saving every category across three generations never leaks balances or poisons the guard', function () {
    $f = openingBalanceFixture();
    $customer = openingCustomer($f, 'Truck Company');
    $depositor = openingCustomer($f, 'Haji Saab');
    $employee = Employee::create([
        'company_id' => $f['company']->id,
        'employee_number' => 'EMP-002',
        'first_name' => 'Bilal',
        'last_name' => 'Ahmed',
        'hire_date' => '2025-01-01',
        'currency' => 'PKR',
    ]);
    $vendor = Vendor::create([
        'company_id' => $f['company']->id,
        'vendor_number' => 'VEND-0002',
        'name' => 'PSO Depot',
        'base_currency' => 'PKR',
        'is_active' => true,
        'ap_account_id' => $f['accounts']['ap']->id,
        'created_by_user_id' => $f['user']->id,
    ]);
    $partner = Partner::create([
        'company_id' => $f['company']->id,
        'name' => 'Owner Two',
        'profit_share_percentage' => 100,
    ]);

    // A normal invoice that happens to carry the same internal_notes marker; must survive all
    // three saves untouched, since selection is by stored id, never by marker.
    $strayInvoice = Invoice::create([
        'company_id' => $f['company']->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-STRAY-0002',
        'invoice_date' => '2026-08-15',
        'due_date' => '2026-08-15',
        'status' => 'sent',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'subtotal' => 5000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 5000,
        'paid_amount' => 0,
        'balance' => 5000,
        'internal_notes' => 'OPENING',
    ]);
    $strayInvoice->forceFill(['created_at' => now()->subYears(2)])->save();

    $everyCategory = fn (float $cash, float $bank, float $ar, float $advance, float $amanat, float $ap, float $partnerAmount) => [
        'as_of_date' => '2026-08-31',
        'cash' => ['amount' => $cash],
        'banks' => [['account_id' => $f['accounts']['bank']->id, 'amount' => $bank]],
        'credit_customers' => [['customer_id' => $customer->id, 'amount' => $ar]],
        'employees' => [['employee_id' => $employee->id, 'amount' => $advance]],
        'amanat' => [['customer_id' => $depositor->id, 'amount' => $amanat]],
        'suppliers' => [['vendor_id' => $vendor->id, 'amount' => $ap]],
        'partners' => [['partner_id' => $partner->id, 'amount' => $partnerAmount]],
    ];

    dispatchOpeningBalance($f, $everyCategory(100000, 200000, 42000, 5000, 30000, 250000, 1000000));
    dispatchOpeningBalance($f, $everyCategory(120000, 210000, 40000, 6000, 32000, 260000, 900000));

    $openingSettings = $f['company']->fresh()->settings['opening_balances'];
    expect(Invoice::whereIn('id', $openingSettings['invoice_ids'])->where('status', '!=', 'void')->count())->toBe(1)
        ->and(Bill::whereIn('id', $openingSettings['bill_ids'])->where('status', '!=', 'void')->count())->toBe(1)
        ->and($strayInvoice->fresh()->status)->not->toBe('void');

    expect((float) CustomerProfile::where('customer_id', $depositor->id)->first()->amanat_balance)->toBe(32000.0);
    expect(SalaryAdvance::where('employee_id', $employee->id)->count())->toBe(1)
        ->and((float) SalaryAdvance::where('employee_id', $employee->id)->first()->amount)->toBe(6000.0);
    expect(PartnerTransaction::where('partner_id', $partner->id)->count())->toBe(1)
        ->and((float) PartnerTransaction::where('partner_id', $partner->id)->first()->amount)->toBe(900000.0);

    expect(Transaction::where('company_id', $f['company']->id)
        ->where('transaction_type', 'opening_balance')
        ->whereNull('reversed_by_id')
        ->whereNull('reversal_of_id')
        ->count())->toBe(1);

    expect(ledgerBalance($f['accounts']['cash']))->toBe(120000.0)
        ->and(ledgerBalance($f['accounts']['bank']))->toBe(210000.0)
        ->and(ledgerBalance($f['accounts']['ar']))->toBe(40000.0)
        ->and(ledgerBalance($f['accounts']['advances']))->toBe(6000.0)
        ->and(ledgerBalance($f['accounts']['amanat']))->toBe(-32000.0)
        ->and(ledgerBalance($f['accounts']['ap']))->toBe(-260000.0)
        ->and(ledgerBalance($f['accounts']['partner']))->toBe(-900000.0);

    $view = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.view', [], $f['user'], true));
    expect($view['rows']['credit_customers'])->toHaveCount(1)
        ->and($view['totals']['assets'])->toBe(376000.0)
        ->and($view['totals']['liabilities'])->toBe(1192000.0)
        ->and($view['totals']['equity'])->toBe(-816000.0);

    // A genuine, unrelated transaction dated 2026-09-01 that is later reversed must still bound
    // as_of_date — reversal must not erase real history from the guard.
    $period9 = AccountingPeriod::where('company_id', $f['company']->id)->where('period_number', 9)->first();
    $unrelated = Transaction::create([
        'company_id' => $f['company']->id,
        'transaction_number' => 'JE-9001',
        'transaction_type' => 'journal',
        'transaction_date' => '2026-09-01',
        'posting_date' => '2026-09-01',
        'fiscal_year_id' => $period9->fiscal_year_id,
        'period_id' => $period9->id,
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'status' => 'posted',
        'description' => 'unrelated real transaction',
    ]);
    JournalEntry::create([
        'company_id' => $f['company']->id,
        'transaction_id' => $unrelated->id,
        'account_id' => $f['accounts']['cash']->id,
        'line_number' => 1,
        'debit_amount' => 500,
        'credit_amount' => 0,
    ]);
    JournalEntry::create([
        'company_id' => $f['company']->id,
        'transaction_id' => $unrelated->id,
        'account_id' => $f['accounts']['ar']->id,
        'line_number' => 2,
        'debit_amount' => 0,
        'credit_amount' => 500,
    ]);
    app(PostingService::class)->reverseTransaction($unrelated, 'unwinding for test');

    // A third save must succeed: the guard is not poisoned by prior generations' now-voided
    // opening invoice/bill postings or by the reversed opening journal.
    $third = dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'cash' => ['amount' => 1]]);
    expect($third['data']['journal_id'])->not->toBeNull();
    expect($strayInvoice->fresh()->status)->not->toBe('void');

    // But the reversed unrelated transaction still really happened on 2026-09-01, so it must
    // still bound as_of_date going forward.
    expect(fn () => dispatchOpeningBalance($f, ['as_of_date' => '2026-09-01', 'cash' => ['amount' => 2]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('as_of_date on or after the first posted transaction is rejected', function () {
    $f = openingBalanceFixture();
    $period = AccountingPeriod::where('company_id', $f['company']->id)->where('period_number', 9)->first();
    Transaction::create([
        'company_id' => $f['company']->id,
        'transaction_number' => 'JE-0001',
        'transaction_type' => 'journal',
        'transaction_date' => '2026-09-01',
        'posting_date' => '2026-09-01',
        'fiscal_year_id' => $period->fiscal_year_id,
        'period_id' => $period->id,
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'status' => 'posted',
        'description' => 'first close',
    ]);

    expect(fn () => dispatchOpeningBalance($f, ['as_of_date' => '2026-09-01', 'cash' => ['amount' => 1]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('a real invoice posting with no opening records saved yet still bounds the first save', function () {
    // Regression: nonOpeningTransactions() must not compile its reference_id exclusion to
    // "reference_id IS NULL" when invoice_ids/bill_ids are empty (a fresh company, before the
    // first opening-balance save has ever run) — that would hide every real invoice/bill/
    // payment posting (they all carry a non-null reference_id) from the date guard.
    $f = openingBalanceFixture();
    $period = AccountingPeriod::where('company_id', $f['company']->id)->where('period_number', 9)->first();
    Transaction::create([
        'company_id' => $f['company']->id,
        'transaction_number' => 'JE-9002',
        'transaction_type' => 'invoice',
        'transaction_date' => '2026-09-01',
        'posting_date' => '2026-09-01',
        'fiscal_year_id' => $period->fiscal_year_id,
        'period_id' => $period->id,
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'status' => 'posted',
        'description' => 'a real invoice, unrelated to opening balances',
        'reference_type' => 'acct.invoices',
        'reference_id' => (string) Str::uuid(),
    ]);

    expect(fn () => dispatchOpeningBalance($f, ['as_of_date' => '2026-09-05', 'cash' => ['amount' => 1]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $result = dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'cash' => ['amount' => 1]]);
    expect($result['data']['journal_id'])->not->toBeNull();
});

test('a save bound to a stale company instance still sees the other save that already ran', function () {
    // Reproduces the concurrent-save race without threads: two Company instances of the
    // same row are loaded up front (as two overlapping requests would), instance A saves
    // first (cash 100), then instance B — whose in-memory settings are still the
    // pre-save empty state — saves cash 200. Before the fix, SaveAction read
    // $company->settings straight off the (stale) instance the context was bound to,
    // so the second save's guards and reversePrevious() never saw the first journal:
    // both journals would end up live and cash would double-post. With the row locked
    // and settings re-read inside the transaction, the second save must reverse the
    // first generation and end up as the single live journal.
    $f = openingBalanceFixture();

    $instanceA = Company::find($f['company']->id);
    $instanceB = Company::find($f['company']->id);

    test()->actingAs($f['user']);

    app(CompanyContextService::class)->withContext($instanceA, function () use ($f) {
        app(CommandBus::class)->dispatch('opening_balance.save', [
            'as_of_date' => '2026-08-31',
            'cash' => ['amount' => 100],
        ], $f['user'], true);
    });

    // $instanceB still has the settings it was loaded with, before instance A's save —
    // simulating a second request that read the company row before the first request wrote it.
    app(CompanyContextService::class)->withContext($instanceB, function () use ($f) {
        app(CommandBus::class)->dispatch('opening_balance.save', [
            'as_of_date' => '2026-08-31',
            'cash' => ['amount' => 200],
        ], $f['user'], true);
    });

    expect(ledgerBalance($f['accounts']['cash']))->toBe(200.0);

    $liveJournals = Transaction::where('company_id', $f['company']->id)
        ->where('transaction_type', 'opening_balance')
        ->whereNull('reversed_by_id')
        ->whereNull('reversal_of_id')
        ->count();
    expect($liveJournals)->toBe(1);
});

test('locking prevents further saves', function () {
    $f = openingBalanceFixture();
    dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'cash' => ['amount' => 10]]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.lock', [], $f['user'], true));

    expect($f['company']->fresh()->settings['opening_balances']['locked_at'])->not->toBeNull();
    expect(fn () => dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'cash' => ['amount' => 20]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('viewing opening balances on a fresh company creates no equity account, saving does', function () {
    $f = openingBalanceFixture();

    $view = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.view', [], $f['user'], true));
    expect($view['rows']['cash']['amount'])->toBe(0.0);
    expect(Account::where('company_id', $f['company']->id)->where('code', '3080')->exists())->toBeFalse();

    dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'cash' => ['amount' => 10]]);

    expect(Account::where('company_id', $f['company']->id)->where('code', '3080')->exists())->toBeTrue();
});

test('locking opening balances freezes the underlying opening invoice and bill against voiding', function () {
    $f = openingBalanceFixture();
    $customer = openingCustomer($f, 'Truck Company');
    $vendor = Vendor::create([
        'company_id' => $f['company']->id,
        'vendor_number' => 'VEND-LOCK-1',
        'name' => 'PSO Depot',
        'base_currency' => 'PKR',
        'is_active' => true,
        'ap_account_id' => $f['accounts']['ap']->id,
        'created_by_user_id' => $f['user']->id,
    ]);

    $result = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'credit_customers' => [['customer_id' => $customer->id, 'amount' => 42000]],
        'suppliers' => [['vendor_id' => $vendor->id, 'amount' => 250000]],
    ]);
    $invoiceId = $result['data']['invoice_ids'][0];
    $billId = $result['data']['bill_ids'][0];

    // Before locking, voiding still works normally.
    test()->actingAs($f['user']);
    app(CompanyContextService::class)->withContext($f['company'], function () use ($invoiceId, $f) {
        app(CommandBus::class)->dispatch('invoice.void', ['id' => $invoiceId, 'reason' => 'test'], $f['user'], true);
    });
    expect(Invoice::find($invoiceId)->status)->toBe('void');

    // Re-save (the void above leaves this generation retired) then lock the new one.
    $result = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'credit_customers' => [['customer_id' => $customer->id, 'amount' => 42000]],
        'suppliers' => [['vendor_id' => $vendor->id, 'amount' => 250000]],
    ]);
    $invoiceId = $result['data']['invoice_ids'][0];
    $billId = $result['data']['bill_ids'][0];

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.lock', [], $f['user'], true));
    $lockedCompany = $f['company']->fresh();

    expect(fn () => app(CompanyContextService::class)->withContext($lockedCompany, fn () => app(CommandBus::class)->dispatch('invoice.void', ['id' => $invoiceId, 'reason' => 'test'], $f['user'], true)))
        ->toThrow(\RuntimeException::class);
    expect(fn () => app(CompanyContextService::class)->withContext($lockedCompany, fn () => app(CommandBus::class)->dispatch('bill.void', ['id' => $billId, 'reason' => 'test'], $f['user'], true)))
        ->toThrow(\RuntimeException::class);

    expect(Invoice::find($invoiceId)->status)->not->toBe('void');
    expect(Bill::find($billId)->status)->not->toBe('void');
});

test('the opening balances page requires the view permission and store requires manage', function () {
    $f = openingBalanceHttpFixture();
    $slug = $f['company']->slug;

    $this->actingAs($f['user'])
        ->get("/{$slug}/accounting/opening-balances")
        ->assertOk();

    // The stranger must belong to some company (any company) so that
    // CheckFirstTimeUser's "no memberships anywhere" redirect to /welcome
    // does not preempt the permission check this test targets. They are not
    // a member of $f['company'], which is what should trigger the 403.
    $stranger = User::factory()->create();
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$stranger->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    $strangerCompany = Company::create([
        'name' => 'Stranger Co',
        'slug' => 'stranger-co-'.str()->lower(str()->random(8)),
        'base_currency' => 'PKR',
    ]);
    DB::table('auth.company_user')->insert([
        'company_id' => $strangerCompany->id,
        'user_id' => $stranger->id,
        'role' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    $this->actingAs($stranger)
        ->post("/{$slug}/accounting/opening-balances", ['as_of_date' => '2026-08-31', 'cash' => ['amount' => 5]])
        ->assertForbidden();

    $this->actingAs($f['user'])
        ->post("/{$slug}/accounting/opening-balances", ['as_of_date' => '2026-08-31', 'cash' => ['amount' => 5]])
        ->assertRedirect("/{$slug}/accounting/opening-balances");

    expect(ledgerBalance($f['accounts']['cash']))->toBe(5.0);
});

test('credit customer index reports the opening receivable as current balance', function () {
    $f = openingBalanceHttpFixture();
    $f['company']->enableModule('fuel_station');
    $customer = openingCustomer($f, 'Truck Company');
    CustomerProfile::getOrCreateForCustomer($f['company']->id, $customer->id)->update(['is_credit_customer' => true]);
    dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'credit_customers' => [['customer_id' => $customer->id, 'amount' => 42000]]]);

    $response = $this->actingAs($f['user'])->get("/{$f['company']->slug}/fuel/credit-customers");
    $response->assertOk();
    $customers = $response->viewData('page')['props']['customers'];
    expect(collect($customers)->firstWhere('id', $customer->id)['current_balance'])->toBe(42000.0);
});

test('the fuel onboarding opening-cash route is gone', function () {
    $f = openingBalanceHttpFixture();
    $f['company']->enableModule('fuel_station');
    $this->actingAs($f['user'])
        ->post("/{$f['company']->slug}/fuel/onboarding/opening-cash", ['as_of_date' => '2026-08-31', 'cash_on_hand' => 1])
        ->assertNotFound();
});


test('locked opening documents reject ordinary updates and direct journal reversal using stale context', function () {
    $f = openingBalanceFixture();
    $customer = openingCustomer($f, 'Locked customer');
    $vendor = Vendor::create([
        'company_id' => $f['company']->id, 'vendor_number' => 'LOCKED-AP', 'name' => 'Locked supplier',
        'base_currency' => 'PKR', 'is_active' => true, 'ap_account_id' => $f['accounts']['ap']->id,
    ]);
    $saved = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31', 'cash' => ['amount' => 100],
        'credit_customers' => [['customer_id' => $customer->id, 'amount' => 100]],
        'suppliers' => [['vendor_id' => $vendor->id, 'amount' => 100]],
    ]);
    $stale = $f['company']->fresh();
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.lock', [], $f['user'], true));
    $invoice = Invoice::findOrFail($saved['data']['invoice_ids'][0]);
    $bill = Bill::findOrFail($saved['data']['bill_ids'][0]);
    $before = JournalEntry::where('company_id', $f['company']->id)->get()->toArray();
    app(CompanyContextService::class)->withContext($stale, function () use ($f, $invoice, $bill, $customer, $saved) {
        expect(fn () => app(CommandBus::class)->dispatch('invoice.update', [
            'id' => $invoice->id, 'customer' => $customer->id, 'currency' => 'PKR',
            'line_items' => [['description' => 'Changed opening', 'quantity' => 1, 'unit_price' => 200]],
        ], $f['user'], true))->toThrow(\RuntimeException::class);
        expect(fn () => app(CommandBus::class)->dispatch('bill.update', [
            'id' => $bill->id, 'line_items' => [['description' => 'Changed opening', 'quantity' => 1, 'unit_price' => 200]],
        ], $f['user'], true))->toThrow(\RuntimeException::class);
        foreach ([$invoice->transaction_id, $bill->transaction_id, $saved['data']['journal_id']] as $id) {
            expect(fn () => app(PostingService::class)->reverseTransaction(Transaction::findOrFail($id)))
                ->toThrow(\RuntimeException::class);
        }
    });
    expect((float) $invoice->fresh()->total_amount)->toBe(100.0);
    expect((float) $bill->fresh()->total_amount)->toBe(100.0);
    expect(JournalEntry::where('company_id', $f['company']->id)->get()->toArray())->toBe($before);
});


test('locked opening principal and lines are protected against direct model and SQL mutation', function () {
    $f = openingBalanceFixture();
    $customer = openingCustomer($f, 'SQL protected');
    $saved = dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'credit_customers' => [['customer_id' => $customer->id, 'amount' => 100]]]);
    $invoice = Invoice::findOrFail($saved['data']['invoice_ids'][0]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.lock', [], $f['user'], true));
    expect(fn () => $invoice->update(['total_amount' => 200]))->toThrow(\RuntimeException::class);
    expect(fn () => $invoice->delete())->toThrow(\RuntimeException::class);
    expect(fn () => DB::transaction(fn () => \App\Modules\Accounting\Models\InvoiceLineItem::create(['company_id' => $f['company']->id, 'invoice_id' => $invoice->id, 'line_number' => 1, 'description' => 'Injected line', 'quantity' => 1, 'unit_price' => 200, 'line_total' => 200, 'tax_amount' => 0, 'total' => 200])))
        ->toThrow(\Illuminate\Database\QueryException::class);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$f['company']->id]);
    expect(fn () => DB::transaction(fn () => DB::table('acct.invoices')->where('id', $invoice->id)->update(['total_amount' => 200])))
        ->toThrow(\Illuminate\Database\QueryException::class);
    expect((float) $invoice->fresh()->total_amount)->toBe(100.0);
});


test('locked opening receivable can be settled by a canonical payment without rewriting its principal journal', function () {
    $f = openingBalanceFixture(); test()->actingAs($f['user']);
    $customer = openingCustomer($f, 'Pay opening');
    $saved = dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'credit_customers' => [['customer_id' => $customer->id, 'amount' => 100]]]);
    $invoice = Invoice::findOrFail($saved['data']['invoice_ids'][0]);
    $entries = $invoice->transaction->journalEntries->toArray();
    app(CompanyContextService::class)->withContext($f['company'], function () use ($f, $invoice) {
        app(CommandBus::class)->dispatch('opening_balance.lock', [], $f['user'], true);
        app(CommandBus::class)->dispatch('payment.create', ['invoice' => $invoice->id, 'amount' => 100, 'method' => 'cash',
            'date' => '2026-09-15', 'deposit_account_id' => $f['accounts']['cash']->id, 'ar_account_id' => $f['accounts']['ar']->id], $f['user'], true);
    });
    expect((float) $invoice->fresh()->total_amount)->toBe(100.0);
    expect((float) $invoice->fresh()->balance)->toBe(0.0);
    expect($invoice->fresh()->transaction->journalEntries->toArray())->toBe($entries);
    expect(ledgerBalance($f['accounts']['ar']))->toBe(0.0);
});
