<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\StoreVoucherRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VoucherController extends Controller
{
    public function __construct(private UmrahCoreService $service) {}

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
                ->get(['id', 'full_name', 'passport_number', 'nationality', 'visa_status']);

            $assignedPassengers = Passenger::where('company_id', $company->id)
                ->where('visa_group_id', $selectedGroup->id)
                ->whereIn('id', $assignedIds)
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->get(['id', 'full_name', 'passport_number', 'nationality', 'visa_status']);
        }

        return Inertia::render('Umrah/Vouchers/Create', [
            'company' => $this->companyPayload($company),
            'nextVoucherNumber' => $this->service->nextVoucherNumber($company->id),
            'groups' => $groups,
            'selectedGroup' => $selectedGroup,
            'availablePassengers' => $availablePassengers->values(),
            'assignedPassengers' => $assignedPassengers->values(),
            'statuses' => Voucher::STATUSES,
            'airlines' => Voucher::AIRLINES,
            'airportCities' => Voucher::AIRPORT_CITIES,
        ]);
    }

    public function store(StoreVoucherRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();
        $group = VisaGroup::where('company_id', $company->id)->findOrFail($data['visa_group_id']);

        $voucher = DB::transaction(function () use ($company, $data, $group, $request) {
            $voucher = Voucher::create([
                'company_id' => $company->id,
                'visa_group_id' => $group->id,
                'agent_id' => $group->agent_id,
                'voucher_number' => $data['voucher_number'] ?: $this->service->nextVoucherNumber($company->id),
                'title' => $data['title'],
                'status' => $data['status'] ?? Voucher::STATUS_ISSUED,
                'onward_airline' => $data['onward_airline'],
                'onward_flight_number' => $data['onward_flight_number'] ?? null,
                'onward_departure_city' => $data['onward_departure_city'],
                'onward_arrival_city' => $data['onward_arrival_city'],
                'onward_departure_at' => $data['onward_departure_at'],
                'onward_arrival_at' => $data['onward_arrival_at'],
                'return_airline' => $data['return_airline'],
                'return_flight_number' => $data['return_flight_number'] ?? null,
                'return_departure_city' => $data['return_departure_city'],
                'return_arrival_city' => $data['return_arrival_city'],
                'return_departure_at' => $data['return_departure_at'],
                'return_arrival_at' => $data['return_arrival_at'],
                'hotel_stays' => array_values($data['hotel_stays'] ?? []),
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $request->user()?->id,
            ]);

            foreach (array_values(array_unique($data['passenger_ids'])) as $passengerId) {
                VoucherPassenger::create([
                    'company_id' => $company->id,
                    'voucher_id' => $voucher->id,
                    'visa_group_id' => $group->id,
                    'passenger_id' => $passengerId,
                ]);
            }

            return $voucher;
        });

        return redirect()->route('umrah.vouchers.show', ['company' => $company->slug, 'voucher' => $voucher->id])
            ->with('success', 'Voucher created successfully.');
    }

    public function show(string $companySlug, string $voucher): Response
    {
        $company = app(CurrentCompany::class)->get();
        $record = Voucher::where('company_id', $company->id)
            ->with([
                'agent:id,name,phone,email,city,country',
                'group:id,group_number,name,travel_date,passenger_count',
                'passengers' => fn ($query) => $query->orderBy('sort_order')->orderBy('created_at'),
                'createdBy:id,name',
            ])
            ->when($this->currentCompanyRole($company->id, request()) === 'member', function ($query) use ($company) {
                $agentId = $this->linkedAgentId($company->id, request());

                return $agentId ? $query->where('agent_id', $agentId) : $query->whereRaw('1 = 0');
            })
            ->findOrFail($voucher);

        return Inertia::render('Umrah/Vouchers/Show', [
            'company' => $this->companyPayload($company),
            'voucher' => $record,
            'statuses' => Voucher::STATUSES,
            'airlines' => Voucher::AIRLINES,
            'airportCities' => Voucher::AIRPORT_CITIES,
        ]);
    }

    private function companyPayload($company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'base_currency' => $company->base_currency,
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
}
