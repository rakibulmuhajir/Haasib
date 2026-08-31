<?php

use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\TravelReportService;
use App\Modules\Umrah\Services\UmrahCoreService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/TicketingFixtures.php';

/**
 * A company charges the agent up front: by the time a group exists the
 * visas are issued and the transport is paid for, and createGroup() posts
 * the sale and the cost to that day's ledger.
 *
 * The financial reports used to window on the travel date, so a group sold
 * in September for a December trip sat in September's books and December's
 * profit report at the same time.
 */
function profitabilityFixture(?string $travelDate): object
{
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

    $agent = Agent::create([
        'company_id' => $f->company->id,
        'agent_number' => 'AGT-'.str()->upper(str()->random(5)),
        'name' => 'Profitability Agent',
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

    $group = app(UmrahCoreService::class)->createGroup($f->company->id, [
        'name' => 'Sold in September',
        'agent_id' => $agent->id,
        'vendor_id' => $vendor->id,
        'includes_visa' => true,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'travel_date' => $travelDate ?: null,
        'passenger_count' => 2,
        'passengers' => [
            ['full_name' => 'First Passenger', 'service_type' => 'visa_transport'],
            ['full_name' => 'Second Passenger', 'service_type' => 'visa_transport'],
        ],
    ]);

    return (object) ['company' => $f->company, 'user' => $f->user, 'group' => $group];
}

function profitabilityRows(object $f, string $start, string $end): array
{
    return app(TravelReportService::class)->build(
        $f->company,
        $f->user,
        'group-profitability',
        ['start' => $start, 'end' => $end],
        false,
    )['rows'];
}

test('a group sold this month is on this month profit report, whenever it travels', function () {
    // Sold 15 September, travelling 20 December.
    $f = profitabilityFixture('2026-12-20');

    $rows = profitabilityRows($f, '2026-09-01', '2026-09-30');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['group'])->toBe($f->group->group_number);
});

test('it is not counted again in the month it travels', function () {
    $f = profitabilityFixture('2026-12-20');

    expect(profitabilityRows($f, '2026-12-01', '2026-12-31'))->toHaveCount(0);
});

test('a group with no travel date is still on the profit report', function () {
    // It used to be dropped at every date range, because the report could
    // not work out a service date for it.
    $f = profitabilityFixture(null);

    expect(profitabilityRows($f, '2026-09-01', '2026-09-30'))->toHaveCount(1);
});

afterEach(function () {
    Illuminate\Support\Carbon::setTestNow();
});
