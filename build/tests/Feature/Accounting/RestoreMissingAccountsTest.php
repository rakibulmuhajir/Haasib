<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\IndustryCoaPack;
use App\Modules\Accounting\Models\IndustryCoaTemplate;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

function restoreMissingSeedPack(): string
{
    $code = 'restore_ui_'.str()->lower(str()->random(8));

    $pack = IndustryCoaPack::create([
        'code' => $code,
        'name' => 'Restore UI Test Pack',
        'is_active' => true,
        'sort_order' => 999,
    ]);

    foreach ([
        ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control', 'sort_order' => 1],
        ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control', 'sort_order' => 2],
        ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings', 'sort_order' => 3],
        ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue', 'sort_order' => 4],
    ] as $t) {
        IndustryCoaTemplate::create(array_merge($t, [
            'industry_pack_id' => $pack->id,
            'is_contra' => false,
            'description' => null,
        ]));
    }

    return $code;
}

function restoreMissingCompanyWithMember(string $industryCode, string $role = 'owner'): array
{
    $user = User::factory()->create();

    $company = Company::create([
        'name' => 'Restore UI Co '.str()->random(6),
        'slug' => 'restore-ui-co-'.str()->lower(str()->random(10)),
        'base_currency' => 'USD',
        'industry_code' => $industryCode,
    ]);

    app(CompanyRbacBootstrapper::class)->bootstrap($company);

    enterCompany($company);

    addCompanyMemberRow($company, $user, $role);

    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($user, $role),
    );

    return ['user' => $user, 'company' => $company];
}

it('creates missing accounts for the current company on confirmation', function () {
    $industryCode = restoreMissingSeedPack();
    $fixture = restoreMissingCompanyWithMember($industryCode, 'owner');

    // AP/RE/Revenue already exist, AR is missing (reproduces the incident).
    Account::create(['company_id' => $fixture['company']->id, 'code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'currency' => 'USD', 'is_active' => true, 'is_system' => true]);

    $response = $this->actingAs($fixture['user'])
        ->post("/{$fixture['company']->slug}/accounts/restore-missing");

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(Account::where('company_id', $fixture['company']->id)->where('subtype', 'accounts_receivable')->exists())->toBeTrue();
});

it('refuses without the account.create permission', function () {
    $industryCode = restoreMissingSeedPack();
    // 'agent' role has no account.create permission per config/role-permissions.php
    $fixture = restoreMissingCompanyWithMember($industryCode, 'agent');

    $response = $this->actingAs($fixture['user'])
        ->post("/{$fixture['company']->slug}/accounts/restore-missing");

    $response->assertForbidden();

    expect(Account::where('company_id', $fixture['company']->id)->where('subtype', 'accounts_receivable')->exists())->toBeFalse();
});

it('rejects a request for another company the user does not belong to', function () {
    $industryCode = restoreMissingSeedPack();
    $fixtureA = restoreMissingCompanyWithMember($industryCode, 'owner');
    $fixtureB = restoreMissingCompanyWithMember($industryCode, 'owner');

    // fixtureA's user is not a member of fixtureB's company. Under enforced
    // row level security that company is not visible to them at all, so
    // IdentifyCompany answers 404 before RBAC gets the chance to answer 403.
    $response = $this->actingAs($fixtureA['user'])
        ->post("/{$fixtureB['company']->slug}/accounts/restore-missing");

    expect($response->getStatusCode())->toBeIn([403, 404]);
});

it('reports nothing missing when the company already has every standard account', function () {
    $industryCode = restoreMissingSeedPack();
    $fixture = restoreMissingCompanyWithMember($industryCode, 'owner');

    foreach ([
        ['code' => '1100', 'subtype' => 'accounts_receivable', 'type' => 'asset', 'normal_balance' => 'debit'],
        ['code' => '2100', 'subtype' => 'accounts_payable', 'type' => 'liability', 'normal_balance' => 'credit'],
        ['code' => '3100', 'subtype' => 'retained_earnings', 'type' => 'equity', 'normal_balance' => 'credit'],
        ['code' => '4000', 'subtype' => 'revenue', 'type' => 'revenue', 'normal_balance' => 'credit'],
    ] as $a) {
        Account::create(array_merge($a, [
            'company_id' => $fixture['company']->id,
            'name' => 'Existing '.$a['code'],
            'currency' => $a['subtype'] === 'accounts_receivable' || $a['subtype'] === 'accounts_payable' ? 'USD' : null,
            'is_active' => true,
            'is_system' => true,
        ]));
    }

    $response = $this->actingAs($fixture['user'])
        ->post("/{$fixture['company']->slug}/accounts/restore-missing");

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Nothing missing -- this company already has every standard account for its industry.');
});
