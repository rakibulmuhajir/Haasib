<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\IndustryCoaPack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CompanyController@store creates and commits the company row in its own transaction,
 * then CompanyBootstrapService::bootstrap() runs afterwards in a separate one. A bootstrap
 * failure -- an industry COA pack seeded with zero templates is the natural trigger, see
 * IndustryCoaPackNotSeededException -- used to leave a committed company with no chart of
 * accounts: no AR, no AP, no retained earnings. A tester hit exactly this, where the first
 * credit sale failed at posting with "Set up a base-currency receivables account for this
 * customer first."
 *
 * Design chosen: keep the two transactions (design b), because at least one step inside
 * bootstrap -- FuelStationModuleInstaller::ensureMigrationsApplied(), which takes a
 * Postgres advisory lock and runs `migrate` -- runs DDL and must not be wrapped inside the
 * company-creation transaction (DDL plus an open transaction plus an advisory lock is
 * exactly the shape of deadlock this project has already hit in this session's own test
 * run: "deadlock detected ... DROP SCHEMA IF EXISTS inv CASCADE" from two concurrent test
 * processes). Extending the transaction (design a) would routinely hold that lock and any
 * uncommitted DDL open for the lifetime of company creation, which is unsafe. Instead,
 * Company now carries an explicit `bootstrap_incomplete_at` timestamp
 * (database/migrations/2026_09_18_000001_add_bootstrap_incomplete_at_to_companies.php),
 * set by CompanyBootstrapService when IndustryCoaPackNotSeededException escapes bootstrap()
 * and cleared once the existing repair action (AccountController::restoreMissing) succeeds.
 * IdentifyCompany blocks every company-scoped route except the repair action, the accounts
 * page it lives on, company settings and logout while the flag is set.
 */
function bootstrapAtomicityOwner(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    test()->actingAs($owner);

    return ['owner' => $owner];
}

/**
 * Reproduces the real incident rather than inventing a new one: the pack for a genuine,
 * selectable industry exists, but its templates are missing, so company creation passes
 * validation and only fails once bootstrap reaches the chart of accounts. Injecting a
 * fake industry code instead is rejected up front by the industry validation and never
 * reaches bootstrap at all.
 */
function emptyTheTemplatesFor(string $code): void
{
    $pack = IndustryCoaPack::where('code', 'other')->sole();
    DB::table('acct.industry_coa_templates')->where('industry_pack_id', $pack->id)->delete();
}

function storeCompanyPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Bootstrap Atomicity Co '.str()->random(8),
        'industry_code' => 'other',
        'country' => 'US',
        'base_currency' => 'USD',
        'timezone' => 'UTC',
    ], $overrides);
}

test('the happy path creates a fully-built company with AR, AP and retained earnings present', function () {
    $f = bootstrapAtomicityOwner();

    $response = test()->post('/companies', storeCompanyPayload());

    $response->assertSessionHasNoErrors();
    $company = Company::where('name', 'like', 'Bootstrap Atomicity Co%')->sole();

    expect($company->bootstrap_incomplete_at)->toBeNull();
    expect($company->ar_account_id)->not->toBeNull();
    expect($company->ap_account_id)->not->toBeNull();
    expect($company->retained_earnings_account_id)->not->toBeNull();
    expect(Account::where('id', $company->ar_account_id)->exists())->toBeTrue();
    expect(Account::where('id', $company->ap_account_id)->exists())->toBeTrue();
    expect(Account::where('id', $company->retained_earnings_account_id)->exists())->toBeTrue();
});

test('a bootstrap failure leaves the company marked incomplete instead of silently usable', function () {
    $f = bootstrapAtomicityOwner();
    emptyTheTemplatesFor('other');

    $response = test()->post('/companies', storeCompanyPayload(['industry_code' => 'other']));

    // The company row survives (design b keeps the two transactions) and the user is
    // still redirected somewhere real, with a visible error -- not a 500, not silence.
    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('error');

    $company = Company::where('name', 'like', 'Bootstrap Atomicity Co%')->sole();
    expect($company->bootstrap_incomplete_at)->not->toBeNull();
    expect(Account::where('company_id', $company->id)->count())->toBe(0);
});

test('an incomplete company redirects ordinary pages to the repair path instead of rendering them', function () {
    $f = bootstrapAtomicityOwner();
    emptyTheTemplatesFor('other');

    test()->post('/companies', storeCompanyPayload(['industry_code' => 'other']));
    $company = Company::where('name', 'like', 'Bootstrap Atomicity Co%')->sole();
    expect($company->bootstrap_incomplete_at)->not->toBeNull();

    // An ordinary page is redirected to the accounts repair page, not rendered.
    $dashboard = test()->get("/{$company->slug}");
    $dashboard->assertRedirect("/{$company->slug}/accounts");
    $dashboard->assertSessionHas('error');

    // The repair page itself, and the repair action, are not blocked.
    test()->get("/{$company->slug}/accounts")->assertOk();
});

test('a successful repair clears the incomplete flag and unblocks the company', function () {
    $f = bootstrapAtomicityOwner();
    emptyTheTemplatesFor('other');

    test()->post('/companies', storeCompanyPayload(['industry_code' => 'other']));
    $company = Company::where('name', 'like', 'Bootstrap Atomicity Co%')->sole();
    $pack = IndustryCoaPack::where('code', 'other')->sole();

    // Seed a real template for this pack now, as an operator fixing the data would, then
    // run the existing repair action.
    DB::table('acct.industry_coa_templates')->insert([
        'id' => (string) Str::uuid(),
        'industry_pack_id' => $pack->id,
        'code' => '1100',
        'name' => 'Accounts Receivable',
        'type' => 'asset',
        'subtype' => 'accounts_receivable',
        'normal_balance' => 'debit',
        'is_system' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $repair = test()->post("/{$company->slug}/accounts/restore-missing");
    $repair->assertSessionHasNoErrors();

    expect($company->fresh()->bootstrap_incomplete_at)->toBeNull();
    test()->get("/{$company->slug}")->assertOk();
});
