<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\ApproveVoucherRequest;
use App\Modules\Umrah\Http\Requests\StoreVoucherRequest;
use App\Modules\Umrah\Http\Requests\UpdateVoucherRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Hotel;
use App\Modules\Umrah\Models\HotelRoomRate;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use App\Modules\Umrah\Services\HotelStayPricingCalculator;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VoucherController extends Controller
{
    public function __construct(private UmrahCoreService $service, private HotelStayPricingCalculator $hotelPricing) {}

    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        $search = trim((string) $request->input('search', ''));
        $isMember = $this->currentCompanyRole($company->id, $request) === 'member';
        $memberAgentId = $isMember ? $this->linkedAgentId($company->id, $request) : null;

        $vouchers = Voucher::where('company_id', $company->id)
            ->with(['agent:id,name', 'group:id,group_number,name,travel_date'])
            ->withCount('passengers')
            ->when($isMember, fn ($query) => $memberAgentId
                ? $query->where('agent_id', $memberAgentId)
                : $query->whereRaw('1 = 0'))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('voucher_number', 'ilike', "%{$search}%")
                ->orWhere('title', 'ilike', "%{$search}%")
                ->orWhereHas('agent', fn ($agent) => $agent->where('name', 'ilike', "%{$search}%"))
                ->orWhereHas('group', fn ($group) => $group
                    ->where('group_number', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%"))))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Umrah/Vouchers/Index', [
            'company' => $this->companyPayload($company),
            'vouchers' => $vouchers,
            'filters' => ['search' => $search],
            'statuses' => Voucher::STATUSES,
            'serviceBundles' => Voucher::SERVICE_BUNDLES,
        ]);
    }

    public function create(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        $groupId = $request->input('group_id');
        $isMember = $this->currentCompanyRole($company->id, $request) === 'member';
        $memberAgentId = $isMember ? $this->linkedAgentId($company->id, $request) : null;

        $groups = VisaGroup::where('company_id', $company->id)
            ->with('agent:id,name')
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
            ->when($isMember, fn ($query) => $memberAgentId
                ? $query->where('agent_id', $memberAgentId)
                : $query->whereRaw('1 = 0'))
            ->orderByDesc('created_at')
            ->get(['id', 'agent_id', 'group_number', 'name', 'passenger_count', 'travel_date']);

        $selectedGroup = null;
        $availablePassengers = collect();
        $assignedPassengers = collect();

        if ($groupId) {
            $selectedGroup = VisaGroup::where('company_id', $company->id)
                ->with('agent:id,name')
                ->when($isMember, fn ($query) => $memberAgentId
                    ? $query->where('agent_id', $memberAgentId)
                    : $query->whereRaw('1 = 0'))
                ->findOrFail($groupId);

            $assignedIds = VoucherPassenger::where('company_id', $company->id)
                ->where('visa_group_id', $selectedGroup->id)
                ->pluck('passenger_id');

            $availablePassengers = Passenger::where('company_id', $company->id)
                ->where('visa_group_id', $selectedGroup->id)
                ->whereNotIn('id', $assignedIds)
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->get(['id', 'full_name', 'passport_number', 'nationality', 'visa_status', 'service_type']);

            $assignedPassengers = Passenger::where('company_id', $company->id)
                ->where('visa_group_id', $selectedGroup->id)
                ->whereIn('id', $assignedIds)
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->get(['id', 'full_name', 'passport_number', 'nationality', 'visa_status', 'service_type']);
        }

        $hotels = Hotel::where('company_id', $company->id)->where('is_active', true)
            ->with(['vendor:id,name', 'roomRates' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('city')->orderBy('name')->get();
        if ($isMember) {
            $hotels->each(fn (Hotel $hotel) => $hotel->roomRates->each->makeHidden('cost_amount'));
        }

        return Inertia::render('Umrah/Vouchers/Create', [
            'company' => $this->companyPayload($company),
            'nextVoucherNumber' => $this->service->nextVoucherNumber($company->id),
            'groups' => $groups,
            'selectedGroup' => $selectedGroup,
            'availablePassengers' => $availablePassengers->values(),
            'assignedPassengers' => $assignedPassengers->values(),
            'statuses' => Voucher::STATUSES,
            'serviceBundles' => Voucher::SERVICE_BUNDLES,
            'airlines' => Voucher::AIRLINES,
            'airportCities' => Voucher::AIRPORT_CITIES,
            'hotels' => $hotels,
            'editingVoucher' => null,
            'agentCapabilities' => $this->voucherCapabilities($company->id, $request),
        ]);
    }

    public function store(StoreVoucherRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();
        if ($this->currentCompanyRole($company->id, $request) === 'member') {
            $data['status'] = Voucher::STATUS_DRAFT;
        }
        $group = VisaGroup::where('company_id', $company->id)->findOrFail($data['visa_group_id']);

        $voucher = DB::transaction(function () use ($company, $data, $group, $request) {
            $hasFlights = $data['service_bundle'] !== Voucher::SERVICE_HOTEL;
            [$hotelStays, $hotelSale, $hotelCost] = $this->resolveHotelStays(
                $company->id,
                $data['hotel_stays'],
                Voucher::bundleIncludesHotel($data['service_bundle']),
            );
            $voucher = Voucher::create([
                'company_id' => $company->id,
                'visa_group_id' => $group->id,
                'agent_id' => $group->agent_id,
                'voucher_number' => $data['voucher_number'] ?: $this->service->nextVoucherNumber($company->id),
                'title' => $data['title'],
                'service_bundle' => $data['service_bundle'],
                'status' => $data['status'] ?? Voucher::STATUS_DRAFT,
                'onward_airline' => $hasFlights ? ($data['onward_airline'] ?? null) : null,
                'onward_flight_number' => $hasFlights ? ($data['onward_flight_number'] ?? null) : null,
                'onward_departure_city' => $hasFlights ? ($data['onward_departure_city'] ?? null) : null,
                'onward_arrival_city' => $hasFlights ? ($data['onward_arrival_city'] ?? null) : null,
                'onward_departure_at' => $hasFlights ? ($data['onward_departure_at'] ?? null) : null,
                'onward_arrival_at' => $hasFlights ? ($data['onward_arrival_at'] ?? null) : null,
                'return_airline' => $hasFlights ? ($data['return_airline'] ?? null) : null,
                'return_flight_number' => $hasFlights ? ($data['return_flight_number'] ?? null) : null,
                'return_departure_city' => $hasFlights ? ($data['return_departure_city'] ?? null) : null,
                'return_arrival_city' => $hasFlights ? ($data['return_arrival_city'] ?? null) : null,
                'return_departure_at' => $hasFlights ? ($data['return_departure_at'] ?? null) : null,
                'return_arrival_at' => $hasFlights ? ($data['return_arrival_at'] ?? null) : null,
                'hotel_stays' => $hotelStays,
                'hotel_sale_amount' => $hotelSale,
                'hotel_cost_amount' => $hotelCost,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $request->user()?->id,
            ]);

            foreach (array_values(array_unique($data['passenger_ids'])) as $passengerId) {
                Passenger::where('company_id', $company->id)
                    ->where('visa_group_id', $group->id)
                    ->whereKey($passengerId)
                    ->update(['service_type' => $data['passenger_services'][$passengerId] ?? Passenger::SERVICE_VISA_TRANSPORT]);
                VoucherPassenger::create([
                    'company_id' => $company->id,
                    'voucher_id' => $voucher->id,
                    'visa_group_id' => $group->id,
                    'passenger_id' => $passengerId,
                ]);
            }

            $this->service->recalculateGroup($group->fresh());
            $this->service->recalculateAgent($group->agent_id);

            if ($voucher->status === Voucher::STATUS_APPROVED) {
                $this->service->applyVoucherHotelAccounting($voucher, $group);
            }

            return $voucher;
        });

        return redirect()->route('umrah.vouchers.show', ['company' => $company->slug, 'voucher' => $voucher->id])
            ->with('success', 'Voucher created successfully.');
    }

    public function approve(ApproveVoucherRequest $request, string $companySlug, string $voucher): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = Voucher::where('company_id', $company->id)->with('group')
            ->when($this->currentCompanyRole($company->id, $request) === 'member', fn ($q) => ($agentId = $this->linkedAgentId($company->id, $request)) ? $q->where('agent_id', $agentId) : $q->whereRaw('1 = 0'))
            ->findOrFail($voucher);
        if ($record->status !== Voucher::STATUS_APPROVED) {
            DB::transaction(function () use ($record) {
                $record->update(['status' => Voucher::STATUS_APPROVED]);
                $this->service->applyVoucherHotelAccounting($record->fresh(), $record->group);
            });
        }

        return back()->with('success', 'Voucher approved successfully.');
    }

    public function edit(Request $request, string $companySlug, string $voucher): Response|RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_VOUCHER_UPDATE), 403);
        $capabilities = $this->voucherCapabilities($company->id, $request);
        abort_unless($capabilities['can_edit'], 403);
        $record = Voucher::where('company_id', $company->id)->with(['group.agent:id,name', 'passengers'])
            ->when($this->currentCompanyRole($company->id, $request) === 'member', fn ($q) => ($agentId = $this->linkedAgentId($company->id, $request)) ? $q->where('agent_id', $agentId) : $q->whereRaw('1 = 0'))
            ->findOrFail($voucher);
        if ($record->status !== Voucher::STATUS_DRAFT) {
            return redirect()->route('umrah.vouchers.show', ['company' => $companySlug, 'voucher' => $record->id])
                ->with('error', 'Approved vouchers cannot be modified.');
        }
        $hotels = Hotel::where('company_id', $company->id)->where('is_active', true)->with(['vendor:id,name', 'roomRates' => fn ($q) => $q->where('is_active', true)])->orderBy('city')->orderBy('name')->get();
        if ($this->currentCompanyRole($company->id, $request) === 'member') {
            $hotels->each(fn (Hotel $hotel) => $hotel->roomRates->each->makeHidden('cost_amount'));
        }

        return Inertia::render('Umrah/Vouchers/Create', [
            'company' => $this->companyPayload($company), 'nextVoucherNumber' => $record->voucher_number,
            'groups' => collect([$record->group]), 'selectedGroup' => $record->group,
            'availablePassengers' => $record->passengers, 'assignedPassengers' => collect(),
            'statuses' => Voucher::STATUSES, 'serviceBundles' => Voucher::SERVICE_BUNDLES, 'airlines' => Voucher::AIRLINES, 'airportCities' => Voucher::AIRPORT_CITIES,
            'hotels' => $hotels, 'editingVoucher' => $record, 'agentCapabilities' => $capabilities,
        ]);
    }

    public function update(UpdateVoucherRequest $request, string $companySlug, string $voucher): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = Voucher::where('company_id', $company->id)
            ->when($this->currentCompanyRole($company->id, $request) === 'member', fn ($q) => ($agentId = $this->linkedAgentId($company->id, $request)) ? $q->where('agent_id', $agentId) : $q->whereRaw('1 = 0'))
            ->findOrFail($voucher);
        if ($record->status !== Voucher::STATUS_DRAFT) {
            return back()->with('error', 'Approved vouchers cannot be modified.');
        }
        $data = $request->validated();
        $hasFlights = $data['service_bundle'] !== Voucher::SERVICE_HOTEL;
        [$hotelStays, $hotelSale, $hotelCost] = $this->resolveHotelStays(
            $company->id,
            $data['hotel_stays'],
            Voucher::bundleIncludesHotel($data['service_bundle']),
        );
        $record->update([
            'title' => $data['title'], 'service_bundle' => $data['service_bundle'], 'onward_airline' => $hasFlights ? ($data['onward_airline'] ?? null) : null, 'onward_flight_number' => $hasFlights ? ($data['onward_flight_number'] ?? null) : null,
            'onward_departure_city' => $hasFlights ? ($data['onward_departure_city'] ?? null) : null, 'onward_arrival_city' => $hasFlights ? ($data['onward_arrival_city'] ?? null) : null, 'onward_departure_at' => $hasFlights ? ($data['onward_departure_at'] ?? null) : null, 'onward_arrival_at' => $hasFlights ? ($data['onward_arrival_at'] ?? null) : null,
            'return_airline' => $hasFlights ? ($data['return_airline'] ?? null) : null, 'return_flight_number' => $hasFlights ? ($data['return_flight_number'] ?? null) : null, 'return_departure_city' => $hasFlights ? ($data['return_departure_city'] ?? null) : null,
            'return_arrival_city' => $hasFlights ? ($data['return_arrival_city'] ?? null) : null, 'return_departure_at' => $hasFlights ? ($data['return_departure_at'] ?? null) : null, 'return_arrival_at' => $hasFlights ? ($data['return_arrival_at'] ?? null) : null,
            'hotel_stays' => $hotelStays, 'hotel_sale_amount' => $hotelSale, 'hotel_cost_amount' => $hotelCost, 'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('umrah.vouchers.show', ['company' => $company->slug, 'voucher' => $record->id])->with('success', 'Voucher updated successfully.');
    }

    private function resolveHotelStays(string $companyId, array $stays, bool $chargeHotels): array
    {
        $resolved = [];
        $sale = 0.0;
        $cost = 0.0;
        foreach ($stays as $stay) {
            $snapshot = $stay;
            $snapshot['source'] = $stay['source'];
            $snapshot['unit_retail_amount'] = 0;
            $snapshot['unit_cost_amount'] = 0;
            $snapshot['total_retail_amount'] = 0;
            $snapshot['total_cost_amount'] = 0;
            $snapshot['night_count'] = 0;
            if ($stay['source'] === 'company') {
                $hotel = Hotel::where('company_id', $companyId)->with(['vendor', 'roomRates' => fn ($q) => $q->where('room_type', $stay['room_type'])->where('is_active', true)])->findOrFail($stay['hotel_id']);
                $rate = $hotel->roomRates->firstOrFail();
                $rooms = max((int) $stay['room_count'], 1);
                $retail = $chargeHotels ? (float) $rate->retail_amount : 0.0;
                $costAmount = $chargeHotels ? (float) $rate->cost_amount : 0.0;
                $totals = $this->hotelPricing->calculate($stay['check_in_date'], $stay['check_out_date'], $rooms, HotelRoomRate::bedsFor($rate->room_type), $retail, $costAmount);
                $snapshot = [...$snapshot, ...$totals, 'hotel_id' => $hotel->id, 'hotel_vendor_id' => $hotel->hotel_vendor_id, 'hotel_name' => $hotel->name, 'city' => $hotel->city, 'room_type' => $rate->room_type, 'room_count' => $rooms, 'unit_retail_amount' => $retail, 'unit_cost_amount' => $costAmount];
                $sale += $snapshot['total_retail_amount'];
                $cost += $snapshot['total_cost_amount'];
            } else {
                $snapshot['hotel_id'] = null;
                $snapshot['hotel_vendor_id'] = null;
                $snapshot['room_count'] = max((int) $stay['room_count'], 1);
                $totals = $this->hotelPricing->calculate($stay['check_in_date'], $stay['check_out_date'], (int) $stay['room_count'], HotelRoomRate::bedsFor($stay['room_type']), 0, 0);
                $snapshot['night_count'] = $totals['night_count'];
                $snapshot['beds_per_room'] = $totals['beds_per_room'];
            }
            $resolved[] = $snapshot;
        }

        return [$resolved, round($sale, 2), round($cost, 2)];
    }

    public function show(string $companySlug, string $voucher): Response
    {
        $company = app(CurrentCompany::class)->get();
        $record = Voucher::where('company_id', $company->id)
            ->with([
                'agent:id,name,phone,email,city,country,logo_url',
                'group' => fn ($query) => $query
                    ->select(['id', 'group_number', 'name', 'travel_date', 'passenger_count', 'transport_mode', 'transport_service_id', 'driver_id', 'transport_pax_capacity'])
                    ->with([
                        'transportService:id,name,vehicle_type,number_plate,driver_name,driver_contact',
                        'driver:id,name,phone',
                        'transportItems' => fn ($items) => $items
                            ->select(['id', 'visa_group_id', 'transport_service_id', 'transport_sector_id', 'driver_id', 'description', 'scheduled_at', 'terminal', 'quantity', 'passenger_count', 'notes'])
                            ->with(['service:id,name,vehicle_type,number_plate,driver_name,driver_contact', 'sector:id,name,origin,destination', 'driver:id,name,phone'])
                            ->orderBy('scheduled_at'),
                    ]),
                'passengers' => fn ($query) => $query->orderBy('sort_order')->orderBy('created_at'),
                'createdBy:id,name',
            ])
            ->when($this->currentCompanyRole($company->id, request()) === 'member', function ($query) use ($company) {
                $agentId = $this->linkedAgentId($company->id, request());

                return $agentId ? $query->where('agent_id', $agentId) : $query->whereRaw('1 = 0');
            })
            ->findOrFail($voucher);

        if ($this->currentCompanyRole($company->id, request()) === 'member') {
            $record->hotel_stays = collect($record->hotel_stays)->map(function (array $stay) {
                unset($stay['unit_cost_amount'], $stay['total_cost_amount']);

                return $stay;
            })->all();
            $record->makeHidden(['hotel_cost_amount', 'hotel_cost_transaction_id']);
        }

        return Inertia::render('Umrah/Vouchers/Show', [
            'company' => $this->companyPayload($company),
            'voucher' => $record,
            'statuses' => Voucher::STATUSES,
            'serviceBundles' => Voucher::SERVICE_BUNDLES,
            'airlines' => Voucher::AIRLINES,
            'airportCities' => Voucher::AIRPORT_CITIES,
            'agentCapabilities' => $this->voucherCapabilities($company->id, request()),
        ]);
    }

    public function pdf(Request $request, string $companySlug, string $voucher)
    {
        $company = app(CurrentCompany::class)->get();
        $record = Voucher::where('company_id', $company->id)
            ->with([
                'agent:id,name,phone,email,city,country,logo_url',
                'group' => fn ($query) => $query->with([
                    'transportService:id,name,vehicle_type,number_plate,driver_name,driver_contact',
                    'driver:id,name,phone',
                    'transportItems' => fn ($items) => $items
                        ->with(['service:id,name,vehicle_type,number_plate,driver_name,driver_contact', 'sector:id,name,origin,destination', 'driver:id,name,phone'])
                        ->orderBy('scheduled_at'),
                ]),
                'passengers' => fn ($query) => $query->orderBy('sort_order')->orderBy('created_at'),
                'createdBy:id,name',
            ])
            ->when($this->currentCompanyRole($company->id, $request) === 'member', function ($query) use ($company, $request) {
                $agentId = $this->linkedAgentId($company->id, $request);

                return $agentId ? $query->where('agent_id', $agentId) : $query->whereRaw('1 = 0');
            })
            ->findOrFail($voucher);

        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', $record->voucher_number ?: 'voucher').'.pdf';

        return Pdf::loadView('umrah::vouchers.pdf', ['company' => $company, 'voucher' => $record])
            ->setPaper('letter', 'portrait')
            ->download($filename);
    }

    private function companyPayload($company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'base_currency' => $company->base_currency,
            'logo_url' => $company->logo_url,
        ];
    }

    private function currentCompanyRole(string $companyId, Request $request): ?string
    {
        return DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $request->user()?->id)
            ->where('is_active', true)
            ->value('role');
    }

    private function linkedAgentId(string $companyId, Request $request): ?string
    {
        return Agent::where('company_id', $companyId)
            ->where('user_id', $request->user()?->id)
            ->where('is_active', true)
            ->value('id');
    }

    private function voucherCapabilities(string $companyId, Request $request): array
    {
        if ($this->currentCompanyRole($companyId, $request) !== 'member') {
            return ['can_create' => true, 'can_approve' => true, 'can_edit' => true, 'cutoff_hours' => null];
        }
        $agent = Agent::where('company_id', $companyId)->where('user_id', $request->user()?->id)->where('is_active', true)->first();

        return ['can_create' => (bool) $agent?->can_create_voucher, 'can_approve' => (bool) $agent?->can_approve_voucher, 'can_edit' => (bool) $agent?->can_edit_voucher, 'cutoff_hours' => $agent?->voucher_cutoff_hours];
    }
}
