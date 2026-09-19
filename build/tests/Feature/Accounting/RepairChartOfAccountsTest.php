<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\IndustryCoaPack;
use App\Modules\Accounting\Models\IndustryCoaTemplate;
use Illuminate\Support\Facades\Artisan;

function repairCoaCompany(string $industryCode, ?string $baseCurrency = 'USD'): Company
{
    $company = Company::create([
        'name' => 'Repair Co '.str()->random(8),
        'slug' => 'repair-co-'.str()->lower(str()->random(10)),
        'base_currency' => $baseCurrency,
        'industry_code' => $industryCode,
    ]);

    enterCompany($company);

    return $company;
}

function seedRepairCoaPack(): string
{
    $code = 'repair_test_'.str()->lower(str()->random(8));

    $pack = IndustryCoaPack::create([
        'code' => $code,
        'name' => 'Repair Test Pack',
        'is_active' => true,
        'sort_order' => 999,
    ]);

    $templates = [
        ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control', 'sort_order' => 1],
        ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control', 'sort_order' => 2],
        ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings', 'sort_order' => 3],
        ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue', 'sort_order' => 4],
        ['code' => '5000', 'name' => 'Operating Expenses', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit', 'is_system' => false, 'system_identifier' => null, 'sort_order' => 5],
        ['code' => '1000', 'name' => 'Operating Bank Account', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => null, 'sort_order' => 6],
    ];

    foreach ($templates as $t) {
        IndustryCoaTemplate::create(array_merge($t, [
            'industry_pack_id' => $pack->id,
            'is_contra' => false,
            'description' => null,
        ]));
    }

    return $code;
}

it('creates a missing AR account and reports posting-critical identifiers', function () {
    $industryCode = seedRepairCoaPack();
    $company = repairCoaCompany($industryCode);

    // Simulate the incident: everything except AR already exists.
    Account::create(['company_id' => $company->id, 'code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'currency' => $company->base_currency, 'is_active' => true, 'is_system' => true]);
    Account::create(['company_id' => $company->id, 'code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'currency' => null, 'is_active' => true, 'is_system' => true]);
    Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'currency' => null, 'is_active' => true, 'is_system' => true]);

    expect(Account::where('company_id', $company->id)->where('subtype', 'accounts_receivable')->exists())->toBeFalse();

    $exitCode = Artisan::call('accounting:repair-coa', ['--company' => $company->slug]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('1100')
        ->and($output)->toContain('ar_control');

    $ar = Account::where('company_id', $company->id)->where('code', '1100')->first();
    expect($ar)->not->toBeNull()
        ->and($ar->subtype)->toBe('accounts_receivable');
});

it('never touches an existing renamed account', function () {
    $industryCode = seedRepairCoaPack();
    $company = repairCoaCompany($industryCode);

    $renamed = Account::create([
        'company_id' => $company->id,
        'code' => '5000',
        'name' => 'HBL Overheads',
        'type' => 'expense',
        'subtype' => 'operating_expense',
        'normal_balance' => 'debit',
        'currency' => null,
        'is_active' => true,
        'is_system' => false,
        'description' => 'Renamed by the owner',
    ]);

    Artisan::call('accounting:repair-coa', ['--company' => $company->slug]);

    $renamed->refresh();
    expect($renamed->name)->toBe('HBL Overheads')
        ->and($renamed->description)->toBe('Renamed by the owner');
});

it('dry-run writes nothing', function () {
    $industryCode = seedRepairCoaPack();
    $company = repairCoaCompany($industryCode);

    $before = Account::where('company_id', $company->id)->count();

    $exitCode = Artisan::call('accounting:repair-coa', ['--company' => $company->slug, '--dry-run' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('DRY RUN')
        ->and(Account::where('company_id', $company->id)->count())->toBe($before);
});

it('reports a subtype conflict as a warning and does not change it', function () {
    $industryCode = seedRepairCoaPack();
    $company = repairCoaCompany($industryCode);

    // Code 1100 exists but with a different subtype than the AR template.
    $conflict = Account::create([
        'company_id' => $company->id,
        'code' => '1100',
        'name' => 'Some Other Account',
        'type' => 'asset',
        'subtype' => 'other_current_asset',
        'normal_balance' => 'debit',
        'currency' => $company->base_currency,
        'is_active' => true,
        'is_system' => false,
    ]);

    Artisan::call('accounting:repair-coa', ['--company' => $company->slug]);
    $output = Artisan::output();

    expect($output)->toContain('Conflicts');

    $conflict->refresh();
    expect($conflict->subtype)->toBe('other_current_asset')
        ->and($conflict->name)->toBe('Some Other Account');
});

it('covers every company with --all', function () {
    $industryCode = seedRepairCoaPack();
    $companyA = repairCoaCompany($industryCode);
    $companyB = repairCoaCompany($industryCode);

    Artisan::call('accounting:repair-coa', ['--all' => true]);

    foreach ([$companyA, $companyB] as $company) {
        // Reading one company's rows means being in that company.
        enterCompany($company);

        expect(Account::where('company_id', $company->id)->where('subtype', 'accounts_receivable')->exists())->toBeTrue();
    }
});

it('sets currency to null on non-monetary accounts and base currency on monetary ones', function () {
    $industryCode = seedRepairCoaPack();
    $company = repairCoaCompany($industryCode, 'PKR');

    Artisan::call('accounting:repair-coa', ['--company' => $company->slug]);

    $ar = Account::where('company_id', $company->id)->where('code', '1100')->first();
    $revenue = Account::where('company_id', $company->id)->where('code', '4000')->first();

    expect($ar->currency)->toBe('PKR')
        ->and($revenue->currency)->toBeNull();
});

it('creates nothing the second time it runs', function () {
    $industryCode = seedRepairCoaPack();
    $company = repairCoaCompany($industryCode);

    Artisan::call('accounting:repair-coa', ['--company' => $company->slug]);
    $countAfterFirst = Account::where('company_id', $company->id)->count();

    Artisan::call('accounting:repair-coa', ['--company' => $company->slug]);
    $countAfterSecond = Account::where('company_id', $company->id)->count();

    expect($countAfterSecond)->toBe($countAfterFirst);
});

it('requires either --company or --all', function () {
    $exitCode = Artisan::call('accounting:repair-coa');

    expect($exitCode)->not->toBe(0);
});
