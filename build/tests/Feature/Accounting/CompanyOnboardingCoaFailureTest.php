<?php

use App\Models\Company;
use App\Modules\Accounting\Exceptions\IndustryCoaPackNotSeededException;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\IndustryCoaPack;
use App\Modules\Accounting\Services\CompanyOnboardingService;
use Illuminate\Support\Facades\DB;

/**
 * Reproduces the incident: a pack row exists in acct.industry_coa_packs but has
 * zero rows in acct.industry_coa_templates. Onboarding must fail loudly instead
 * of silently creating an empty chart of accounts while reporting success.
 */
function onboardingCoaFailureCompany(): Company
{
    $company = Company::create([
        'name' => 'Unseeded Pack Co '.str()->random(8),
        'slug' => 'unseeded-pack-'.str()->lower(str()->random(10)),
        'base_currency' => 'USD',
    ]);

    enterCompany($company);

    return $company;
}

function seedEmptyIndustryPack(string $code): void
{
    IndustryCoaPack::create([
        'code' => $code,
        'name' => 'Empty Test Pack',
        'is_active' => true,
        'sort_order' => 999,
    ]);
    // Deliberately no acct.industry_coa_templates rows for this pack.
}

it('throws instead of silently creating an empty chart of accounts', function () {
    $code = 'empty_test_pack_'.str()->random(6);
    seedEmptyIndustryPack($code);
    $company = onboardingCoaFailureCompany();

    expect(fn () => app(CompanyOnboardingService::class)->setupCompanyIdentity($company, [
        'industry_code' => $code,
        'timezone' => 'UTC',
    ]))->toThrow(IndustryCoaPackNotSeededException::class);

    expect(Account::where('company_id', $company->id)->count())->toBe(0);
});

it('does not leave onboarding marked complete for the failed step', function () {
    $code = 'empty_test_pack_'.str()->random(6);
    seedEmptyIndustryPack($code);
    $company = onboardingCoaFailureCompany();

    try {
        app(CompanyOnboardingService::class)->setupCompanyIdentity($company, [
            'industry_code' => $code,
            'timezone' => 'UTC',
        ]);
    } catch (IndustryCoaPackNotSeededException $e) {
        // expected
    }

    $onboarding = DB::table('auth.company_onboarding')->where('company_id', $company->id)->first();

    expect($onboarding === null || ! in_array('company-identity', json_decode($onboarding->completed_steps ?? '[]', true) ?? [], true))
        ->toBeTrue();
});

it('rethrows through CompanyBootstrapService instead of swallowing it', function () {
    $code = 'empty_test_pack_'.str()->random(6);
    seedEmptyIndustryPack($code);
    $company = onboardingCoaFailureCompany();

    expect(fn () => app(\App\Services\CompanyBootstrapService::class)->bootstrap($company, $code, null))
        ->toThrow(IndustryCoaPackNotSeededException::class);
});
