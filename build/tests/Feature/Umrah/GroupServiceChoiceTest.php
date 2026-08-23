<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Http\Requests\StoreVisaGroupRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\TransportSector;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use Illuminate\Support\Facades\DB;

function makeVisaGroupCompany(string $suffix): array
{
    $user = User::factory()->create();
    $company = Company::create([
        'name' => "Group Service Choice Test {$suffix}",
        'slug' => 'group-service-choice-test-'.$suffix,
        'owner_id' => $user->id,
        'base_currency' => 'SAR',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => "AGT-GSC-{$suffix}",
        'name' => 'Group Service Choice Agent',
    ]);

    return [$company, $agent];
}

it('defaults an existing group to including a visa', function () {
    [$company, $agent] = makeVisaGroupCompany('a');

    $group = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-GSC-A',
        'name' => 'Visa default group',
        'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
    ]);

    expect($group->fresh()->includes_visa)->toBeTrue();
});

it('offers no visa statuses to a transport-only group', function () {
    expect(array_keys(VisaGroup::statusesAvailable(false)))
        ->toBe(['draft', 'delivered', 'closed', 'cancelled']);

    expect(array_keys(VisaGroup::statusesAvailable(true)))
        ->toBe(array_keys(VisaGroup::STATUSES));
});

it('refuses a group that is neither visa nor transport', function () {
    [$company, $agent] = makeVisaGroupCompany('b');

    expect(fn () => VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-GSC-B',
        'name' => 'Neither visa nor transport group',
        'includes_visa' => false,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('refuses to park a transport-only group in a visa status', function () {
    [$company, $agent] = makeVisaGroupCompany('c');

    // statusesAvailable() decides what the UI offers; this is the half that
    // decides what the record may hold. Without it a transport-only group
    // could still be written straight to 'submitted' by any path that
    // bypasses the picker -- an import, a console command, a later feature.
    expect(fn () => VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-GSC-C',
        'name' => 'Transport-only group in a visa status',
        'includes_visa' => false,
        'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
        'status' => VisaGroup::STATUS_SUBMITTED,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

/**
 * A fare whose vehicle seats $capacity people, or states no capacity at all
 * when $capacity is null.
 */
function fareSeating(Company $company, ?int $capacity, string $suffix): TransportFare
{
    $vendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => "TRN-GSC-{$suffix}",
        'name' => "Seat Test Provider {$suffix}",
        'vendor_type' => VisaVendor::TYPE_TRANSPORT_PROVIDER,
    ]);

    $service = TransportService::create([
        'company_id' => $company->id,
        'name' => "Seat Test Coach {$suffix}",
        'vehicle_type' => 'Bus',
        'pax_capacity' => $capacity,
        'is_active' => true,
    ]);

    // transport_fares_target_check: a fare must name the sector or package
    // it prices. One without either prices nothing.
    $sector = TransportSector::create([
        'company_id' => $company->id,
        'code' => "GSC-{$suffix}",
        'name' => "Seat Test Sector {$suffix}",
        'origin' => 'Jeddah',
        'destination' => 'Makkah',
        'is_active' => true,
    ]);

    return TransportFare::create([
        'company_id' => $company->id,
        'transport_vendor_id' => $vendor->id,
        'transport_service_id' => $service->id,
        'transport_sector_id' => $sector->id,
        'name' => "Seat Test Fare {$suffix}",
        'charging_basis' => TransportFare::BASIS_PER_VEHICLE,
        'sale_amount' => 100,
        'cost_amount' => 80,
        'is_active' => true,
    ]);
}

function seatedQuantityFor(array $item): int
{
    $request = StoreVisaGroupRequest::create('/travel/umrah/groups', 'POST', [
        'includes_visa' => '1',
        'transport_mode' => 'specialized',
        'transport_items' => [$item],
    ]);

    (fn () => $this->prepareForValidation())->call($request);

    return (int) $request->input('transport_items.0.quantity');
}

it('raises the vehicle count when passengers exceed the seats booked', function () {
    [$company] = makeVisaGroupCompany('seats');
    $fare = fareSeating($company, 20, 'seats');

    // 45 passengers at 20 seats a vehicle is three vehicles, not the one asked for.
    expect(seatedQuantityFor([
        'transport_fare_id' => $fare->id,
        'quantity' => 1,
        'passenger_count' => 45,
    ]))->toBe(3);
});

it('leaves a deliberately generous vehicle count alone', function () {
    [$company] = makeVisaGroupCompany('generous');
    $fare = fareSeating($company, 20, 'generous');

    // Seats cover the passengers twice over; the operator had a reason.
    expect(seatedQuantityFor([
        'transport_fare_id' => $fare->id,
        'quantity' => 4,
        'passenger_count' => 20,
    ]))->toBe(4);
});

it('leaves the vehicle count alone when the vehicle states no capacity', function () {
    [$company] = makeVisaGroupCompany('nocap');
    $fare = fareSeating($company, null, 'nocap');

    expect(seatedQuantityFor([
        'transport_fare_id' => $fare->id,
        'quantity' => 1,
        'passenger_count' => 45,
    ]))->toBe(1);
});

it('does not fail on a fare id that is not a uuid', function () {
    makeVisaGroupCompany('badid');

    // Runs before validation has judged transport_fare_id. Comparing a uuid
    // column against a non-uuid string raises in Postgres, which would turn
    // a bad payload into a 500 rather than a 422.
    expect(seatedQuantityFor([
        'transport_fare_id' => 'not-a-uuid',
        'quantity' => 2,
        'passenger_count' => 45,
    ]))->toBe(2);
});
