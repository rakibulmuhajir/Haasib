<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\Voucher;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/*
 * These tests cover the "visa without transport" vocabulary added alongside
 * the visa_groups.transport_mode fix: a self-arranged group (transport_mode
 * 'none') has no bus to sell, so its vouchers must not be allowed to carry a
 * transport-bearing service_bundle, and existing rows written before the
 * vocabulary existed must be corrected, not left lying about a bus that was
 * never booked.
 */

function serviceBundleTestCompany(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Service Bundle Test '.str()->random(8),
        'slug' => 'service-bundle-test-'.str()->lower(str()->random(10)),
        'base_currency' => 'SAR',
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");

    app(CompanyRbacBootstrapper::class)->bootstrap($company);

    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($owner, 'owner'),
    );

    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    return [$company, $owner];
}

function serviceBundleTestGroup(Company $company, string $transportMode): VisaGroup
{
    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-'.strtoupper(str()->random(5)),
        'name' => 'Bundle Test Agent',
    ]);

    return VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'group_number' => 'UGR-'.strtoupper(str()->random(5)),
        'name' => 'Bundle Test Group',
        'status' => VisaGroup::STATUS_VISA_APPROVED,
        'transport_mode' => $transportMode,
        'transport_required' => $transportMode !== VisaGroup::TRANSPORT_NONE,
    ]);
}

function serviceBundleTestPassenger(VisaGroup $group): Passenger
{
    return Passenger::create([
        'company_id' => $group->company_id,
        'visa_group_id' => $group->id,
        'full_name' => 'Bundle Test Passenger',
        'passport_number' => 'P'.strtoupper(str()->random(8)),
        'nationality' => 'PK',
        'service_type' => Passenger::SERVICE_VISA_TRANSPORT,
    ]);
}

function serviceBundleTestPayload(VisaGroup $group, Passenger $passenger, string $serviceBundle): array
{
    return [
        'voucher_number' => 'UVR-'.strtoupper(str()->random(8)),
        'title' => 'Test Voucher',
        'service_bundle' => $serviceBundle,
        'status' => Voucher::STATUS_DRAFT,
        'visa_group_id' => $group->id,
        'passenger_ids' => [$passenger->id],
        'passenger_services' => [$passenger->id => 'visa_transport'],
        'hotel_stays' => [[
            'hotel_name' => null,
            'city' => null,
            'source' => null,
            'hotel_id' => null,
            'room_type' => null,
            'room_count' => null,
            'check_in_date' => null,
            'check_out_date' => null,
            'notes' => null,
        ]],
    ];
}

test('a transport bundle is rejected on a self-arranged group', function () {
    [$company, $owner] = serviceBundleTestCompany();
    $group = serviceBundleTestGroup($company, VisaGroup::TRANSPORT_NONE);
    $passenger = serviceBundleTestPassenger($group);

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/vouchers", serviceBundleTestPayload($group, $passenger, 'visa_transport'))
        ->assertSessionHasErrors('service_bundle');

    expect(Voucher::where('company_id', $company->id)->count())->toBe(0);
});

test('visa and visa_hotel are accepted on a self-arranged group', function () {
    [$company, $owner] = serviceBundleTestCompany();
    $group = serviceBundleTestGroup($company, VisaGroup::TRANSPORT_NONE);

    $passengerOne = serviceBundleTestPassenger($group);
    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/vouchers", serviceBundleTestPayload($group, $passengerOne, 'visa'))
        ->assertSessionHasNoErrors();

    $passengerTwo = serviceBundleTestPassenger($group);
    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/vouchers", serviceBundleTestPayload($group, $passengerTwo, 'visa_hotel'))
        ->assertSessionHasNoErrors();

    expect(Voucher::where('company_id', $company->id)->pluck('service_bundle')->sort()->values()->all())
        ->toBe(['visa', 'visa_hotel']);
});

test('transport bundles still work on a standard_bus group', function () {
    [$company, $owner] = serviceBundleTestCompany();
    $group = serviceBundleTestGroup($company, VisaGroup::TRANSPORT_STANDARD_BUS);
    $passenger = serviceBundleTestPassenger($group);

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/vouchers", serviceBundleTestPayload($group, $passenger, 'visa_transport'))
        ->assertSessionHasNoErrors();

    expect(Voucher::where('company_id', $company->id)->first()->service_bundle)->toBe('visa_transport');
});

test('a standard_bus group can still sell a visa-only voucher', function () {
    [$company, $owner] = serviceBundleTestCompany();
    $group = serviceBundleTestGroup($company, VisaGroup::TRANSPORT_STANDARD_BUS);
    $passenger = serviceBundleTestPassenger($group);

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/vouchers", serviceBundleTestPayload($group, $passenger, 'visa'))
        ->assertSessionHasNoErrors();

    expect(Voucher::where('company_id', $company->id)->first()->service_bundle)->toBe('visa');
});

test('the backfill maps an existing self-arranged voucher from visa_transport to visa', function () {
    [$company] = serviceBundleTestCompany();
    $group = serviceBundleTestGroup($company, VisaGroup::TRANSPORT_NONE);

    $voucher = Voucher::create([
        'company_id' => $company->id,
        'visa_group_id' => $group->id,
        'agent_id' => $group->agent_id,
        'voucher_number' => 'UVR-BACKFILL-1',
        'title' => 'Pre-vocabulary voucher',
        'service_bundle' => 'visa_transport',
        'status' => Voucher::STATUS_DRAFT,
        'hotel_stays' => [],
    ]);

    $hotelOnlyVoucher = Voucher::create([
        'company_id' => $company->id,
        'visa_group_id' => $group->id,
        'agent_id' => $group->agent_id,
        'voucher_number' => 'UVR-BACKFILL-2',
        'title' => 'Pre-vocabulary voucher with hotel',
        'service_bundle' => 'visa_transport_hotel',
        'status' => Voucher::STATUS_DRAFT,
        'hotel_stays' => [],
    ]);

    $busGroup = serviceBundleTestGroup($company, VisaGroup::TRANSPORT_STANDARD_BUS);
    $busVoucher = Voucher::create([
        'company_id' => $company->id,
        'visa_group_id' => $busGroup->id,
        'agent_id' => $busGroup->agent_id,
        'voucher_number' => 'UVR-BACKFILL-3',
        'title' => 'Real bus voucher',
        'service_bundle' => 'visa_transport',
        'status' => Voucher::STATUS_DRAFT,
        'hotel_stays' => [],
    ]);

    (require base_path('modules/Umrah/Database/Migrations/2026_08_20_000002_allow_visa_only_bundles_on_vouchers.php'))->up();

    expect($voucher->fresh()->service_bundle)->toBe('visa')
        ->and($hotelOnlyVoucher->fresh()->service_bundle)->toBe('visa_hotel')
        ->and($busVoucher->fresh()->service_bundle)->toBe('visa_transport');
});
