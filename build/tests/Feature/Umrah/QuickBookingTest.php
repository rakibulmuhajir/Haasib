<?php

use App\Facades\CompanyContext;
use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\CommercialRate;
use App\Modules\Umrah\Models\GroupTransportItem;
use App\Modules\Umrah\Models\Hotel;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\TransportSector;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Models\Voucher;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/TicketingFixtures.php';

function quickBookingAddMember(Company $company, User $user, string $role): void
{
    addCompanyMemberRow($company, $user, $role);

    CompanyContext::withContext($company, fn () => CompanyContext::assignRole($user, $role));
}

function quickBookingFixture(): object
{
    Carbon::setTestNow('2026-09-15 10:00:00');
    $base = ticketingCompany([
        'name' => 'Quick Booking Test',
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
        'base_currency' => 'SAR',
    ]);
    $company = $base->company;
    $owner = $base->user;
    $operations = User::factory()->withoutTwoFactor()->create();
    $outsider = User::factory()->withoutTwoFactor()->create();
    $agentUser = User::factory()->withoutTwoFactor()->create();

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    quickBookingAddMember($company, $owner, 'owner');
    quickBookingAddMember($company, $operations, 'operations');
    quickBookingAddMember($company, $agentUser, 'agent');
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    CompanyContext::setContext($company);

    foreach ([
        ['1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit'],
        ['2000', 'Accounts Payable', 'liability', 'accounts_payable', 'credit'],
        ['4100', 'Visa Revenue', 'revenue', 'revenue', 'credit'],
        ['4110', 'Transport Revenue', 'revenue', 'revenue', 'credit'],
        ['5100', 'Visa Cost', 'cogs', 'cogs', 'debit'],
        ['5110', 'Transport Cost', 'cogs', 'cogs', 'debit'],
    ] as [$code, $name, $type, $subtype, $normal]) {
        Account::firstOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            ['name' => $name, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal],
        );
    }

    $agent = Agent::create([
        'company_id' => $company->id,
        'user_id' => $agentUser->id,
        'agent_number' => 'AGT-QB',
        'name' => 'Quick Booking Agent',
        'country' => 'Pakistan',
        'can_create_voucher' => false,
    ]);
    $otherAgent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-QB-OTHER',
        'name' => 'Other Booking Agent',
        'country' => 'Pakistan',
    ]);
    $transportProvider = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'TRN-QB',
        'name' => 'Quick Bus Supplier',
        'service_type' => VisaVendor::SERVICE_TRANSPORT_PROVIDER,
        'standard_bus_retail_amount' => 120,
        'standard_bus_cost_amount' => 80,
        'charge_child_fare' => false,
        'is_active' => true,
    ]);
    $visaVendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VIS-QB',
        'name' => 'Quick Visa Supplier',
        'service_type' => VisaVendor::SERVICE_VISA_PROVIDER,
        'adult_retail_amount' => 900,
        'adult_cost_amount' => 750,
        'child_retail_amount' => 500,
        'child_cost_amount' => 400,
        'mandatory_transport_vendor_id' => $transportProvider->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $service = TransportService::create([
        'company_id' => $company->id,
        'name' => 'Twenty Seat Coach',
        'vehicle_type' => 'Coach',
        'pax_capacity' => 20,
        'is_active' => true,
    ]);
    $sector = TransportSector::create([
        'company_id' => $company->id,
        'code' => 'JED-MAK-QB',
        'name' => 'Jeddah Airport to Makkah',
        'origin' => 'Jeddah Airport',
        'destination' => 'Makkah',
        'is_active' => true,
    ]);
    $fare = TransportFare::create([
        'company_id' => $company->id,
        'transport_vendor_id' => $transportProvider->id,
        'transport_service_id' => $service->id,
        'transport_sector_id' => $sector->id,
        'name' => 'Airport coach',
        'charging_basis' => TransportFare::BASIS_PER_VEHICLE,
        'sale_amount' => 300,
        'cost_amount' => 200,
        'hajj_terminal_sale_amount' => 50,
        'hajj_terminal_cost_amount' => 30,
        'is_active' => true,
    ]);
    $hotelVendor = HotelVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'HTL-QB',
        'name' => 'Quick Hotel Supplier',
        'is_active' => true,
    ]);
    $makkahHotel = Hotel::create([
        'company_id' => $company->id,
        'hotel_vendor_id' => $hotelVendor->id,
        'name' => 'Makkah Test Hotel',
        'city' => 'Makkah',
        'is_active' => true,
    ]);
    $madinahHotel = Hotel::create([
        'company_id' => $company->id,
        'hotel_vendor_id' => $hotelVendor->id,
        'name' => 'Madinah Test Hotel',
        'city' => 'Madinah',
        'is_active' => true,
    ]);

    return (object) compact(
        'company',
        'owner',
        'operations',
        'outsider',
        'agentUser',
        'agent',
        'otherAgent',
        'visaVendor',
        'transportProvider',
        'fare',
        'makkahHotel',
        'madinahHotel',
    );
}

function quickBookingPayload(object $fixture, array $overrides = []): array
{
    return array_replace([
        'service_mode' => 'visa_transport',
        'next_step' => 'group',
        'idempotency_key' => (string) Str::uuid(),
        'group_number' => 'QB-'.str()->upper(str()->random(8)),
        'name' => 'Quick family booking',
        'agent_id' => $fixture->agent->id,
        'travel_date' => '2026-09-25',
        'passenger_count' => 1,
        'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
        'hotel_makkah_id' => null,
        'hotel_madinah_id' => null,
        'room_type' => 'double',
        'makkah_nights' => 0,
        'madinah_nights' => 0,
        'notes' => null,
        'passengers' => [[
            'full_name' => 'Adult Traveller',
            'passport_number' => 'QB100001',
            'nationality' => 'Pakistan',
            'imported_age' => 30,
            'visa_status' => Passenger::STATUS_RECEIVED,
        ]],
        'transport_items' => [],
    ], $overrides);
}

test('quick booking saves commercial visa and bus prices and ignores submitted price tampering', function () {
    $f = quickBookingFixture();
    foreach ([['visa_adult', $f->visaVendor->id, 1100, 700], ['standard_transport', $f->transportProvider->id, 200, 90]] as [$service, $vendorId, $sale, $cost]) {
        CommercialRate::create([
            'company_id' => $f->company->id, 'service_type' => $service, 'visa_vendor_id' => $vendorId,
            'scope_type' => 'default', 'calculation_type' => 'set_price', 'amount' => $sale, 'cost_amount' => $cost,
            'currency' => 'SAR', 'effective_from' => '2026-09-01', 'is_active' => true,
        ]);
        CommercialRate::create([
            'company_id' => $f->company->id, 'service_type' => $service, 'visa_vendor_id' => $vendorId,
            'scope_type' => 'agent', 'agent_id' => $f->agent->id,
            'calculation_type' => 'discount_percentage', 'percentage' => 10,
            'currency' => 'SAR', 'effective_from' => '2026-09-01', 'is_active' => true,
        ]);
    }
    $payload = quickBookingPayload($f, ['visa_sale_amount' => 1, 'transport_sale_amount' => 1, 'visa_cost_amount' => 1]);
    $this->actingAs($f->owner)->post("/{$f->company->slug}/umrah/quick-booking", $payload)
        ->assertRedirect()->assertSessionHasNoErrors();
    $group = VisaGroup::where('company_id', $f->company->id)->where('group_number', $payload['group_number'])->firstOrFail();
    expect((float) $group->visa_sale_amount)->toBe(990.0)
        ->and((float) $group->visa_cost_amount)->toBe(700.0)
        ->and((float) $group->transport_amount)->toBe(180.0)
        ->and((float) $group->transport_cost_amount)->toBe(90.0)
        ->and(data_get($group->pricing_snapshot, 'visa.adult.source'))->toBe('agent');
});

test('specialized quick booking saves dated commercial fare prices with the vehicle quantity', function () {
    $f = quickBookingFixture();
    CommercialRate::create([
        'company_id' => $f->company->id, 'service_type' => 'transport_fare', 'transport_fare_id' => $f->fare->id,
        'scope_type' => 'default', 'calculation_type' => 'set_price', 'amount' => 500, 'cost_amount' => 250,
        'currency' => 'SAR', 'effective_from' => '2026-09-01', 'is_active' => true,
    ]);
    CommercialRate::create([
        'company_id' => $f->company->id, 'service_type' => 'transport_fare', 'transport_fare_id' => $f->fare->id,
        'scope_type' => 'agent', 'agent_id' => $f->agent->id,
        'calculation_type' => 'discount_amount', 'amount' => 50,
        'currency' => 'SAR', 'effective_from' => '2026-09-01', 'is_active' => true,
    ]);
    $payload = quickBookingPayload($f, [
        'service_mode' => 'transport', 'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
        'transport_items' => [['transport_fare_id' => $f->fare->id, 'quantity' => 2, 'terminal' => 'standard']],
    ]);
    $this->actingAs($f->owner)->post("/{$f->company->slug}/umrah/quick-booking", $payload)
        ->assertRedirect()->assertSessionHasNoErrors();
    $group = VisaGroup::where('company_id', $f->company->id)->where('group_number', $payload['group_number'])->firstOrFail();
    expect((float) $group->visa_sale_amount)->toBe(0.0)
        ->and((float) $group->transport_amount)->toBe(900.0)
        ->and((float) $group->transport_cost_amount)->toBe(500.0);
});

test('quick booking page is role protected and never exposes supplier costs', function () {
    $f = quickBookingFixture();
    CompanyContext::setContext($f->company);

    $response = $this->actingAs($f->owner)
        ->get("/{$f->company->slug}/umrah/quick-booking")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Umrah/QuickBooking/Create')
            ->has('agents', 2)
            ->where('agents.1.name', 'Quick Booking Agent')
            ->where('pricing.visa.adult', 900)
            ->where('pricing.standard_transport.per_passenger', 120)
            ->where('transportFares.0.sale_amount', 300)
        );

    expect(json_encode($response->viewData('page')['props']))->not->toContain('cost_amount');

    $this->actingAs($f->outsider)
        ->get("/{$f->company->slug}/umrah/quick-booking")
        ->assertRedirect();
});

test('visa only booking prices passengers and does not invent transport or hotel', function () {
    $f = quickBookingFixture();
    CompanyContext::setContext($f->company);
    $payload = quickBookingPayload($f, [
        'service_mode' => 'visa',
        'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
    ]);

    $this->actingAs($f->operations)
        ->post("/{$f->company->slug}/umrah/quick-booking", $payload)
        ->assertRedirect();

    $group = VisaGroup::where('idempotency_key', $payload['idempotency_key'])->firstOrFail();
    expect($group->includes_visa)->toBeTrue()
        ->and($group->includes_hotel)->toBeFalse()
        ->and($group->transport_mode)->toBe(VisaGroup::TRANSPORT_NONE)
        ->and($group->mandatory_transport_vendor_id)->toBeNull()
        ->and((float) $group->visa_sale_amount)->toBe(900.0)
        ->and((float) $group->visa_cost_amount)->toBe(750.0)
        ->and((float) $group->transport_amount)->toBe(0.0)
        ->and($group->sale_transaction_id)->not->toBeNull()
        ->and($group->cost_transaction_id)->not->toBeNull()
        ->and($group->passengers()->first()->service_type)->toBe(Passenger::SERVICE_VISA_TRANSPORT);
});

test('standard transport only booking respects child fare settings without requiring a visa vendor', function () {
    $f = quickBookingFixture();
    CompanyContext::setContext($f->company);
    $f->visaVendor->update(['is_default' => false]);
    $payload = quickBookingPayload($f, [
        'service_mode' => 'transport',
        'passenger_count' => 2,
        'passengers' => [
            ['full_name' => 'Adult Rider', 'nationality' => 'Pakistan', 'imported_age' => 30, 'visa_status' => 'received'],
            ['full_name' => 'Child Rider', 'nationality' => 'Pakistan', 'imported_age' => 8, 'visa_status' => 'received'],
        ],
    ]);

    $this->actingAs($f->operations)
        ->post("/{$f->company->slug}/umrah/quick-booking", $payload)
        ->assertRedirect();

    $group = VisaGroup::where('idempotency_key', $payload['idempotency_key'])->firstOrFail();
    expect($group->includes_visa)->toBeFalse()
        ->and($group->vendor_id)->toBeNull()
        ->and($group->status)->toBe(VisaGroup::STATUS_DRAFT)
        ->and($group->standard_bus_billable_passenger_count)->toBe(1)
        ->and((float) $group->transport_amount)->toBe(120.0)
        ->and((float) $group->transport_cost_amount)->toBe(80.0)
        ->and($group->passengers()->pluck('service_type')->unique()->all())->toBe([Passenger::SERVICE_TRANSPORT_ONLY]);
});

test('hotel only booking keeps hotel intent and opens a prefilled hotel voucher', function () {
    $f = quickBookingFixture();
    CompanyContext::setContext($f->company);
    $payload = quickBookingPayload($f, [
        'service_mode' => 'hotel',
        'next_step' => 'voucher',
        'hotel_makkah_id' => $f->makkahHotel->id,
        'hotel_madinah_id' => $f->madinahHotel->id,
        'makkah_nights' => 5,
        'madinah_nights' => 3,
    ]);

    $response = $this->actingAs($f->owner)
        ->post("/{$f->company->slug}/umrah/quick-booking", $payload);

    $group = VisaGroup::where('idempotency_key', $payload['idempotency_key'])->firstOrFail();
    $response->assertRedirect(route('umrah.vouchers.create', [
        'company' => $f->company->slug,
        'group_id' => $group->id,
    ]));
    expect($group->includes_visa)->toBeFalse()
        ->and($group->includes_hotel)->toBeTrue()
        ->and($group->transport_mode)->toBe(VisaGroup::TRANSPORT_NONE)
        ->and($group->vendor_id)->toBeNull()
        ->and((float) $group->total_receivable)->toBe(0.0)
        ->and($group->sale_transaction_id)->toBeNull()
        ->and($group->passengers()->first()->service_type)->toBe(Passenger::SERVICE_HOTEL_ONLY)
        ->and($group->hotel_info['makkah'])->toBe('Makkah Test Hotel')
        ->and($group->hotel_info['madinah_nights'])->toBe(3);

    $this->actingAs($f->owner)
        ->get(route('umrah.vouchers.create', ['company' => $f->company->slug, 'group_id' => $group->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Umrah/Vouchers/Create')
            ->where('bookingDefaults.service_bundle', Voucher::SERVICE_HOTEL)
            ->where('bookingDefaults.hotel_stays.0.hotel_id', $f->makkahHotel->id)
            ->where('bookingDefaults.hotel_stays.1.hotel_id', $f->madinahHotel->id)
        );
});

test('complete package prices adult and child correctly and records all service intent', function () {
    $f = quickBookingFixture();
    CompanyContext::setContext($f->company);
    $payload = quickBookingPayload($f, [
        'service_mode' => 'complete',
        'hotel_makkah_id' => $f->makkahHotel->id,
        'passenger_count' => 2,
        'passengers' => [
            ['full_name' => 'Adult Package', 'nationality' => 'Pakistan', 'imported_age' => 30, 'visa_status' => 'received'],
            ['full_name' => 'Child Package', 'nationality' => 'Pakistan', 'imported_age' => 8, 'visa_status' => 'received'],
        ],
    ]);

    $this->actingAs($f->operations)
        ->post("/{$f->company->slug}/umrah/quick-booking", $payload)
        ->assertRedirect();

    $group = VisaGroup::where('idempotency_key', $payload['idempotency_key'])->firstOrFail();
    expect($group->includes_visa)->toBeTrue()
        ->and($group->includes_hotel)->toBeTrue()
        ->and($group->transport_mode)->toBe(VisaGroup::TRANSPORT_STANDARD_BUS)
        ->and((float) $group->visa_sale_amount)->toBe(1400.0)
        ->and((float) $group->visa_cost_amount)->toBe(1150.0)
        ->and($group->standard_bus_billable_passenger_count)->toBe(1)
        ->and((float) $group->transport_amount)->toBe(120.0)
        ->and((float) $group->transport_cost_amount)->toBe(80.0)
        ->and((float) $group->total_receivable)->toBe(1520.0)
        ->and((float) $group->profit)->toBe(290.0);
});

test('specialized transport raises vehicle count to cover passengers and prices the resolved quantity', function () {
    $f = quickBookingFixture();
    CompanyContext::setContext($f->company);
    $payload = quickBookingPayload($f, [
        'service_mode' => 'transport',
        'passenger_count' => 45,
        'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
        'passengers' => [],
        'transport_items' => [[
            'transport_fare_id' => $f->fare->id,
            'driver_id' => null,
            'scheduled_at' => '2026-09-25 20:30:00',
            'terminal' => 'hajj',
            'quantity' => 1,
            'passenger_count' => 45,
            'notes' => null,
        ]],
    ]);

    $this->actingAs($f->operations)
        ->post("/{$f->company->slug}/umrah/quick-booking", $payload)
        ->assertRedirect();

    $group = VisaGroup::where('idempotency_key', $payload['idempotency_key'])->firstOrFail();
    $item = GroupTransportItem::where('visa_group_id', $group->id)->firstOrFail();
    expect($item->quantity)->toBe(3)
        ->and((float) $item->unit_sale_amount)->toBe(300.0)
        ->and((float) $item->surcharge_sale_amount)->toBe(150.0)
        ->and((float) $item->total_sale_amount)->toBe(1050.0)
        ->and((float) $item->total_cost_amount)->toBe(690.0)
        ->and((float) $group->transport_amount)->toBe(1050.0);
});

test('retrying the same request is idempotent while a different request cannot reuse its number', function () {
    $f = quickBookingFixture();
    CompanyContext::setContext($f->company);
    $payload = quickBookingPayload($f, ['service_mode' => 'visa']);

    $this->actingAs($f->operations)->post("/{$f->company->slug}/umrah/quick-booking", $payload)->assertRedirect();
    $this->actingAs($f->operations)->post("/{$f->company->slug}/umrah/quick-booking", $payload)->assertRedirect();

    expect(VisaGroup::where('idempotency_key', $payload['idempotency_key'])->count())->toBe(1)
        ->and(VisaGroup::where('group_number', $payload['group_number'])->count())->toBe(1);

    $second = quickBookingPayload($f, [
        'service_mode' => 'visa',
        'group_number' => $payload['group_number'],
    ]);
    $this->actingAs($f->operations)
        ->post("/{$f->company->slug}/umrah/quick-booking", $second)
        ->assertSessionHasErrors('group_number');
    expect(VisaGroup::count())->toBe(1);
});

test('invalid service setup and tampered company resources create nothing', function () {
    $f = quickBookingFixture();
    CompanyContext::setContext($f->company);
    $url = "/{$f->company->slug}/umrah/quick-booking";

    $cases = [
        ['service_mode', quickBookingPayload($f, ['service_mode' => 'unknown'])],
        ['passenger_count', quickBookingPayload($f, ['passenger_count' => 0])],
        ['hotel_makkah_id', quickBookingPayload($f, ['service_mode' => 'hotel', 'hotel_makkah_id' => (string) Str::uuid()])],
        ['transport_items.0.transport_fare_id', quickBookingPayload($f, [
            'service_mode' => 'transport',
            'transport_mode' => 'specialized',
            'transport_items' => [[
                'transport_fare_id' => (string) Str::uuid(),
                'terminal' => 'standard',
                'quantity' => 1,
                'passenger_count' => 1,
            ]],
        ])],
    ];

    foreach ($cases as [$field, $payload]) {
        $this->actingAs($f->operations)
            ->post($url, $payload)
            ->assertSessionHasErrors($field);
    }

    $f->visaVendor->update(['is_default' => false]);
    $this->actingAs($f->operations)
        ->post($url, quickBookingPayload($f, ['service_mode' => 'visa']))
        ->assertSessionHasErrors('service_mode');
    $f->visaVendor->update(['is_default' => true]);

    $f->transportProvider->update(['is_active' => false]);
    $this->actingAs($f->operations)
        ->post($url, quickBookingPayload($f, ['service_mode' => 'transport']))
        ->assertSessionHasErrors('transport_mode');

    expect(VisaGroup::count())->toBe(0);
});

test('voucher continuation requires permission, agent capability, and a named passenger', function () {
    $f = quickBookingFixture();
    CompanyContext::setContext($f->company);
    $url = "/{$f->company->slug}/umrah/quick-booking";

    $this->actingAs($f->owner)
        ->post($url, quickBookingPayload($f, [
            'service_mode' => 'hotel',
            'next_step' => 'voucher',
            'passengers' => [],
        ]))
        ->assertSessionHasErrors('passengers');

    $this->actingAs($f->agentUser)
        ->post($url, quickBookingPayload($f, [
            'service_mode' => 'visa',
            'next_step' => 'voucher',
        ]))
        ->assertSessionHasErrors('next_step');

    $this->actingAs($f->agentUser)
        ->post($url, quickBookingPayload($f, [
            'service_mode' => 'visa',
            'agent_id' => $f->otherAgent->id,
        ]))
        ->assertSessionHasErrors('agent_id');

    expect(VisaGroup::count())->toBe(0);
});

afterEach(function () {
    Carbon::setTestNow();
});
