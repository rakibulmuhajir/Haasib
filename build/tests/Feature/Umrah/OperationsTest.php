<?php

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Driver;
use App\Modules\Umrah\Models\GroupTransportItem;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\TransportSector;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use App\Modules\Umrah\Services\MovementReportService;
use App\Modules\Umrah\Services\OperationalEventTimelineService;
use App\Services\CompanyRbacBootstrapper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

function operationsAddMember(Company $company, User $user, string $role): void
{
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => $role,
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    CompanyContext::withContext($company, fn () => CompanyContext::assignRole($user, $role));
}

function operationsAddRawMember(Company $company, User $user, string $role = 'operations'): void
{
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => $role,
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function operationsFixture(): array
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 12:00:00', 'UTC'));

    $owner = User::factory()->withoutTwoFactor()->create();
    $operations = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Operations Test '.str()->random(8),
        'slug' => 'operations-test-'.str()->lower(str()->random(10)),
        'base_currency' => 'SAR',
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    operationsAddMember($company, $owner, 'owner');
    operationsAddMember($company, $operations, 'operations');
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    CompanyContext::setContext($company);

    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-EVT',
        'name' => 'Operations Agent',
    ]);
    $driver = Driver::create([
        'company_id' => $company->id,
        'name' => 'Ready Driver',
        'phone' => '+966500000001',
    ]);
    $vehicle = TransportService::create([
        'company_id' => $company->id,
        'driver_id' => $driver->id,
        'name' => 'Airport Coach',
        'vehicle_type' => 'Bus',
        'pax_capacity' => 45,
        'is_active' => true,
    ]);
    $airportSector = TransportSector::create([
        'company_id' => $company->id,
        'code' => 'JED-MAK-EVT',
        'name' => 'Jeddah Airport to Makkah',
        'origin' => 'JED',
        'destination' => 'Makkah',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $citySector = TransportSector::create([
        'company_id' => $company->id,
        'code' => 'MAK-MED-EVT',
        'name' => 'Makkah to Madinah',
        'origin' => 'Makkah',
        'destination' => 'Madinah',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    $group = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'transport_service_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'group_number' => 'UGR-EVT',
        'name' => 'September Operations',
        'status' => VisaGroup::STATUS_VISA_APPROVED,
        'travel_date' => '2026-09-05',
        'transport_required' => true,
        'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
        'passenger_count' => 2,
    ]);

    GroupTransportItem::create([
        'company_id' => $company->id,
        'visa_group_id' => $group->id,
        'transport_service_id' => $vehicle->id,
        'transport_sector_id' => $airportSector->id,
        'driver_id' => $driver->id,
        'description' => 'Airport pickup',
        'scheduled_at' => '2026-09-05 22:30:00',
        'passenger_count' => 2,
        'quantity' => 1,
    ]);
    GroupTransportItem::create([
        'company_id' => $company->id,
        'visa_group_id' => $group->id,
        'transport_service_id' => $vehicle->id,
        'transport_sector_id' => $citySector->id,
        'driver_id' => $driver->id,
        'description' => 'City transfer',
        'scheduled_at' => '2026-09-05 08:00:00',
        'passenger_count' => 2,
        'quantity' => 1,
    ]);

    $voucher = Voucher::create([
        'company_id' => $company->id,
        'visa_group_id' => $group->id,
        'agent_id' => $agent->id,
        'voucher_number' => 'UVR-EVT-1',
        'title' => 'Operations family',
        'service_bundle' => Voucher::SERVICE_VISA_TRANSPORT_HOTEL,
        'status' => Voucher::STATUS_APPROVED,
        'onward_airline' => 'SV',
        'onward_flight_number' => '726',
        'onward_departure_city' => 'KHI',
        'onward_arrival_city' => 'JED',
        'onward_departure_at' => '2026-09-05 17:10:00',
        'onward_arrival_at' => '2026-09-05 21:30:00',
        'return_airline' => 'SV',
        'return_flight_number' => '725',
        'return_departure_city' => 'JED',
        'return_arrival_city' => 'KHI',
        'return_departure_at' => '2026-09-12 10:30:00',
        'return_arrival_at' => '2026-09-12 15:15:00',
        'hotel_stays' => [
            [
                'source' => 'company',
                'hotel_name' => 'Makkah Gate Hotel',
                'city' => 'Makkah',
                'room_type' => 'double',
                'room_count' => 1,
                'check_in_date' => '2026-09-05',
                'check_out_date' => '2026-09-09',
            ],
            [
                'source' => 'company',
                'hotel_name' => 'Madinah Gate Hotel',
                'city' => 'Madinah',
                'room_type' => 'double',
                'room_count' => 1,
                'check_in_date' => '2026-09-09',
                'check_out_date' => '2026-09-12',
            ],
        ],
    ]);

    foreach ([
        ['Ayesha Siddiqua', 'PAK100001'],
        ['Bilal Ahmed', 'PAK100002'],
    ] as [$name, $passport]) {
        $passenger = Passenger::create([
            'company_id' => $company->id,
            'visa_group_id' => $group->id,
            'full_name' => $name,
            'passport_number' => $passport,
            'nationality' => 'Pakistan',
            'service_type' => Passenger::SERVICE_VISA_TRANSPORT,
            'visa_status' => Passenger::STATUS_APPROVED,
        ]);
        VoucherPassenger::create([
            'company_id' => $company->id,
            'voucher_id' => $voucher->id,
            'visa_group_id' => $group->id,
            'passenger_id' => $passenger->id,
        ]);
    }

    return compact(
        'company',
        'owner',
        'operations',
        'agent',
        'driver',
        'vehicle',
        'airportSector',
        'citySector',
        'group',
        'voucher',
    );
}

function operationsFilters(string $period = 'today'): array
{
    return [
        'period' => $period,
        'date' => '2026-09-05',
        'event_type' => 'all',
        'readiness' => 'all',
    ];
}

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

test('owner operations payload contains the same operational detail as an operations clerk', function () {
    $fixture = operationsFixture();

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['owner'],
        operationsFilters(),
    );
    $summary = collect($data['summary'])->keyBy('key');

    expect($data['profile'])->toBe('operational')
        ->and($data['shows_details'])->toBeTrue()
        ->and($data['events'])->not->toBeEmpty()
        ->and($data['agents'])->not->toBeEmpty()
        ->and($summary['moving_in']['value'])->toBe(2)
        ->and($summary['moving_out']['value'])->toBe(0)
        ->and($summary['makkah_to_madinah']['value'])->toBe(2)
        ->and(json_encode($data))->toContain('Ayesha Siddiqua')
        ->and(json_encode($data))->toContain('PAK100001')
        ->and(json_encode($data))->toContain('Makkah Gate Hotel')
        ->and(json_encode($data))->toContain('JED');
});

test('operations workspace preserves local wall clock time and includes operational detail', function () {
    $fixture = operationsFixture();

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        operationsFilters('tonight'),
    );
    $arrival = collect($data['events'])->firstWhere('type', 'airport_arrival');
    $pickup = collect($data['events'])->firstWhere('type', 'transport_pickup');

    expect($data['profile'])->toBe('operational')
        ->and($arrival)->not->toBeNull()
        ->and($arrival['scheduled_at'])->toBe('2026-09-05T21:30:00')
        ->and($arrival['scheduled_time'])->toBe('21:30')
        ->and($arrival['airport'])->toBe('JED')
        ->and($arrival['hotel'])->toBe('Makkah Gate Hotel')
        ->and($arrival['passengers'])->toHaveCount(2)
        ->and($arrival['readiness'])->toBe('ready')
        ->and($pickup)->not->toBeNull()
        ->and(collect($data['events'])->firstWhere('type', 'city_transfer'))->toBeNull();
});

test('hotel city changes become movement events when transport is not yet scheduled', function () {
    $fixture = operationsFixture();

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        [
            ...operationsFilters('custom'),
            'start' => '2026-09-09',
            'end' => '2026-09-09',
        ],
    );
    $transfer = collect($data['events'])->firstWhere('type', 'city_transfer');
    $summary = collect($data['summary'])->keyBy('key');

    expect($transfer)->not->toBeNull()
        ->and($transfer['origin'])->toBe('Makkah')
        ->and($transfer['destination'])->toBe('Madinah')
        ->and($transfer['passenger_count'])->toBe(2)
        ->and($transfer['readiness'])->toBe('needs_attention')
        ->and($transfer['readiness_issues'])->toContain('Transport is not scheduled')
        ->and($transfer['resolution_actions'][0]['key'])->toBe('group_transport')
        ->and($transfer['resolution_actions'][0]['href'])->toBe("/umrah/groups/{$fixture['group']->id}/edit?focus=transport&from=operations")
        ->and($summary['makkah_to_madinah']['value'])->toBe(2);
});

test('voucher readiness issues open the protected amendment workflow', function () {
    $fixture = operationsFixture();
    $stays = $fixture['voucher']->hotel_stays;
    $stays[0]['room_type'] = null;
    $fixture['voucher']->update(['hotel_stays' => $stays]);

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        operationsFilters(),
    );
    $checkIn = collect($data['events'])->firstWhere('type', 'hotel_check_in');

    expect($checkIn['readiness'])->toBe('needs_attention')
        ->and($checkIn['readiness_issues'])->toContain('Room arrangement is incomplete')
        ->and($checkIn['resolution_actions'][0]['key'])->toBe('voucher_amendment')
        ->and($checkIn['resolution_actions'][0]['href'])->toBe("/umrah/vouchers/{$fixture['voucher']->id}?workflow=amend&from=operations");

    CompanyContext::setContext($fixture['company']);
    $this->actingAs($fixture['operations'])
        ->get("/{$fixture['company']->slug}/umrah/vouchers/{$fixture['voucher']->id}?workflow=amend&from=operations")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Umrah/Vouchers/Show')
            ->where('openWorkflow', 'amend')
            ->where('agentCapabilities.can_amend', true)
        );
});

test('movement report presents the exact filtered Operations payload', function () {
    $fixture = operationsFixture();
    $filters = operationsFilters('today');

    $timeline = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        $filters,
    );
    $report = app(MovementReportService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        $filters,
    );
    $transport = collect($report['events'])->firstWhere('type', 'city_transfer');

    expect($report['events'])->toBe($timeline['events'])
        ->and($report['summary'])->toBe($timeline['summary'])
        ->and($report['filters'])->toBe($timeline['filters'])
        ->and($report['agent_summary'][0]['label'])->toBe('Operations Agent')
        ->and($report['group_summary'][0]['label'])->toBe('September Operations')
        ->and($transport['passengers'])->toHaveCount(2);
});

test('movement report preview and pdf give owners full detail while preserving accountant privacy', function () {
    $fixture = operationsFixture();
    $accountant = User::factory()->withoutTwoFactor()->create();
    operationsAddMember($fixture['company'], $accountant, 'accountant');
    CompanyContext::setContext($fixture['company']);
    $query = http_build_query(operationsFilters('today'));

    $this->actingAs($fixture['operations'])
        ->get("/{$fixture['company']->slug}/umrah/operations/report?{$query}")
        ->assertOk()
        ->assertSee('Movement Report')
        ->assertSee('Print report')
        ->assertSee('Download PDF')
        ->assertSee('Ayesha Siddiqua')
        ->assertSee('PAK100001')
        ->assertSee('Makkah Gate Hotel')
        ->assertSee('Ready Driver')
        ->assertSee('Time not specified')
        ->assertDontSee('All day');

    $this->actingAs($fixture['owner'])
        ->get("/{$fixture['company']->slug}/umrah/operations/report?{$query}")
        ->assertOk()
        ->assertSee('Ayesha Siddiqua')
        ->assertSee('PAK100001')
        ->assertSee('Makkah Gate Hotel')
        ->assertSee('JED');

    $this->actingAs($accountant)
        ->get("/{$fixture['company']->slug}/umrah/operations/report?{$query}")
        ->assertOk()
        ->assertSee('movement totals only')
        ->assertDontSee('Ayesha Siddiqua')
        ->assertDontSee('PAK100001')
        ->assertDontSee('Makkah Gate Hotel')
        ->assertDontSee('JED');

    $pdf = $this->actingAs($fixture['operations'])
        ->get("/{$fixture['company']->slug}/umrah/operations/report/pdf?{$query}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($pdf->getContent())->toStartWith('%PDF')
        ->and($pdf->headers->get('content-disposition'))->toContain('movement-report-today-2026-09-05.pdf');
});

test('operations land on Operations while the owner keeps the Umrah dashboard', function () {
    $fixture = operationsFixture();
    CompanyContext::setContext($fixture['company']);

    $this->actingAs($fixture['operations'])
        ->get("/{$fixture['company']->slug}/umrah")
        ->assertRedirect(route('umrah.operations.index', ['company' => $fixture['company']->slug]));

    $this->actingAs($fixture['operations'])
        ->get("/{$fixture['company']->slug}/umrah/operations")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Umrah/Operations/Index')
            ->where('operationsData.profile', 'operational')
            ->where('operationsData.shows_details', true)
        );

    $this->actingAs($fixture['owner'])
        ->get("/{$fixture['company']->slug}/umrah")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Umrah/Dashboard/Index'));

    $this->actingAs($fixture['owner'])
        ->get("/{$fixture['company']->slug}/umrah/operations")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Umrah/Operations/Index')
            ->where('operationsData.profile', 'operational')
            ->where('operationsData.shows_details', true)
            ->has('operationsData.events')
        );
});

test('an agent operations view is restricted to the linked agent records', function () {
    $fixture = operationsFixture();
    $agentUser = User::factory()->withoutTwoFactor()->create();
    operationsAddMember($fixture['company'], $agentUser, 'agent');
    $fixture['agent']->update(['user_id' => $agentUser->id]);
    CompanyContext::setContext($fixture['company']);

    $otherAgent = Agent::create([
        'company_id' => $fixture['company']->id,
        'agent_number' => 'AGT-OTHER-EVT',
        'name' => 'Other Operations Agent',
    ]);
    $otherGroup = VisaGroup::create([
        'company_id' => $fixture['company']->id,
        'agent_id' => $otherAgent->id,
        'group_number' => 'UGR-OTHER-EVT',
        'name' => 'Other Group',
        'status' => VisaGroup::STATUS_VISA_APPROVED,
        'travel_date' => '2026-09-05',
        'transport_required' => false,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'passenger_count' => 5,
    ]);
    Voucher::create([
        'company_id' => $fixture['company']->id,
        'visa_group_id' => $otherGroup->id,
        'agent_id' => $otherAgent->id,
        'voucher_number' => 'UVR-OTHER-EVT',
        'title' => 'Other agent travellers',
        'service_bundle' => Voucher::SERVICE_HOTEL,
        'status' => Voucher::STATUS_APPROVED,
        'hotel_stays' => [[
            'source' => 'self',
            'hotel_name' => 'Private Hotel',
            'city' => 'Makkah',
            'room_type' => 'double',
            'room_count' => 1,
            'check_in_date' => '2026-09-05',
            'check_out_date' => '2026-09-06',
        ]],
    ]);

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $agentUser,
        operationsFilters(),
    );

    expect($data['profile'])->toBe('agent_operational')
        ->and(collect($data['events'])->pluck('agent.id')->filter()->unique()->all())->toBe([$fixture['agent']->id])
        ->and(json_encode($data['events']))->not->toContain('Other Operations Agent')
        ->and(json_encode($data['events']))->not->toContain('Private Hotel')
        ->and(json_encode($data['events']))->not->toContain('Ready Driver')
        ->and(collect($data['events'])->flatMap(fn (array $event): array => $event['resolution_actions'])->all())->toBe([]);
});

test('all operations endpoints enforce authentication permission and module access', function () {
    $fixture = operationsFixture();
    $query = http_build_query(operationsFilters());
    $paths = [
        "/{$fixture['company']->slug}/umrah/operations?{$query}",
        "/{$fixture['company']->slug}/umrah/operations/report?{$query}",
        "/{$fixture['company']->slug}/umrah/operations/report/pdf?{$query}",
    ];

    foreach ($paths as $path) {
        $this->get($path)->assertRedirect(route('login'));
    }

    $denied = User::factory()->withoutTwoFactor()->create();
    operationsAddRawMember($fixture['company'], $denied);
    CompanyContext::setContext($fixture['company']);

    foreach ($paths as $path) {
        $this->actingAs($denied)->get($path)->assertForbidden();
    }

    $fixture['company']->update(['settings' => ['modules' => ['umrah' => false]]]);

    foreach ($paths as $path) {
        $this->actingAs($fixture['owner'])
            ->get($path)
            ->assertRedirect("/{$fixture['company']->slug}")
            ->assertSessionHas('error', 'This module is not enabled for the selected company.');
    }
});

test('operations rejects a malformed or foreign filter without a server error', function (array $query, array $fields) {
    $fixture = operationsFixture();
    CompanyContext::setContext($fixture['company']);
    $base = "/{$fixture['company']->slug}/umrah/operations";

    $this->actingAs($fixture['operations'])
        ->from($base)
        ->get($base.'?'.http_build_query($query))
        ->assertRedirect($base)
        ->assertSessionHasErrors($fields);
})->with([
    'unknown period' => [['period' => 'eventually'], ['period']],
    'wrong date format' => [['period' => 'today', 'date' => '05-09-2026'], ['date']],
    'custom dates missing' => [['period' => 'custom', 'date' => '2026-09-05'], ['start', 'end']],
    'custom dates reversed' => [[
        'period' => 'custom',
        'date' => '2026-09-05',
        'start' => '2026-09-06',
        'end' => '2026-09-05',
    ], ['end']],
    'unknown event type' => [[...operationsFilters(), 'event_type' => 'boarding'], ['event_type']],
    'unknown readiness' => [[...operationsFilters(), 'readiness' => 'maybe'], ['readiness']],
    'malformed agent id' => [[...operationsFilters(), 'agent_id' => 'not-a-uuid'], ['agent_id']],
    'unavailable agent id' => [[...operationsFilters(), 'agent_id' => (string) str()->uuid()], ['agent_id']],
]);

test('operations defaults use the Saudi operational date and valid filter values', function () {
    $fixture = operationsFixture();
    CompanyContext::setContext($fixture['company']);

    $this->actingAs($fixture['operations'])
        ->get("/{$fixture['company']->slug}/umrah/operations")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('operationsData.filters.period', 'today')
            ->where('operationsData.filters.date', '2026-09-05')
            ->where('operationsData.filters.event_type', 'all')
            ->where('operationsData.filters.readiness', 'all')
        );
});

test('manager accountant and unlinked agent receive only their configured presentation', function () {
    $fixture = operationsFixture();
    $manager = User::factory()->withoutTwoFactor()->create();
    $accountant = User::factory()->withoutTwoFactor()->create();
    $unlinkedAgent = User::factory()->withoutTwoFactor()->create();
    operationsAddMember($fixture['company'], $manager, 'manager');
    operationsAddMember($fixture['company'], $accountant, 'accountant');
    operationsAddMember($fixture['company'], $unlinkedAgent, 'agent');
    CompanyContext::setContext($fixture['company']);

    $managerData = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $manager,
        operationsFilters(),
    );
    $accountantData = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $accountant,
        operationsFilters(),
    );
    $unlinkedData = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $unlinkedAgent,
        operationsFilters(),
    );

    expect($managerData['profile'])->toBe('operational')
        ->and($managerData['events'])->not->toBeEmpty()
        ->and($managerData['agents'])->not->toBeEmpty()
        ->and($accountantData['profile'])->toBe('summary')
        ->and($accountantData['shows_details'])->toBeFalse()
        ->and($accountantData['events'])->toBe([])
        ->and($accountantData['agents'])->toBe([])
        ->and(json_encode($accountantData))->not->toContain('PAK100001')
        ->and($unlinkedData['profile'])->toBe('agent_operational')
        ->and($unlinkedData['events'])->toBe([])
        ->and($unlinkedData['agents'])->toBe([])
        ->and($unlinkedData['filters']['agent_id'])->toBeNull();

    $this->actingAs($accountant)
        ->get("/{$fixture['company']->slug}/umrah")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Umrah/Dashboard/Index'));
});

test('an agent cannot widen scope with a forged agent filter and an unlinked agent can still filter safely', function () {
    $fixture = operationsFixture();
    $agentUser = User::factory()->withoutTwoFactor()->create();
    $unlinkedAgent = User::factory()->withoutTwoFactor()->create();
    operationsAddMember($fixture['company'], $agentUser, 'agent');
    operationsAddMember($fixture['company'], $unlinkedAgent, 'agent');
    $fixture['agent']->update(['user_id' => $agentUser->id]);

    $otherAgent = Agent::create([
        'company_id' => $fixture['company']->id,
        'agent_number' => 'AGT-FORGED-EVT',
        'name' => 'Forged Scope Agent',
    ]);
    CompanyContext::setContext($fixture['company']);

    $forged = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $agentUser,
        [...operationsFilters(), 'agent_id' => $otherAgent->id],
    );

    expect($forged['filters']['agent_id'])->toBe($fixture['agent']->id)
        ->and(collect($forged['events'])->pluck('agent.id')->filter()->unique()->all())->toBe([$fixture['agent']->id]);

    $this->actingAs($unlinkedAgent)
        ->get("/{$fixture['company']->slug}/umrah/operations?".http_build_query(operationsFilters()))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('operationsData.events', [])
            ->where('operationsData.filters.agent_id', null)
        );
});

test('tonight tomorrow and next seven days respect their exact inclusive boundaries', function () {
    $fixture = operationsFixture();
    foreach ([
        ['Before tonight', '2026-09-05 17:59:00'],
        ['Tonight starts', '2026-09-05 18:00:00'],
        ['Tonight ends', '2026-09-06 06:00:00'],
        ['After tonight', '2026-09-06 06:01:00'],
        ['Seventh displayed day', '2026-09-11 23:59:00'],
        ['Eighth day', '2026-09-12 00:00:00'],
    ] as [$description, $scheduledAt]) {
        GroupTransportItem::create([
            'company_id' => $fixture['company']->id,
            'visa_group_id' => $fixture['group']->id,
            'transport_service_id' => $fixture['vehicle']->id,
            'transport_sector_id' => $fixture['airportSector']->id,
            'driver_id' => $fixture['driver']->id,
            'description' => $description,
            'scheduled_at' => $scheduledAt,
            'passenger_count' => 2,
            'quantity' => 1,
        ]);
    }

    $service = app(OperationalEventTimelineService::class);
    $tonight = collect($service->build($fixture['company'], $fixture['operations'], operationsFilters('tonight'))['events'])->pluck('scheduled_at');
    $tomorrow = collect($service->build($fixture['company'], $fixture['operations'], operationsFilters('tomorrow'))['events'])->pluck('scheduled_at');
    $nextSeven = collect($service->build($fixture['company'], $fixture['operations'], operationsFilters('next_7_days'))['events'])->pluck('scheduled_at');

    expect($tonight)->toContain('2026-09-05T18:00:00', '2026-09-06T06:00:00')
        ->and($tonight)->not->toContain('2026-09-05T17:59:00', '2026-09-06T06:01:00')
        ->and($tomorrow)->toContain('2026-09-06T06:00:00', '2026-09-06T06:01:00')
        ->and($tomorrow)->not->toContain('2026-09-05T18:00:00')
        ->and($nextSeven)->toContain('2026-09-11T23:59:00')
        ->and($nextSeven)->not->toContain('2026-09-12T00:00:00');
});

test('every movement type and readiness filter returns only the requested rows', function () {
    $fixture = operationsFixture();
    $service = app(OperationalEventTimelineService::class);
    $range = [
        ...operationsFilters('custom'),
        'start' => '2026-09-05',
        'end' => '2026-09-12',
    ];

    foreach (array_keys(OperationalEventTimelineService::EVENT_TYPES) as $type) {
        $events = collect($service->build(
            $fixture['company'],
            $fixture['operations'],
            [...$range, 'event_type' => $type],
        )['events']);

        expect($events)->not->toBeEmpty()
            ->and($events->pluck('type')->unique()->values()->all())->toBe([$type]);
    }

    $stays = $fixture['voucher']->hotel_stays;
    $stays[0]['source'] = 'self';
    $fixture['voucher']->update(['hotel_stays' => $stays]);

    foreach (['ready', 'needs_attention', 'self_arranged'] as $readiness) {
        $events = collect($service->build(
            $fixture['company'],
            $fixture['operations'],
            [...$range, 'readiness' => $readiness],
        )['events']);

        expect($events)->not->toBeEmpty()
            ->and($events->pluck('readiness')->unique()->values()->all())->toBe([$readiness]);
    }
});

test('a precise scheduled city transfer replaces the inferred warning', function () {
    $fixture = operationsFixture();
    GroupTransportItem::create([
        'company_id' => $fixture['company']->id,
        'visa_group_id' => $fixture['group']->id,
        'transport_service_id' => $fixture['vehicle']->id,
        'transport_sector_id' => $fixture['citySector']->id,
        'driver_id' => $fixture['driver']->id,
        'description' => 'Precise Makkah to Madinah transfer',
        'scheduled_at' => '2026-09-09 09:15:00',
        'passenger_count' => 2,
        'quantity' => 1,
    ]);

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        [
            ...operationsFilters('custom'),
            'start' => '2026-09-09',
            'end' => '2026-09-09',
            'event_type' => 'city_transfer',
        ],
    );
    $transfers = collect($data['events']);

    expect($transfers)->toHaveCount(1)
        ->and($transfers->first()['event_key'])->toStartWith('transport:')
        ->and($transfers->first()['scheduled_time'])->toBe('09:15')
        ->and($transfers->first()['readiness'])->toBe('ready')
        ->and($transfers->first()['headline'])->toBe('Makkah to Madinah');
});

test('draft cancelled superseded and cancelled-group vouchers never enter Operations', function () {
    $fixture = operationsFixture();

    foreach ([
        ['UVR-DRAFT-EVT', 'Draft leak', Voucher::STATUS_DRAFT, null],
        ['UVR-CANCELLED-EVT', 'Cancelled leak', Voucher::STATUS_CANCELLED, null],
        ['UVR-SUPERSEDED-EVT', 'Superseded leak', Voucher::STATUS_APPROVED, now()],
    ] as [$number, $title, $status, $supersededAt]) {
        $voucher = $fixture['voucher']->replicate();
        $voucher->voucher_number = $number;
        $voucher->title = $title;
        $voucher->status = $status;
        $voucher->superseded_at = $supersededAt;
        $voucher->save();
    }

    $cancelledGroup = $fixture['group']->replicate();
    $cancelledGroup->group_number = 'UGR-CANCELLED-EVT';
    $cancelledGroup->name = 'Cancelled Group Leak';
    $cancelledGroup->status = VisaGroup::STATUS_CANCELLED;
    $cancelledGroup->save();
    $cancelledVoucher = $fixture['voucher']->replicate();
    $cancelledVoucher->visa_group_id = $cancelledGroup->id;
    $cancelledVoucher->voucher_number = 'UVR-CANCELLED-GROUP-EVT';
    $cancelledVoucher->title = 'Cancelled group voucher leak';
    $cancelledVoucher->save();

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        operationsFilters(),
    );
    $json = json_encode($data['events']);

    expect($json)->not->toContain('Draft leak')
        ->and($json)->not->toContain('Cancelled leak')
        ->and($json)->not->toContain('Superseded leak')
        ->and($json)->not->toContain('Cancelled group voucher leak');
});

test('soft-deleted assignments and transport items are absent from manifests and movements', function () {
    $fixture = operationsFixture();
    VoucherPassenger::query()
        ->where('voucher_id', $fixture['voucher']->id)
        ->whereHas('passenger', fn ($query) => $query->where('passport_number', 'PAK100001'))
        ->firstOrFail()
        ->delete();
    GroupTransportItem::query()
        ->where('visa_group_id', $fixture['group']->id)
        ->where('description', 'Airport pickup')
        ->firstOrFail()
        ->delete();

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        operationsFilters('tonight'),
    );
    $json = json_encode($data['events']);
    $arrival = collect($data['events'])->firstWhere('type', 'airport_arrival');

    expect($json)->not->toContain('PAK100001')
        ->and($json)->not->toContain('Airport pickup')
        ->and($arrival['passenger_count'])->toBe(1)
        ->and($arrival['readiness'])->toBe('needs_attention')
        ->and($arrival['readiness_issues'])->toContain('Transport is not scheduled');
});

test('all derived readiness failures are explicit and never expose internal timezone data', function () {
    $fixture = operationsFixture();
    $fixture['group']->update(['driver_id' => null]);
    $fixture['vehicle']->update(['pax_capacity' => 1]);

    GroupTransportItem::create([
        'company_id' => $fixture['company']->id,
        'visa_group_id' => $fixture['group']->id,
        'transport_sector_id' => $fixture['airportSector']->id,
        'description' => 'Unassigned dispatch',
        'scheduled_at' => '2026-09-05 19:30:00',
        'passenger_count' => 2,
        'quantity' => 1,
    ]);

    $stays = $fixture['voucher']->hotel_stays;
    $stays[0]['hotel_name'] = null;
    $stays[0]['room_type'] = null;
    $stays[0]['room_count'] = 0;
    $fixture['voucher']->update(['hotel_stays' => $stays]);

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        operationsFilters('tonight'),
    );
    $unassigned = collect($data['events'])->first(
        fn (array $event): bool => $event['type'] === 'transport_pickup'
            && $event['scheduled_time'] === '19:30',
    );
    $checkIn = collect($data['events'])->firstWhere('type', 'hotel_check_in');
    $issues = collect($data['events'])->flatMap(fn (array $event): array => $event['readiness_issues']);

    expect($issues)->toContain('Vehicle is not assigned')
        ->and($issues)->toContain('Driver is not assigned')
        ->and($issues)->toContain('Capacity is short by 1 seats')
        ->and($checkIn['readiness_issues'])->toContain('Hotel is not selected')
        ->and($checkIn['readiness_issues'])->toContain('Room arrangement is incomplete')
        ->and($unassigned['readiness'])->toBe('needs_attention')
        ->and(json_encode($data['events']))->not->toContain('_timezone');
});

test('self-arranged services are not reported as missing company transport', function () {
    $fixture = operationsFixture();
    $fixture['voucher']->update(['service_bundle' => Voucher::SERVICE_VISA_HOTEL]);
    $fixture['group']->update([
        'transport_required' => false,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'transport_service_id' => null,
        'driver_id' => null,
    ]);

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        operationsFilters('tonight'),
    );
    $arrival = collect($data['events'])->firstWhere('type', 'airport_arrival');

    expect($arrival['readiness'])->toBe('self_arranged')
        ->and($arrival['readiness_issues'])->toBe([])
        ->and($arrival['transport'])->toBeNull();
});

test('needs-attention actions require their separate update permissions', function () {
    $fixture = operationsFixture();
    $viewer = User::factory()->withoutTwoFactor()->create();
    operationsAddRawMember($fixture['company'], $viewer);
    CompanyContext::withContext(
        $fixture['company'],
        fn () => $viewer->givePermissionTo(Permissions::UMRAH_OPERATIONS_VIEW),
    );
    $stays = $fixture['voucher']->hotel_stays;
    $stays[0]['room_type'] = null;
    $fixture['voucher']->update(['hotel_stays' => $stays]);

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $viewer,
        operationsFilters(),
    );
    $attention = collect($data['events'])->where('readiness', 'needs_attention');

    expect($data['profile'])->toBe('operational')
        ->and($attention)->not->toBeEmpty()
        ->and($attention->flatMap(fn (array $event): array => $event['resolution_actions'])->all())->toBe([]);

    CompanyContext::setContext($fixture['company']);
    $this->actingAs($viewer)
        ->get("/{$fixture['company']->slug}/umrah/operations?".http_build_query(operationsFilters()))
        ->assertOk();
});

test('operations reads do not mutate voucher passenger or transport records', function () {
    $fixture = operationsFixture();
    CompanyContext::setContext($fixture['company']);
    $before = [
        'vouchers' => Voucher::query()->where('company_id', $fixture['company']->id)->count(),
        'assignments' => VoucherPassenger::query()->where('company_id', $fixture['company']->id)->count(),
        'transport' => GroupTransportItem::query()->where('company_id', $fixture['company']->id)->count(),
        'voucher_updated_at' => $fixture['voucher']->fresh()->updated_at->toISOString(),
        'group_updated_at' => $fixture['group']->fresh()->updated_at->toISOString(),
    ];
    $query = http_build_query(operationsFilters());

    $this->actingAs($fixture['operations'])
        ->get("/{$fixture['company']->slug}/umrah/operations?{$query}")
        ->assertOk();
    $this->actingAs($fixture['operations'])
        ->get("/{$fixture['company']->slug}/umrah/operations/report?{$query}")
        ->assertOk();

    expect(Voucher::query()->where('company_id', $fixture['company']->id)->count())->toBe($before['vouchers'])
        ->and(VoucherPassenger::query()->where('company_id', $fixture['company']->id)->count())->toBe($before['assignments'])
        ->and(GroupTransportItem::query()->where('company_id', $fixture['company']->id)->count())->toBe($before['transport'])
        ->and($fixture['voucher']->fresh()->updated_at->toISOString())->toBe($before['voucher_updated_at'])
        ->and($fixture['group']->fresh()->updated_at->toISOString())->toBe($before['group_updated_at']);
});

test('a full package names missing passengers and missing first and final stays', function () {
    $fixture = operationsFixture();
    VoucherPassenger::query()->where('voucher_id', $fixture['voucher']->id)->delete();
    $fixture['voucher']->update(['hotel_stays' => []]);

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        [
            ...operationsFilters('custom'),
            'start' => '2026-09-05',
            'end' => '2026-09-12',
        ],
    );
    $arrival = collect($data['events'])->firstWhere('type', 'airport_arrival');
    $departure = collect($data['events'])->firstWhere('type', 'airport_departure');

    expect($arrival['passenger_count'])->toBe(0)
        ->and($arrival['readiness_issues'])->toContain('No passengers are assigned')
        ->and($arrival['readiness_issues'])->toContain('First stay is missing')
        ->and($departure['readiness_issues'])->toContain('No passengers are assigned')
        ->and($departure['readiness_issues'])->toContain('Final stay is missing');
});

test('visa and transport service-only vouchers do not demand a company-booked hotel', function (string $bundle) {
    $fixture = operationsFixture();
    $fixture['voucher']->update([
        'service_bundle' => $bundle,
        'hotel_stays' => [],
    ]);

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        operationsFilters('tonight'),
    );
    $arrival = collect($data['events'])->firstWhere('type', 'airport_arrival');

    expect($arrival)->not->toBeNull()
        ->and($arrival['readiness'])->toBe('ready')
        ->and($arrival['readiness_issues'])->not->toContain('First stay is missing');
})->with([
    'visa and transport' => Voucher::SERVICE_VISA_TRANSPORT,
    'transport only' => Voucher::SERVICE_TRANSPORT,
]);

test('hotel-only vouchers ignore stale flight fields and produce hotel movements only', function () {
    $fixture = operationsFixture();
    $fixture['voucher']->update(['service_bundle' => Voucher::SERVICE_HOTEL]);
    $fixture['group']->update([
        'transport_required' => false,
        'transport_mode' => VisaGroup::TRANSPORT_NONE,
        'transport_service_id' => null,
        'driver_id' => null,
    ]);

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        [
            ...operationsFilters('custom'),
            'start' => '2026-09-05',
            'end' => '2026-09-12',
        ],
    );

    expect(collect($data['events'])->pluck('type')->unique()->sort()->values()->all())->toBe([
        'hotel_check_in',
        'hotel_check_out',
    ]);
});

test('Madinah-first itineraries count the reverse city movement and preserve MED local time', function () {
    $fixture = operationsFixture();
    GroupTransportItem::query()->where('visa_group_id', $fixture['group']->id)->delete();
    $fixture['voucher']->update([
        'onward_arrival_city' => 'MED',
        'onward_arrival_at' => '2026-09-05 23:40:00',
        'return_departure_city' => 'JED',
        'hotel_stays' => [
            [
                'source' => 'self',
                'hotel_name' => 'Madinah First Hotel',
                'city' => 'Madinah',
                'room_type' => 'double',
                'room_count' => 1,
                'check_in_date' => '2026-09-05',
                'check_out_date' => '2026-09-09',
            ],
            [
                'source' => 'self',
                'hotel_name' => 'Makkah Last Hotel',
                'city' => 'Makkah',
                'room_type' => 'double',
                'room_count' => 1,
                'check_in_date' => '2026-09-09',
                'check_out_date' => '2026-09-12',
            ],
        ],
    ]);

    $arrivalData = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        operationsFilters('tonight'),
    );
    $arrival = collect($arrivalData['events'])->firstWhere('type', 'airport_arrival');
    $transferData = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        [
            ...operationsFilters('custom'),
            'start' => '2026-09-09',
            'end' => '2026-09-09',
        ],
    );
    $reverse = collect($transferData['events'])->firstWhere('type', 'city_transfer');
    $summary = collect($transferData['summary'])->keyBy('key');

    expect($arrival['airport'])->toBe('MED')
        ->and($arrival['scheduled_time'])->toBe('23:40')
        ->and($arrival['destination'])->toBe('Madinah')
        ->and($reverse['origin'])->toBe('Madinah')
        ->and($reverse['destination'])->toBe('Makkah')
        ->and($summary['madinah_to_makkah']['value'])->toBe(2)
        ->and($summary['makkah_to_madinah']['value'])->toBe(0);
});

test('company and agent filters never cross tenant boundaries', function () {
    $first = operationsFixture();
    $second = operationsFixture();
    $second['voucher']->update(['title' => 'Foreign Tenant Secret']);
    $second['agent']->update(['name' => 'Foreign Tenant Agent']);
    CompanyContext::setContext($first['company']);

    $data = app(OperationalEventTimelineService::class)->build(
        $first['company'],
        $first['operations'],
        operationsFilters(),
    );
    $json = json_encode($data);

    expect($json)->not->toContain('Foreign Tenant Secret')
        ->and($json)->not->toContain('Foreign Tenant Agent');

    $this->actingAs($first['operations'])
        ->from("/{$first['company']->slug}/umrah/operations")
        ->get("/{$first['company']->slug}/umrah/operations?".http_build_query([
            ...operationsFilters(),
            'agent_id' => $second['agent']->id,
        ]))
        ->assertRedirect("/{$first['company']->slug}/umrah/operations")
        ->assertSessionHasErrors('agent_id');
});

test('agent movement reports retain own manifests but hide drivers and other agents', function () {
    $fixture = operationsFixture();
    $agentUser = User::factory()->withoutTwoFactor()->create();
    operationsAddMember($fixture['company'], $agentUser, 'agent');
    $fixture['agent']->update(['user_id' => $agentUser->id]);
    CompanyContext::setContext($fixture['company']);
    $query = http_build_query(operationsFilters('tonight'));

    $this->actingAs($agentUser)
        ->get("/{$fixture['company']->slug}/umrah/operations/report?{$query}")
        ->assertOk()
        ->assertSee('Ayesha Siddiqua')
        ->assertSee('PAK100001')
        ->assertDontSee('Ready Driver')
        ->assertDontSee('+966500000001');
});

test('the agent picker includes only active agents from the current company', function () {
    $fixture = operationsFixture();
    Agent::create([
        'company_id' => $fixture['company']->id,
        'agent_number' => 'AGT-INACTIVE-EVT',
        'name' => 'Inactive Operations Agent',
        'is_active' => false,
    ]);

    $data = app(OperationalEventTimelineService::class)->build(
        $fixture['company'],
        $fixture['operations'],
        operationsFilters(),
    );

    expect(collect($data['agents'])->pluck('label')->all())->toContain('Operations Agent')
        ->and(collect($data['agents'])->pluck('label')->all())->not->toContain('Inactive Operations Agent');
});
