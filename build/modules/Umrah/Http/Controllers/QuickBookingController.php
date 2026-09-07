<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Commands\CreateQuickBooking;
use App\Modules\Umrah\Http\Requests\StoreQuickBookingRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Hotel;
use App\Modules\Umrah\Models\HotelRoomRate;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\TransportCatalogService;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class QuickBookingController extends Controller
{
    public function __construct(
        private readonly UmrahCoreService $core,
        private readonly TransportCatalogService $transportCatalog,
        private readonly TravelAccessService $access,
    ) {}

    public function create(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        $user = $request->user();
        abort_unless($user?->hasCompanyPermission(Permissions::UMRAH_GROUP_CREATE), 403);

        $this->transportCatalog->ensureDefaultSectors($company->id);
        $isAgent = $this->access->isAgentMember($company->id, $user);
        $linkedAgentId = $isAgent
            ? Agent::where('company_id', $company->id)->where('user_id', $user?->id)->where('is_active', true)->value('id')
            : null;

        $agents = Agent::where('company_id', $company->id)
            ->where('is_active', true)
            ->when($isAgent, fn ($query) => $linkedAgentId ? $query->whereKey($linkedAgentId) : $query->whereRaw('1 = 0'))
            ->orderByName()
            // Agent names live on the linked customer. Keep customer_id when
            // narrowing columns so the model can eager-load that party.
            ->get(['id', 'customer_id', 'agent_number', 'country']);

        $visaVendor = VisaVendor::where('company_id', $company->id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->where('service_type', '!=', VisaVendor::SERVICE_TRANSPORT_PROVIDER)
            ->withCompleteVisaRates()
            ->first();
        $transportProviderId = $visaVendor?->resolvedMandatoryTransportVendorId()
            ?: VisaVendor::where('company_id', $company->id)
                ->where('is_active', true)
                ->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)
                ->orderBy('created_at')
                ->value('id');
        $transportProvider = $transportProviderId
            ? VisaVendor::where('company_id', $company->id)
                ->where('is_active', true)
                ->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)
                ->find($transportProviderId)
            : null;

        $fares = TransportFare::where('company_id', $company->id)
            ->where('is_active', true)
            ->with(['service:id,name,vehicle_type,pax_capacity', 'sector:id,code,name', 'package:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (TransportFare $fare) => [
                'id' => $fare->id,
                'name' => $fare->name,
                'charging_basis' => $fare->charging_basis,
                'sale_amount' => (float) $fare->sale_amount,
                'hajj_terminal_sale_amount' => (float) $fare->hajj_terminal_sale_amount,
                'service' => $fare->service ? [
                    'name' => $fare->service->name,
                    'vehicle_type' => $fare->service->vehicle_type,
                    'pax_capacity' => $fare->service->pax_capacity,
                ] : null,
                'route' => $fare->sector?->name ?: $fare->package?->name,
            ]);

        return Inertia::render('Umrah/QuickBooking/Create', [
            'company' => [
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'nextBookingNumber' => $this->core->nextGroupNumber($company->id),
            'agents' => $agents,
            'isAgent' => $isAgent,
            'canBuildVoucher' => (bool) $user?->hasCompanyPermission(Permissions::UMRAH_VOUCHER_CREATE)
                && (! $isAgent || (bool) Agent::where('company_id', $company->id)->whereKey($linkedAgentId)->value('can_create_voucher')),
            'pricing' => [
                'visa' => $visaVendor ? [
                    'adult' => (float) $visaVendor->adult_retail_amount,
                    'child' => (float) $visaVendor->child_retail_amount,
                ] : null,
                'standard_transport' => $transportProvider ? [
                    'per_passenger' => (float) $transportProvider->standard_bus_retail_amount,
                    'charge_child_fare' => (bool) $transportProvider->charge_child_fare,
                ] : null,
            ],
            'transportFares' => $fares,
            'hotels' => Hotel::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('city')
                ->orderBy('name')
                ->get(['id', 'name', 'city']),
            'roomTypes' => HotelRoomRate::TYPES,
            'countries' => Agent::COUNTRIES,
            'passengerStatuses' => Passenger::STATUSES,
            'setup' => [
                'has_visa_rate' => $visaVendor !== null,
                'has_standard_transport_rate' => $transportProvider !== null,
                'has_specialized_transport_rate' => $fares->isNotEmpty(),
            ],
        ]);
    }

    public function store(StoreQuickBookingRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        if ($this->access->isAgentMember($company->id, $request->user())) {
            $data['agent_id'] = Agent::where('company_id', $company->id)
                ->where('user_id', $request->user()?->id)
                ->where('is_active', true)
                ->value('id') ?? abort(403, 'Agent login is not linked.');
        }

        try {
            $group = Bus::dispatch(new CreateQuickBooking($company->id, $data));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'Booking could not be created. Check the service setup and try again.');
        }

        if ($data['next_step'] === 'voucher') {
            return redirect()->route('umrah.vouchers.create', [
                'company' => $company->slug,
                'group_id' => $group->id,
            ])->with('success', 'Booking saved. Complete the voucher.');
        }

        return redirect()->route('umrah.groups.show', [
            'company' => $company->slug,
            'group' => $group->id,
        ])->with('success', 'Booking created.');
    }
}
