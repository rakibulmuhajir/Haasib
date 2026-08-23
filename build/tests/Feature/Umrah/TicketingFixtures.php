<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Umrah\Models\Agent;
use Illuminate\Support\Facades\DB;

/**
 * Shared fixture builders for the ticketing (umrah) domain tests. Every
 * name here is prefixed `ticketing` because Pest top-level helpers are
 * global across the whole suite -- an unprefixed name would collide with
 * an existing accounting-domain helper rather than shadow it.
 *
 * Copies the shape of billPaymentTestFixture() in
 * tests/Feature/Accounting/BillPaymentPostingTest.php: company creation
 * seeds nothing, so every fixture that will post must also open a fiscal
 * year and an accounting period covering the dates this plan uses
 * (September 2026).
 */
function ticketingCompany(array $overrides = []): object
{
    $user = User::factory()->create();

    $company = Company::create(array_merge([
        'name' => 'Ticketing Test',
        'slug' => 'ticketing-test-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ], $overrides));

    if (! DB::table('public.currencies')->where('code', $company->base_currency)->exists()) {
        DB::table('public.currencies')->insert([
            'code' => $company->base_currency,
            'name' => 'Pakistan Rupee',
            'symbol' => 'Rs',
        ]);
    }

    // Postings are refused outside an open period. Every test in this
    // plan posts, so every fixture needs both of these.
    $fiscalYear = FiscalYear::create([
        'company_id' => $company->id,
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'open',
    ]);

    AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fiscalYear->id,
        'name' => 'Sep 2026',
        'period_number' => 9,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]);

    return (object) [
        'company' => $company,
        'user' => $user,
        'fiscalYear' => $fiscalYear,
    ];
}

function ticketingCustomer(Company $company, array $overrides = []): Customer
{
    return Customer::create(array_merge([
        'company_id' => $company->id,
        'customer_number' => 'CUST-'.str()->upper(str()->random(8)),
        'name' => 'Test Customer',
        'base_currency' => $company->base_currency,
    ], $overrides));
}

function ticketingAgent(Company $company, array $overrides = []): Agent
{
    return Agent::create(array_merge([
        'company_id' => $company->id,
        'agent_number' => 'AGT-'.str()->upper(str()->random(8)),
        'name' => 'Test Agent',
    ], $overrides));
}
