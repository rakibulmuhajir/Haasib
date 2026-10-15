<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\CompanyOnboardingService;
use App\Modules\Accounting\Services\DefaultAccountProvisioner;
use App\Modules\Accounting\Services\OpeningBalanceAccounts;
use App\Modules\Accounting\Services\SalesDiscountAccountResolver;
use App\Modules\Payroll\Services\PayrollPostingService;
use Database\Seeders\IndustryCoaPackSeeder;
use Illuminate\Support\Facades\DB;

function generalCoaMigration(): object
{
    return require database_path('migrations/2026_10_15_000001_replace_general_coa_pack.php');
}

function generalTemplates(string $packCode): array
{
    return DB::table('acct.industry_coa_templates as t')
        ->join('acct.industry_coa_packs as p', 'p.id', '=', 't.industry_pack_id')
        ->where('p.code', $packCode)
        ->orderBy('t.sort_order')
        ->get(['t.code', 't.name', 't.type', 't.subtype', 't.normal_balance', 't.is_contra', 't.is_system', 't.system_identifier', 't.sort_order'])
        ->map(fn ($r) => (array) $r)
        ->all();
}

function generalCompany(): Company
{
    $company = Company::create([
        'name' => 'General Co '.str()->random(8),
        'slug' => 'general-co-'.str()->lower(str()->random(10)),
        'base_currency' => 'USD',
    ]);
    enterCompany($company);

    app(CompanyOnboardingService::class)->setupCompanyIdentity($company, [
        'industry_code' => 'other',
        'timezone' => 'UTC',
    ]);

    return $company->fresh();
}

test('a new general company gets the general chart and core lookups land on it', function () {
    $this->seed(IndustryCoaPackSeeder::class);
    $company = generalCompany();

    $before = Account::where('company_id', $company->id)->count();
    $names = Account::where('company_id', $company->id)->pluck('name', 'code');

    expect($names)->not->toHaveKey('2200')
        ->and($names['6200'])->toBe('Salaries & Wages')
        ->and($names['2211'])->toBe('Salary Payable')
        ->and($names['7050'])->toBe('Inventory Gain')
        ->and($names['8060'])->toBe('Inventory Shrinkage')
        ->and($names['4210'])->toBe('Sales Discounts');

    // Default accounts resolve onto the pack, not onto new accounts.
    app(DefaultAccountProvisioner::class)->ensureCoreDefaults($company);
    app(DefaultAccountProvisioner::class)->ensureTransitAccounts($company);
    $company = $company->fresh();
    $code = fn (?string $id) => Account::find($id)?->code;
    expect($code($company->ar_account_id))->toBe('1100')
        ->and($code($company->ap_account_id))->toBe('2100')
        ->and($code($company->income_account_id))->toBe('4000')
        ->and($code($company->retained_earnings_account_id))->toBe('3100')
        ->and($code($company->transit_loss_account_id))->toBe('8060')
        ->and($code($company->transit_gain_account_id))->toBe('7050');
    expect(Account::find($company->expense_account_id)->type)->toBe('expense');

    expect(app(SalesDiscountAccountResolver::class)->resolve($company)->code)->toBe('4210');
    expect(app(OpeningBalanceAccounts::class)->resolve($company->id, false)['equity'])->not->toBeNull();
    $opening = app(OpeningBalanceAccounts::class)->resolve($company->id, false);
    expect(Account::find($opening['partner_deposits'])->name)->toBe('Partner Deposits')
        ->and(Account::find($opening['employee_advances'])->name)->toBe('Employee Advances');

    // Bank/cash template rows are skipped at creation (the bank-setup step makes
    // them), so give payroll the bank account a real company would have by now.
    Account::create(['company_id' => $company->id, 'code' => '1000', 'name' => 'Bank Account', 'type' => 'asset',
        'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'USD', 'is_active' => true]);
    $before++;

    // Payroll resolves to the intended rows and creates nothing.
    $payroll = app(PayrollPostingService::class)->ensureDefaultPayrollAccounts($company->id);
    expect($payroll['salary_expense']['code'])->toBe('6200')
        ->and($payroll['payroll_payable']['code'])->toBe('2211')
        ->and($payroll['deduction_payable']['code'])->toBe('2221')
        ->and($payroll['employee_advances']['code'])->toBe('1150');

    // Nothing was created by any of those lookups.
    expect(Account::where('company_id', $company->id)->count())->toBe($before);
});

test('the migration matches the seeder and rolls back to the old thirteen rows', function () {
    $this->seed(IndustryCoaPackSeeder::class);
    $seeded = generalTemplates('other');
    expect($seeded)->toHaveCount(44);

    $migration = generalCoaMigration();

    $migration->down();
    $old = generalTemplates('other');
    expect($old)->toHaveCount(13)
        ->and(array_column($old, 'code'))->toContain('2200', '5000')
        ->and(DB::table('acct.industry_coa_packs')->where('code', 'other')->value('name'))->toBe('Other');

    $migration->up();
    expect(generalTemplates('other'))->toBe($seeded)
        ->and(DB::table('acct.industry_coa_packs')->where('code', 'other')->value('name'))->toBe('General business');
});

test('the general pack leaves other packs and existing companies untouched', function () {
    $this->seed(IndustryCoaPackSeeder::class);
    $migration = generalCoaMigration();
    $migration->down();

    $company = generalCompany(); // built on the old pack
    $accounts = fn () => Account::where('company_id', $company->id)->orderBy('code')->get(['code', 'name', 'type', 'subtype'])->toArray();
    $accountsBefore = $accounts();
    $fuelBefore = generalTemplates('fuel_station');
    $travelBefore = generalTemplates('travel');

    $migration->up();

    expect($accounts())->toBe($accountsBefore)
        ->and(generalTemplates('fuel_station'))->toBe($fuelBefore)
        ->and(generalTemplates('travel'))->toBe($travelBefore);
});
