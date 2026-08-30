<?php

use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use App\Modules\Umrah\Services\VoucherWorkflowService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/TicketingFixtures.php';

/**
 * Two rules that made a voucher's own lifecycle impossible to finish.
 */
function amendmentFixture(string $bundle = Voucher::SERVICE_VISA_TRANSPORT): object
{
    $f = ticketingCompany([
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
        'base_currency' => 'SAR',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$f->company->id]);

    $agent = App\Modules\Umrah\Models\Agent::create([
        'company_id' => $f->company->id,
        'agent_number' => 'AGT-'.str()->upper(str()->random(5)),
        'name' => 'Amendment Agent',
    ]);
    $vendor = App\Modules\Umrah\Models\VisaVendor::create([
        'company_id' => $f->company->id,
        'vendor_number' => 'VIS-'.str()->upper(str()->random(5)),
        'name' => 'Visa Vendor',
        'service_type' => App\Modules\Umrah\Models\VisaVendor::SERVICE_VISA_PROVIDER,
    ]);
    $group = App\Modules\Umrah\Models\VisaGroup::create([
        'company_id' => $f->company->id,
        'agent_id' => $agent->id,
        'vendor_id' => $vendor->id,
        'group_number' => 'UGR-'.str()->upper(str()->random(5)),
        'name' => 'Amendment group',
        'status' => App\Modules\Umrah\Models\VisaGroup::STATUS_VISA_APPROVED,
        'transport_mode' => App\Modules\Umrah\Models\VisaGroup::TRANSPORT_STANDARD_BUS,
        'travel_date' => now()->addDays(40)->toDateString(),
    ]);
    $passenger = Passenger::create([
        'company_id' => $f->company->id,
        'visa_group_id' => $group->id,
        'full_name' => 'Amendment Passenger',
        'service_type' => Passenger::SERVICE_VISA_TRANSPORT,
    ]);
    $voucher = Voucher::create([
        'company_id' => $f->company->id,
        'visa_group_id' => $group->id,
        'agent_id' => $agent->id,
        'voucher_number' => 'UVR-'.str()->upper(str()->random(5)),
        'title' => 'Amendment voucher',
        'service_bundle' => $bundle,
        'status' => Voucher::STATUS_APPROVED,
        'hotel_stays' => [],
        'onward_airline' => 'SV', 'onward_flight_number' => '700',
        'onward_departure_city' => 'LHE', 'onward_arrival_city' => 'JED',
        'onward_departure_at' => now()->addDays(40)->setTime(13, 0),
        'onward_arrival_at' => now()->addDays(40)->setTime(17, 0),
        'return_airline' => 'SV', 'return_flight_number' => '701',
        'return_departure_city' => 'JED', 'return_arrival_city' => 'LHE',
        'return_departure_at' => now()->addDays(50)->setTime(18, 0),
        'return_arrival_at' => now()->addDays(50)->setTime(22, 0),
    ]);
    VoucherPassenger::create([
        'company_id' => $f->company->id,
        'voucher_id' => $voucher->id,
        'visa_group_id' => $group->id,
        'passenger_id' => $passenger->id,
    ]);

    return (object) ['company' => $f->company, 'voucher' => $voucher, 'passenger' => $passenger];
}

test('an approved voucher can be amended', function () {
    // A passenger's assignment is soft-deleted and written again against
    // the new draft. A second, non-partial unique index counted the
    // soft-deleted row, so every amendment died on a duplicate key.
    $f = amendmentFixture();

    $amendment = app(VoucherWorkflowService::class)
        ->createAmendment($f->voucher, 'UVR-AMEND', null);

    expect($amendment->status)->toBe(Voucher::STATUS_DRAFT)
        ->and($amendment->amends_voucher_id)->toBe($f->voucher->id)
        ->and($amendment->version_number)->toBe(2);
});

test('the amendment carries the passengers across', function () {
    $f = amendmentFixture();

    $amendment = app(VoucherWorkflowService::class)
        ->createAmendment($f->voucher, 'UVR-AMEND2', null);

    $live = VoucherPassenger::where('voucher_id', $amendment->id)->pluck('passenger_id');

    expect($live)->toHaveCount(1)
        ->and($live->first())->toBe($f->passenger->id);
});

test('a passenger still cannot hold two live assignments in one group', function () {
    // The partial index is the rule now, and it still has to bite.
    $f = amendmentFixture();

    expect(fn () => VoucherPassenger::create([
        'company_id' => $f->company->id,
        'voucher_id' => $f->voucher->id,
        'visa_group_id' => $f->voucher->visa_group_id,
        'passenger_id' => $f->passenger->id,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

test('a bundle that sells no hotel needs no hotel stay to be approved', function () {
    // Visa + Transport could never be approved: the completeness check
    // demanded a stay for every bundle, and the form offers none for this
    // one.
    $f = amendmentFixture(Voucher::SERVICE_VISA_TRANSPORT);

    $request = new App\Modules\Umrah\Http\Requests\ApproveVoucherRequest;
    $method = new ReflectionMethod($request, 'hasCompleteItinerary');

    expect($method->invoke($request, $f->voucher))->toBeTrue();
});

test('a bundle that does sell a hotel still needs its stay', function () {
    $f = amendmentFixture(Voucher::SERVICE_VISA_TRANSPORT_HOTEL);

    $request = new App\Modules\Umrah\Http\Requests\ApproveVoucherRequest;
    $method = new ReflectionMethod($request, 'hasCompleteItinerary');

    expect($method->invoke($request, $f->voucher))->toBeFalse();
});
