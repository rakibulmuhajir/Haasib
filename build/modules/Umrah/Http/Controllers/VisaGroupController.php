<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\BulkUpdatePassengerStatusRequest;
use App\Modules\Umrah\Http\Requests\ImportMutamersRequest;
use App\Modules\Umrah\Http\Requests\RemovePassengerRequest;
use App\Modules\Umrah\Http\Requests\StoreGroupPaymentRequest;
use App\Modules\Umrah\Http\Requests\StorePassengerRequest;
use App\Modules\Umrah\Http\Requests\StoreVisaGroupRequest;
use App\Modules\Umrah\Http\Requests\UpdatePassengerRequest;
use App\Modules\Umrah\Http\Requests\UpdatePassengerStatusRequest;
use App\Modules\Umrah\Http\Requests\UpdateVisaGroupRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\ChangeLog;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\MutamerSheetImportService;
use App\Modules\Umrah\Services\TransportCatalogService;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Modules\Umrah\Services\TravelChangeLogger;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CompanyCurrencyOptions;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VisaGroupController extends Controller
{
    public function __construct(
        private UmrahCoreService $service,
        private MutamerSheetImportService $mutamerImporter,
        private TransportCatalogService $transportCatalog,
        private TravelAccessService $access,
        private TravelChangeLogger $changeLogger,
    ) {}

    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(\App\Constants\Permissions::UMRAH_GROUP_VIEW), 403);
        $this->transportCatalog->ensureDefaultSectors($company->id);
        $search = trim((string) $request->input('search', ''));
        $memberAgentId = $this->memberAgentId($company->id, $request);

        $groups = VisaGroup::where('company_id', $company->id)
            ->with(['agent:id,name', 'vendor:id,name', 'visaService:id,name', 'transportService:id,name,vehicle_type,pax_capacity', 'driver:id,name,phone'])
            ->when($this->isMember($company->id, $request), fn ($q) => $memberAgentId ? $q->where('agent_id', $memberAgentId) : $q->whereRaw('1 = 0'))
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('group_number', 'ilike', "%{$search}%")
                ->orWhereHas('passengers', fn ($passenger) => $passenger->where(fn ($match) => $match
                    ->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('passport_number', 'ilike', "%{$search}%")))
                ->orWhereHas('agent', fn ($agent) => $agent->where('name', 'ilike', "%{$search}%"))))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn (VisaGroup $group) => $group->makeHidden('status'))
            ->withQueryString();

        return Inertia::render('Umrah/Groups/Index', [
            'company' => $this->companyPayload($company),
            'groups' => $groups,
            'filters' => ['search' => $search],
            'canViewAccounting' => (bool) $request->user()?->hasCompanyPermission(\App\Constants\Permissions::UMRAH_GROUP_ACCOUNTING_VIEW),
        ]);
    }

    public function create(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(\App\Constants\Permissions::UMRAH_GROUP_CREATE), 403);
        $memberAgentId = $this->memberAgentId($company->id, $request);
        $isMember = $this->isMember($company->id, $request);
        $vendors = VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('vendor_type', '!=', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'vendor_number', 'is_default', 'provides_mandatory_transport', 'mandatory_transport_vendor_id', 'adult_retail_amount', 'adult_cost_amount', 'child_retail_amount', 'child_cost_amount', 'included_bus_cost_amount']);
        $defaultVendor = $vendors->firstWhere('is_default', true);
        $transportVendors = VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderBy('name')->get(['id', 'name', 'is_company_owned']);
        $transportFares = TransportFare::where('company_id', $company->id)->where('is_active', true)->with(['transportVendor:id,name,is_company_owned', 'service:id,name,vehicle_type,pax_capacity', 'sector:id,code,name', 'package:id,name'])->orderBy('name')->get();
        if ($isMember) {
            $vendors = collect();
            $transportVendors = collect();
            $transportFares->each(function (TransportFare $fare) {
                $fare->makeHidden(['transport_vendor_id', 'cost_amount', 'hajj_terminal_cost_amount']);
                $fare->unsetRelation('transportVendor');
            });
        }

        return Inertia::render('Umrah/Groups/Create', [
            'company' => $this->companyPayload($company),
            'nextGroupNumber' => $this->service->nextGroupNumber($company->id),
            'agents' => Agent::where('company_id', $company->id)->where('is_active', true)->when($isMember, fn ($q) => $memberAgentId ? $q->whereKey($memberAgentId) : $q->whereRaw('1 = 0'))->orderBy('name')->get(['id', 'name', 'agent_number', 'country']),
            'vendors' => $vendors,
            'transportVendors' => $transportVendors,
            'defaultVendorId' => $isMember ? null : $defaultVendor?->id,
            'agentVisaPricing' => $isMember && $defaultVendor ? [
                'adult_retail_amount' => (float) $defaultVendor->adult_retail_amount,
                'child_retail_amount' => (float) $defaultVendor->child_retail_amount,
            ] : null,
            'isAgent' => $isMember,
            'transportFares' => $transportFares,
            'passengerStatuses' => Passenger::STATUSES,
            'passengerServiceTypes' => Passenger::SERVICE_TYPES,
            'countries' => Agent::COUNTRIES,
        ]);
    }

    public function store(StoreVisaGroupRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();
        $isAgent = $this->isMember($company->id, $request);
        if ($isAgent) {
            $data['agent_id'] = $this->memberAgentId($company->id, $request) ?? abort(403, 'Agent login is not linked.');
            $data['discount_amount'] = 0;
        }
        $data = $this->service->resolveGroupVendors($company->id, $data, $isAgent);
        $group = $this->service->createGroup($company->id, $data);

        return redirect()->route('umrah.groups.show', ['company' => $company->slug, 'group' => $group->id])
            ->with('success', 'Visa group created successfully.');
    }

    public function importMutamers(ImportMutamersRequest $request): RedirectResponse
    {
        $mutamers = $this->mutamerImporter->import($request->file('mutamers_file'));
        $count = count($mutamers);

        return back()
            ->with('success', "{$count} mutamers imported.")
            ->with('umrah_imported_mutamers', $mutamers);
    }

    public function show(string $companySlug, string $group): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless(request()->user()?->hasCompanyPermission(\App\Constants\Permissions::UMRAH_GROUP_VIEW), 403);
        $isMember = $this->isMember($company->id, request());
        $record = VisaGroup::where('company_id', $company->id)
            ->with([
                'agent',
                'vendor',
                'mandatoryTransportVendor',
                'visaService',
                'transportService',
                'driver',
                'transportItems.service',
                'transportItems.sector',
                'transportItems.package.sectors',
                'transportItems.driver',
                'transportItems.transportVendor',
                'saleTransaction:id,transaction_number',
                'costTransaction:id,transaction_number',
                'passengers' => fn ($query) => $query->orderBy('sort_order')->orderBy('created_at'),
                'paymentAllocations.payment' => fn ($query) => $query
                    ->when($isMember, fn ($paymentQuery) => $paymentQuery->where('direction', GroupPayment::DIRECTION_RECEIVED))
                    ->with(['account:id,code,name', 'transaction:id,transaction_number', 'visaVendor:id,name', 'transportVendor:id,name', 'hotelVendor:id,name']),
            ])
            ->when($isMember, fn ($q) => ($agentId = $this->memberAgentId($company->id, request())) ? $q->where('agent_id', $agentId) : $q->whereRaw('1 = 0'))
            ->findOrFail($group);

        $record->setRelation('payments', $record->paymentAllocations
            ->filter->payment
            ->sortByDesc(fn ($allocation) => $allocation->payment->payment_date?->format('Y-m-d').$allocation->payment->created_at?->toISOString())
            ->map(function ($allocation) {
                $payment = $allocation->payment;
                $payment->setAttribute('allocated_base_amount', $allocation->base_amount);

                return $payment;
            })->values());
        $record->unsetRelation('paymentAllocations');

        if ($isMember) {
            $record->makeHidden(['visa_cost_amount', 'transport_cost_amount', 'hotel_cost_amount', 'profit', 'sale_transaction_id', 'cost_transaction_id']);
            $record->vendor?->makeHidden(['adult_cost_amount', 'child_cost_amount', 'included_bus_cost_amount', 'total_cost', 'total_paid', 'balance']);
            $record->transportItems->each->makeHidden(['unit_cost_amount', 'surcharge_cost_amount', 'total_cost_amount']);
        }
        $record->makeHidden('status');

        $canModify = $isMember
            ? $this->access->agentCanEditGroup($company->id, request()->user(), $record)
            : (bool) request()->user()?->hasCompanyPermission(\App\Constants\Permissions::UMRAH_GROUP_UPDATE);
        $hasStarted = $this->access->groupHasStarted($record);
        $changeLogs = $isMember ? collect() : ChangeLog::where('company_id', $company->id)
            ->where(function ($query) use ($record) {
                $query->where(fn ($entity) => $entity->where('entity_type', 'visa_group')->where('entity_id', $record->id))
                    ->orWhere(fn ($entity) => $entity->where('entity_type', 'passenger')->whereIn('entity_id', $record->passengers->pluck('id')));
            })
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return Inertia::render('Umrah/Groups/Show', [
            'company' => $this->companyPayload($company),
            'group' => $record,
            'paymentMethods' => GroupPayment::METHODS,
            'paymentDirections' => $isMember ? [GroupPayment::DIRECTION_RECEIVED => GroupPayment::DIRECTIONS[GroupPayment::DIRECTION_RECEIVED]] : GroupPayment::DIRECTIONS,
            'currencies' => app(CompanyCurrencyOptions::class)->forCompany($company),
            'passengerStatuses' => Passenger::STATUSES,
            'visaVendors' => $isMember ? [] : VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('vendor_type', '!=', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderBy('name')->get(['id', 'name', 'balance']),
            'transportVendors' => $isMember ? [] : VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderBy('name')->get(['id', 'name', 'balance', 'is_company_owned']),
            'hotelVendors' => $isMember ? [] : HotelVendor::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'balance']),
            'groupCapabilities' => [
                'can_modify' => $canModify,
                'has_started' => $hasStarted,
                'requires_override_reason' => ! $isMember && $canModify && $hasStarted,
                'can_record_payment' => ! $isMember && (bool) request()->user()?->hasCompanyPermission(\App\Constants\Permissions::UMRAH_PAYMENT_CREATE),
                'can_view_accounting' => (bool) request()->user()?->hasCompanyPermission(\App\Constants\Permissions::UMRAH_GROUP_ACCOUNTING_VIEW),
            ],
            'changeLogs' => $changeLogs,
        ]);
    }

    public function edit(Request $request, string $companySlug, string $group): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(\App\Constants\Permissions::UMRAH_GROUP_UPDATE), 403);
        $record = $this->access->scopeAgentRecords(
            VisaGroup::where('company_id', $company->id),
            $company->id,
            $request->user(),
        )->findOrFail($group);

        if ($this->access->isAgentMember($company->id, $request->user())) {
            abort_unless($this->access->agentCanEditGroup($company->id, $request->user(), $record), 403, 'This group cannot be modified by your agent login.');
        }
        $canManageVendors = ! $this->access->isAgentMember($company->id, $request->user());
        $record->makeHidden('status');

        return Inertia::render('Umrah/Groups/Edit', [
            'company' => $this->companyPayload($company),
            'group' => $record,
            'requiresOverrideReason' => ! $this->access->isAgentMember($company->id, $request->user()) && $this->access->groupHasStarted($record),
            'canManageVendors' => $canManageVendors,
            'vendors' => $canManageVendors ? VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('vendor_type', '!=', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default', 'provides_mandatory_transport', 'mandatory_transport_vendor_id']) : [],
            'transportVendors' => $canManageVendors ? VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderBy('name')->get(['id', 'name', 'is_company_owned']) : [],
        ]);
    }

    public function update(UpdateVisaGroupRequest $request, string $companySlug, string $group): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = $this->access->scopeAgentRecords(
            VisaGroup::where('company_id', $company->id),
            $company->id,
            $request->user(),
        )->findOrFail($group);
        $data = $request->validated();
        $isAgent = $this->access->isAgentMember($company->id, $request->user());
        $hadStarted = $this->access->groupHasStarted($record);
        $changes = [
            'name' => $data['name'],
            'travel_date' => $data['travel_date'] ?? null,
            'flight_info' => ['airline' => $data['flight_airline'] ?? null, 'number' => $data['flight_number'] ?? null, 'notes' => $data['flight_notes'] ?? null],
            'hotel_info' => ['makkah' => $data['hotel_makkah'] ?? null, 'madinah' => $data['hotel_madinah'] ?? null, 'notes' => $data['hotel_notes'] ?? null],
            'notes' => $data['notes'] ?? null,
        ];
        if (! $isAgent) {
            $vendorData = $this->service->resolveGroupVendors($company->id, [
                'vendor_id' => $data['vendor_id'] ?? $record->vendor_id,
                'mandatory_transport_vendor_id' => $data['mandatory_transport_vendor_id'] ?? null,
                'transport_mode' => $record->transport_mode,
            ], false);
            $changes['vendor_id'] = $vendorData['vendor_id'];
            $changes['mandatory_transport_vendor_id'] = $vendorData['mandatory_transport_vendor_id'];
        }
        $oldValues = $record->only(array_keys($changes));
        $oldVendorIds = array_filter([$record->vendor_id, $record->mandatory_transport_vendor_id]);

        DB::transaction(function () use ($request, $record, $changes, $oldValues, $data, $hadStarted) {
            $record->update($changes);
            $this->changeLogger->log($request, $record, 'visa_group', 'updated', $oldValues, $changes, $data['override_reason'] ?? null, [
                'after_travel_start' => $hadStarted,
            ]);
        });
        foreach (array_unique([...$oldVendorIds, $record->fresh()->vendor_id, $record->fresh()->mandatory_transport_vendor_id]) as $vendorId) {
            if ($vendorId) {
                $this->service->recalculateVendor($vendorId);
            }
        }

        return redirect()->route('umrah.groups.show', ['company' => $company->slug, 'group' => $record->id])
            ->with('success', 'Visa group updated successfully.');
    }

    public function addPassenger(StorePassengerRequest $request, string $companySlug, string $group): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaGroup::where('company_id', $company->id)->findOrFail($group);
        $data = $request->validated();
        $before = $record->only(['passenger_count', 'visa_sale_amount', 'visa_cost_amount', 'transport_amount', 'total_receivable', 'balance', 'profit']);
        $passenger = $this->service->addPassenger($record, $data);
        $after = $record->fresh()->only(array_keys($before));
        $this->changeLogger->log($request, $passenger, 'passenger', 'created', [], $passenger->only([
            'full_name', 'passport_number', 'nationality', 'date_of_birth', 'imported_age', 'service_type', 'transport_charge_amount', 'visa_status',
        ]), $data['override_reason'] ?? null, ['group_id' => $record->id, 'group_financials_before' => $before, 'group_financials_after' => $after]);

        return back()->with('success', 'Passenger added successfully.');
    }

    public function updatePassengerStatus(UpdatePassengerStatusRequest $request, string $companySlug, string $group, string $passenger): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaGroup::where('company_id', $company->id)->findOrFail($group);
        $member = Passenger::where('company_id', $company->id)
            ->where('visa_group_id', $record->id)
            ->findOrFail($passenger);

        $old = ['visa_status' => $member->visa_status];
        $data = $request->validated();
        $member->update(['visa_status' => $data['visa_status']]);
        $this->changeLogger->log($request, $member, 'passenger', 'status_updated', $old, ['visa_status' => $data['visa_status']], $data['override_reason'] ?? null, ['group_id' => $record->id]);

        return back()->with('success', 'Passenger visa status updated successfully.');
    }

    public function updatePassenger(UpdatePassengerRequest $request, string $companySlug, string $group, string $passenger): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaGroup::where('company_id', $company->id)->findOrFail($group);
        $member = Passenger::where('company_id', $company->id)->where('visa_group_id', $record->id)->findOrFail($passenger);
        $old = $member->only(['full_name', 'passport_number', 'nationality', 'date_of_birth', 'imported_age', 'service_type', 'transport_charge_amount', 'visa_status', 'notes']);
        $updated = $this->service->updatePassenger($record, $member, $request->validated());
        $this->changeLogger->log($request, $updated, 'passenger', 'corrected', $old, $updated->only(array_keys($old)), $request->validated('override_reason'), ['group_id' => $record->id]);

        return back()->with('success', 'Passenger corrected successfully.');
    }

    public function removePassenger(RemovePassengerRequest $request, string $companySlug, string $group, string $passenger): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaGroup::where('company_id', $company->id)->findOrFail($group);
        $member = Passenger::where('company_id', $company->id)->where('visa_group_id', $record->id)->findOrFail($passenger);
        $old = $member->only(['full_name', 'passport_number', 'service_type', 'transport_charge_amount']);
        $reason = $request->validated('reason') ?: 'Passenger removed before travel';
        $this->service->removePassenger($record, $member, $reason);
        $this->changeLogger->log($request, $member, 'passenger', 'removed', $old, ['removed' => true], $reason, ['group_id' => $record->id]);

        return back()->with('success', 'Passenger removed and group totals recalculated.');
    }

    public function bulkUpdatePassengerStatus(BulkUpdatePassengerStatusRequest $request, string $companySlug, string $group): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaGroup::where('company_id', $company->id)->findOrFail($group);
        $data = $request->validated();
        $passengers = Passenger::where('company_id', $company->id)
            ->where('visa_group_id', $record->id)
            ->whereIn('id', $data['passenger_ids'])
            ->get();
        $updated = $passengers->count();
        DB::transaction(function () use ($request, $record, $data, $passengers) {
            foreach ($passengers as $passenger) {
                $old = ['visa_status' => $passenger->visa_status];
                $passenger->update(['visa_status' => $data['visa_status']]);
                $this->changeLogger->log($request, $passenger, 'passenger', 'status_updated', $old, ['visa_status' => $data['visa_status']], $data['override_reason'] ?? null, ['group_id' => $record->id, 'bulk' => true]);
            }
        });

        return back()->with('success', "{$updated} passenger visa status updated successfully.");
    }

    public function addPayment(StoreGroupPaymentRequest $request, string $companySlug, string $group): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaGroup::where('company_id', $company->id)->findOrFail($group);
        $data = $request->validated();
        $data['visa_group_id'] = $record->id;
        $data['agent_id'] = $data['direction'] === GroupPayment::DIRECTION_RECEIVED ? $record->agent_id : null;

        if ($data['direction'] === GroupPayment::DIRECTION_SENT) {
            abort_if($this->isMember($company->id, $request), 403, 'Agent logins cannot record vendor payments.');
        }

        $this->service->addPayment($company->id, $data);

        return back()->with('success', 'Payment recorded successfully.');
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

    private function isMember(string $companyId, Request $request): bool
    {
        return DB::table('auth.company_user')->where('company_id', $companyId)->where('user_id', $request->user()?->id)->where('is_active', true)->value('role') === 'agent';
    }

    private function memberAgentId(string $companyId, Request $request): ?string
    {
        if (! $this->isMember($companyId, $request)) {
            return null;
        }

        return Agent::where('company_id', $companyId)->where('user_id', $request->user()?->id)->where('is_active', true)->value('id');
    }
}
