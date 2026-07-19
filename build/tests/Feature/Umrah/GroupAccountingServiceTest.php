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
        'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER,
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
        'visa' => 2,
        'transport_only' => 1,
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
