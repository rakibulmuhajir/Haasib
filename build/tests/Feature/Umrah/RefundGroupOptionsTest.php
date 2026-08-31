<?php

use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/TicketingFixtures.php';

/**
 * A refund attached to a group reverses enough of that group's allocations
 * to cover itself, so the group's balance goes back up. Without the group
 * the money only comes off the agent's advance and the group still reads
 * as fully paid for money that was handed back.
 *
 * The picker used to read the payment allocation options, which carry a
 * group only while it still owes something. A refund is usually raised
 * after the agent has paid, so the one group you needed was the one that
 * had just dropped off the list.
 */
function refundOptionsFixture(): object
{
    $f = ticketingCompany([
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
        'base_currency' => 'SAR',
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$f->user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($f->company);
    ticketingAddCompanyMember($f->company, $f->user, 'owner');
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$f->company->id]);

    $agent = Agent::create([
        'company_id' => $f->company->id,
        'agent_number' => 'AGT-'.str()->upper(str()->random(5)),
        'name' => 'Refund Options Agent',
    ]);
    $vendor = VisaVendor::create([
        'company_id' => $f->company->id,
        'vendor_number' => 'VIS-'.str()->upper(str()->random(5)),
        'name' => 'Visa Vendor',
        'service_type' => VisaVendor::SERVICE_VISA_PROVIDER,
    ]);

    $make = fn (string $suffix, string $mode, float $balance, float $hotel) => VisaGroup::create([
        'company_id' => $f->company->id,
        'agent_id' => $agent->id,
        'vendor_id' => $vendor->id,
        'group_number' => 'UGR-'.$suffix,
        'name' => 'Group '.$suffix,
        'status' => VisaGroup::STATUS_VISA_APPROVED,
        'transport_mode' => $mode,
        'transport_required' => $mode !== VisaGroup::TRANSPORT_NONE,
        'visa_sale_amount' => 1000,
        'total_receivable' => 1000,
        'total_paid' => 1000 - $balance,
        'balance' => $balance,
        'hotel_amount' => $hotel,
    ]);

    return (object) [
        'company' => $f->company,
        'user' => $f->user,
        'agent' => $agent,
        'settled' => $make('SETTLED', VisaGroup::TRANSPORT_STANDARD_BUS, 0, 0),
        'owing' => $make('OWING', VisaGroup::TRANSPORT_NONE, 250, 0),
        'hotel' => $make('HOTEL', VisaGroup::TRANSPORT_NONE, 0, 4800),
    ];
}

function refundGroupOptions(object $f): array
{
    $props = [];

    test()->actingAs($f->user)
        ->get("/{$f->company->slug}/umrah/refunds/create")
        ->assertOk()
        ->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray()['props']['refundGroups'] ?? [];

            return $page;
        });

    return $props;
}

test('a fully paid group is still offered for a refund', function () {
    // The bug: this is exactly the group you would refund, and it was the
    // one the picker dropped.
    $f = refundOptionsFixture();

    $numbers = collect(refundGroupOptions($f))->pluck('group_number');

    expect($numbers)->toContain('UGR-SETTLED');
});

test('a group that still owes money is offered too', function () {
    $f = refundOptionsFixture();

    expect(collect(refundGroupOptions($f))->pluck('group_number'))
        ->toContain('UGR-OWING');
});

test('each group says which services it bought', function () {
    // The form narrows by these, so a transport refund only offers groups
    // that actually had transport.
    $f = refundOptionsFixture();

    $rows = collect(refundGroupOptions($f))->keyBy('group_number');

    expect($rows['UGR-SETTLED']['has_transport'])->toBeTrue()
        ->and($rows['UGR-OWING']['has_transport'])->toBeFalse()
        ->and($rows['UGR-HOTEL']['has_hotel'])->toBeTrue()
        ->and($rows['UGR-SETTLED']['has_hotel'])->toBeFalse();
});

test('a cancelled group is not offered', function () {
    $f = refundOptionsFixture();
    $f->owing->update(['status' => VisaGroup::STATUS_CANCELLED]);

    expect(collect(refundGroupOptions($f))->pluck('group_number'))
        ->not->toContain('UGR-OWING');
});
