<?php

use App\Modules\Accounting\Models\Customer;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\UmrahCoreService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/TicketingFixtures.php';

/**
 * Saving a group with the name left blank names it after its agent.
 *
 * That lookup asked umrah.agents for a "name" column, which the party
 * refactor dropped -- an agent's name lives on its customer now. Every
 * such save threw SQLSTATE[42703] on production. Only blank-named groups
 * reached the code, so it survived a full QA pass.
 */
function defaultNameFixture(): object
{
    // The shared fixture opens one accounting period, September 2026, and
    // a group's sale and cost post on today's date.
    Illuminate\Support\Carbon::setTestNow('2026-09-15 09:00:00');

    $f = ticketingCompany([
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
        'base_currency' => 'SAR',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$f->company->id]);

    foreach ([
        ['1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit'],
        ['2000', 'Accounts Payable', 'liability', 'accounts_payable', 'credit'],
        ['4100', 'Visa Revenue', 'revenue', 'revenue', 'credit'],
        ['5100', 'Visa Cost', 'cogs', 'cogs', 'debit'],
    ] as [$code, $name, $type, $subtype, $normal]) {
        App\Modules\Accounting\Models\Account::firstOrCreate(
            ['company_id' => $f->company->id, 'code' => $code],
            ['name' => $name, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal],
        );
    }

    // The model routes a name given to create() into acct.customers and
    // links it, which is how every caller makes an agent.
    $agent = Agent::create([
        'company_id' => $f->company->id,
        'agent_number' => 'AGT-'.str()->upper(str()->random(5)),
        'name' => 'Karwan Travel Network',
    ]);
    $vendor = VisaVendor::create([
        'company_id' => $f->company->id,
        'vendor_number' => 'VIS-'.str()->upper(str()->random(5)),
        'name' => 'Visa Vendor',
        'service_type' => VisaVendor::SERVICE_VISA_PROVIDER,
        'adult_retail_amount' => 900, 'adult_cost_amount' => 750,
        'child_retail_amount' => 500, 'child_cost_amount' => 400,
        'is_default' => true,
    ]);

    return (object) ['company' => $f->company, 'agent' => $agent, 'vendor' => $vendor];
}

test('a group saved with no name is named after its agent', function () {
    $f = defaultNameFixture();

    $group = app(UmrahCoreService::class)->createGroup($f->company->id, [
        'name' => '',
        'agent_id' => $f->agent->id,
        'vendor_id' => $f->vendor->id,
        'includes_visa' => true,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'passenger_count' => 2,
        'passengers' => [
            ['full_name' => 'First Passenger', 'service_type' => 'visa_transport'],
            ['full_name' => 'Second Passenger', 'service_type' => 'visa_transport'],
        ],
    ]);

    expect($group->name)->toContain('Karwan Travel Network')
        ->and($group->name)->toContain('2 pax');
});

test('a group given a name keeps it', function () {
    $f = defaultNameFixture();

    $group = app(UmrahCoreService::class)->createGroup($f->company->id, [
        'name' => 'Ramadan Group 3',
        'agent_id' => $f->agent->id,
        'vendor_id' => $f->vendor->id,
        'includes_visa' => true,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'passenger_count' => 1,
        'passengers' => [
            ['full_name' => 'Only Passenger', 'service_type' => 'visa_transport'],
        ],
    ]);

    expect($group->name)->toBe('Ramadan Group 3');
});

test('an agent with no linked customer still yields a usable name', function () {
    $f = defaultNameFixture();
    // The migration guarantees a customer, but the column is the only
    // thing standing between this and a group with no name at all.
    Customer::where('id', $f->agent->customer_id)->update(['name' => '']);
    $f->agent->refresh()->unsetRelation('customer');

    $group = app(UmrahCoreService::class)->createGroup($f->company->id, [
        'name' => '',
        'agent_id' => $f->agent->id,
        'vendor_id' => $f->vendor->id,
        'includes_visa' => true,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'passenger_count' => 1,
        'passengers' => [
            ['full_name' => 'Nameless Agent Passenger', 'service_type' => 'visa_transport'],
        ],
    ]);

    expect($group->name)->toContain('Umrah Group');
});

afterEach(function () {
    Illuminate\Support\Carbon::setTestNow();
});
