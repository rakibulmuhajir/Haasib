<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;

/**
 * The COA pack applies at company creation, so a template-only change
 * leaves every existing company without these accounts and every ticket
 * posting failing on a missing role mapping.
 *
 * Company::factory() does not exist in this codebase (Company has no
 * HasFactory trait) and COA seeding is not triggered by Company::create() --
 * it only happens through CompanyOnboardingService::setupCompanyIdentity(),
 * called explicitly during onboarding. So a company created in a test is,
 * by construction, a company that existed before the backfill migration
 * ran (RefreshDatabase migrates the schema before any test creates a row).
 * We reproduce that by creating a bare company and then re-running the
 * migration's up() against it, exactly as it would run against a real
 * pre-existing company in production.
 */
function ticketAccountsCompany(): Company
{
    return Company::create([
        'name' => 'Ticket Accounts Co '.str()->random(8),
        'slug' => 'ticket-accounts-'.str()->lower(str()->random(10)),
        'base_currency' => 'USD',
    ]);
}

function runTicketAccountsBackfillMigration(): void
{
    $migration = require database_path('migrations/2026_08_23_000001_add_ticket_accounts_to_existing_companies.php');
    $migration->up();
}

it('gives an existing umrah company the six ticket accounts', function () {
    $company = ticketAccountsCompany();
    runTicketAccountsBackfillMigration();

    foreach (['2350', '4130', '4140', '4150', '4160', '9900'] as $code) {
        expect(Account::where('company_id', $company->id)->where('code', $code)->exists())
            ->toBeTrue("account {$code} is missing");
    }
});

it('makes the ticket discount account a contra-revenue account', function () {
    $company = ticketAccountsCompany();
    runTicketAccountsBackfillMigration();

    $discount = Account::where('company_id', $company->id)->where('code', '4150')->first();

    expect($discount->type)->toBe('revenue')
        ->and($discount->normal_balance)->toBe('debit')
        ->and($discount->is_contra)->toBeTrue();
});

it('leaves ticket revenue accounts on the base currency', function () {
    $company = ticketAccountsCompany();
    runTicketAccountsBackfillMigration();

    foreach (['4130', '4140', '4150', '4160'] as $code) {
        $account = Account::where('company_id', $company->id)->where('code', $code)->first();
        expect($account->currency)->toBeNull("account {$code} must be base-currency only");
    }
});
