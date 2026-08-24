<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use App\Modules\Umrah\Services\GroupAccountingService;
use Illuminate\Support\Facades\DB;

test('group accounting exposes anonymous age and service aggregates', function () {
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Anonymous Group Accounting', 'slug' => 'anonymous-group-accounting', 'owner_id' => $user->id, 'base_currency' => 'SAR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $agent = Agent::create(['company_id' => $company->id, 'agent_number' => 'AGT-ACC', 'name' => 'Accounting Agent']);
    $vendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VIS-ACC',
        'name' => 'Accounting Vendor',
        'service_type' => VisaVendor::SERVICE_VISA_PROVIDER,
        'is_default' => true,
        'provides_mandatory_transport' => true,
    ]);
    $group = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'vendor_id' => $vendor->id,
        'mandatory_transport_vendor_id' => $vendor->id,
        'group_number' => 'UGR-ACC',
        'name' => 'Private passenger group',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
        'travel_date' => '2026-08-01',
        'transport_required' => true,
        'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
        'visa_sale_amount' => 300,
        'transport_amount' => 50,
    ]);

    $passengers = collect();
    foreach ([
        ['Adult Person', 'PA-1', 30, Passenger::SERVICE_VISA_TRANSPORT],
        ['Child Person', 'PA-2', 8, Passenger::SERVICE_VISA_TRANSPORT],
        ['Infant Person', 'PA-3', 1, Passenger::SERVICE_TRANSPORT_ONLY],
    ] as [$name, $passport, $age, $service]) {
        $passengers->push(Passenger::create([
            'company_id' => $company->id,
            'visa_group_id' => $group->id,
            'full_name' => $name,
            'passport_number' => $passport,
            'imported_age' => $age,
            'service_type' => $service,
        ]));
    }

    $voucher = Voucher::create([
        'company_id' => $company->id,
        'visa_group_id' => $group->id,
        'agent_id' => $agent->id,
        'voucher_number' => 'UVR-ACC',
        'title' => 'Private itinerary',
        'service_bundle' => Voucher::SERVICE_VISA_TRANSPORT_HOTEL,
        'status' => Voucher::STATUS_DRAFT,
        'hotel_stays' => [
            ['source' => 'company', 'hotel_name' => 'Private Hotel', 'total_retail_amount' => 200, 'total_cost_amount' => 150],
        ],
        'hotel_sale_amount' => 200,
        'hotel_cost_amount' => 150,
    ]);
    foreach ($passengers as $passenger) {
        VoucherPassenger::create([
            'company_id' => $company->id,
            'voucher_id' => $voucher->id,
            'visa_group_id' => $group->id,
            'passenger_id' => $passenger->id,
        ]);
    }

    $summary = app(GroupAccountingService::class)->summary($group);
    $voucherSummary = app(GroupAccountingService::class)->voucherSummary($voucher);
    $encoded = json_encode($summary);
    $encodedVoucher = json_encode($voucherSummary);

    expect($summary['passengerSummary'])->toMatchArray([
        'total' => 3,
        'adults' => 1,
        'children' => 1,
        'infants' => 1,
        // The group includes a visa, so all three passengers take it. The
        // third row still says transport_only -- written before the group
        // level choice existed -- and is no longer allowed to disagree with
        // the group it sits in.
        'visa' => 3,
        'transport_only' => 0,
    ])->and($summary['voucherBreakdown'][0])->toMatchArray([
        'voucher_number' => 'UVR-ACC',
        'passengers' => 3,
        'accounting_state' => 'pending',
    ])->and($voucherSummary['groupPosting'])->toMatchArray([
        'visa_sale_amount' => 300.0,
        'transport_amount' => 50.0,
        'revenue' => 350.0,
    ])->and($voucherSummary['voucherPosting'])->toMatchArray([
        'hotel_sale_amount' => 200.0,
        'hotel_cost_amount' => 150.0,
        'accounting_state' => 'pending',
    ])->and($encoded)->not->toContain('Adult Person')
        ->and($encoded)->not->toContain('PA-1')
        ->and($encodedVoucher)->not->toContain('Adult Person')
        ->and($encodedVoucher)->not->toContain('PA-1');
});

test('a transport-only group is charged no visa', function () {
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Transport Only Accounting', 'slug' => 'transport-only-accounting', 'owner_id' => $user->id, 'base_currency' => 'SAR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $agent = Agent::create(['company_id' => $company->id, 'agent_number' => 'AGT-TON', 'name' => 'Transport Only Agent']);

    // visa_sale_amount carries a figure the group must not be billed for.
    // Everyone here already holds a visa; the group sells them a coach.
    $group = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-TON',
        'name' => 'Already holds a visa',
        'includes_visa' => false,
        'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
        'visa_sale_amount' => 300,
    ]);

    Passenger::create([
        'company_id' => $company->id,
        'visa_group_id' => $group->id,
        'full_name' => 'Coach Rider',
        'passport_number' => 'TON-1',
        'imported_age' => 30,
        'service_type' => Passenger::SERVICE_TRANSPORT_ONLY,
    ]);

    $summary = app(GroupAccountingService::class)->summary($group);

    expect(collect($summary['services'])->pluck('service')->all())->toBe([])
        ->and($summary['passengerSummary'])->toMatchArray(['visa' => 0, 'transport_only' => 1]);
});

test('a visa group with no passengers entered yet is charged no visa', function () {
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Empty Group Accounting', 'slug' => 'empty-group-accounting', 'owner_id' => $user->id, 'base_currency' => 'SAR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $agent = Agent::create(['company_id' => $company->id, 'agent_number' => 'AGT-EMP', 'name' => 'Empty Group Agent']);

    $group = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-EMP',
        'name' => 'Nobody entered yet',
        'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
        'visa_sale_amount' => 300,
    ]);

    // The visa line is priced per head. No heads, no line -- a group that
    // has been named but not filled bills nothing.
    expect(app(GroupAccountingService::class)->summary($group)["services"]->all())->toBe([]);
});
