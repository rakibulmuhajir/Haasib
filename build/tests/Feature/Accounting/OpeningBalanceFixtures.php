<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * Fixtures shared by every opening-balance test in this directory.
 *
 * They started out inside OpeningBalancesTest.php, which is fine while that is the only file
 * using them and the whole directory is run at once. It stops being fine the moment a second
 * file needs them and someone runs that file on its own - the functions are simply not
 * declared, and the failure looks nothing like the real cause. Hence a plain required file
 * rather than a test file, so neither one depends on the other having been loaded.
 */

function openingBalanceFixture(): array
{
    $user = User::factory()->create();

    $company = Company::create([
        'name' => 'Opening Balance Test',
        'slug' => 'opening-balance-test-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ]);

    enterCompany($company);

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

    // A customer or supplier created through the app (Quick Add, Add Holder) carries no AR/AP
    // account of its own; posting falls back to the company's templates, as it does on a real
    // company. Without these, only the hand-built openingCustomer() rows could be invoiced.
    foreach (['AR_INVOICE' => ['AR', $f['accounts']['ar']], 'AP_BILL' => ['AP', $f['accounts']['ap']]] as $docType => [$role, $account]) {
        $template = PostingTemplate::create([
            'company_id' => $company->id, 'doc_type' => $docType, 'name' => $docType,
            'is_active' => true, 'is_default' => true, 'effective_from' => '2026-01-01', 'version' => 1,
        ]);
        PostingTemplateLine::create(['template_id' => $template->id, 'role' => $role, 'account_id' => $account->id]);
    }

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
