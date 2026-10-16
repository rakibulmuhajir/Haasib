<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\PostingTemplate;
use App\Modules\Accounting\Models\PostingTemplateLine;
use App\Services\CommandBus;
use App\Services\CompanyBootstrapService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * A company with a chart, posting templates and an owner, plus a member who may not
 * record sales or payments (role "operations").
 */
function cashSaleFixture(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $limited = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Cash Sale Co '.str()->random(8),
        'slug' => 'cash-sale-'.str()->lower(str()->random(10)),
        'base_currency' => 'PKR',
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);

    foreach ([[$owner, 'owner'], [$limited, 'operations']] as [$user, $role]) {
        DB::table('auth.company_user')->insert([
            'company_id' => $company->id, 'user_id' => $user->id, 'role' => $role,
            'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(CompanyContextService::class)->withContext(
            $company,
            fn () => app(CompanyContextService::class)->assignRole($user, $role),
        );
    }

    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    enterCompany($company);

    $today = app(\App\Support\CompanyClock::class)->today();
    $year = (int) substr($today, 0, 4);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => (string) $year, 'start_date' => "$year-01-01", 'end_date' => "$year-12-31", 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'Whole year', 'period_number' => 1, 'start_date' => "$year-01-01", 'end_date' => "$year-12-31"]);

    $ar = Account::create(['company_id' => $company->id, 'code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $cash = Account::create(['company_id' => $company->id, 'code' => '1050', 'name' => 'Cash on Hand', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $bank = Account::create(['company_id' => $company->id, 'code' => '1060', 'name' => 'Meezan Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $bank2 = Account::create(['company_id' => $company->id, 'code' => '1061', 'name' => 'HBL Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $revenue = Account::create(['company_id' => $company->id, 'code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue', 'subtype' => 'other_income', 'normal_balance' => 'credit']);
    $ap = Account::create(['company_id' => $company->id, 'code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'currency' => 'PKR', 'is_active' => true]);
    $tea = Account::create(['company_id' => $company->id, 'code' => '6200', 'name' => 'Tea and Refreshments', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit']);
    $charges = Account::create(['company_id' => $company->id, 'code' => '6520', 'name' => 'Bank Charges & Fees', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit']);

    $company->ar_account_id = $ar->id;
    $company->ap_account_id = $ap->id;
    $company->save();

    foreach (['AR_INVOICE', 'AR_PAYMENT'] as $docType) {
        $template = PostingTemplate::create(['company_id' => $company->id, 'doc_type' => $docType, 'name' => $docType, 'is_active' => true, 'is_default' => true, 'effective_from' => "$year-01-01", 'version' => 1]);
        PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'AR', 'account_id' => $ar->id]);
        if ($docType === 'AR_INVOICE') {
            PostingTemplateLine::create(['template_id' => $template->id, 'role' => 'REVENUE', 'account_id' => $revenue->id]);
        }
    }

    return compact('owner', 'limited', 'company', 'ar', 'ap', 'cash', 'bank', 'bank2', 'revenue', 'tea', 'charges', 'today');
}

/** Journal lines of every transaction in the company, as sorted "code|debit|credit" strings. */
function cashSaleJournal(Company $company, array $exceptTransactionIds = []): array
{
    return DB::table('acct.journal_entries as je')
        ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
        ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
        ->where('t.company_id', $company->id)
        ->whereNotIn('t.id', $exceptTransactionIds)
        ->orderBy('a.code')->orderBy('je.debit_amount')->orderBy('je.credit_amount')
        ->get(['a.code', 'je.debit_amount', 'je.credit_amount'])
        ->map(fn ($r) => $r->code.'|'.(float) $r->debit_amount.'|'.(float) $r->credit_amount)
        ->all();
}
