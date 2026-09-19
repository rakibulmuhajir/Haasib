<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\CompanyOnboardingService;
use App\Modules\FuelStation\Models\StationSettings;
use Database\Seeders\IndustryCoaPackSeeder;

it('prepares fuel accounting when the owner saves named banks and cash, without a mapping step', function () {
    $this->seed(IndustryCoaPackSeeder::class);
    $company = Company::create([
        'name' => 'Simple Fuel Setup',
        'slug' => 'simple-fuel-'.str()->lower(str()->random(8)),
        'base_currency' => 'PKR',
    ]);
    enterCompany($company);
    $service = app(CompanyOnboardingService::class);
    $company = $service->setupCompanyIdentity($company, ['industry_code' => 'fuel_station']);
    app(\App\Modules\FuelStation\Services\FuelStationOnboardingService::class)->ensureRequiredAccounts($company->id);
    $company->refresh();
    app(\App\Modules\FuelStation\Services\FuelStationOnboardingService::class)->ensureRequiredAccounts($company->id);
    $company->refresh();
    $bank = Account::where('company_id', $company->id)->where('subtype', 'bank')->orderBy('code')->firstOrFail();
    $cash = Account::where('company_id', $company->id)->where('subtype', 'cash')->orderBy('code')->firstOrFail();
    app(\App\Modules\Accounting\Services\CompanyBankAccountSyncService::class)->ensureForCompany($company->id);
    $rows = [
        ['id' => $bank->id, 'account_name' => 'UBL Current Account', 'currency' => 'PKR', 'account_type' => 'bank'],
        ['id' => $cash->id, 'account_name' => 'Cash on Hand', 'currency' => 'PKR', 'account_type' => 'cash'],
        ['account_name' => 'Meezan', 'currency' => 'PKR', 'account_type' => 'bank'],
    ];

    $created = $service->setupBankAccounts($company, $rows);
    $company->refresh();
    $settings = StationSettings::where('company_id', $company->id)->firstOrFail();
    expect($company->ar_account_id)->not->toBeNull()
        ->and($company->ap_account_id)->not->toBeNull()
        ->and($company->income_account_id)->not->toBeNull()
        ->and($company->expense_account_id)->not->toBeNull()
        ->and($company->retained_earnings_account_id)->not->toBeNull()
        ->and($company->bank_account_id)->toBe($bank->id)
        ->and($settings->operating_bank_account_id)->toBe($bank->id)
        ->and($settings->cash_account_id)->toBe($cash->id)
        ->and($company->onboarding->completed_steps)->toContain('bank-accounts', 'default-accounts');
    expect(\App\Modules\Accounting\Models\BankAccount::where('gl_account_id', $bank->id)->value('account_name'))
        ->toBe('UBL Current Account');

    // A second save must reuse the banks and preserve an explicitly selected channel destination.
    $rows[2]['id'] = $created[0]->id;
    $settings->update(['payment_channels' => [
        ['code' => 'transfer', 'type' => 'bank_transfer', 'enabled' => true, 'bank_account_id' => $created[0]->id],
    ]]);
    $count = Account::where('company_id', $company->id)->count();
    $service->setupBankAccounts($company, $rows);
    expect(Account::where('company_id', $company->id)->count())->toBe($count)
        ->and($settings->fresh()->payment_channels[0]['bank_account_id'])->toBe($created[0]->id);
});
