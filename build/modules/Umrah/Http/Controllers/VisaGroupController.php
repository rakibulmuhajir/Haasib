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
            ->with(['agent:id,customer_id', 'vendor:id,vendor_id', 'visaService:id,name', 'transportService:id,name,vehicle_type,pax_capacity', 'driver:id,name,phone'])
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
        $hidesFinancials = $this->access->hidesFinancialData($company->id, $request->user());
        $vendors = VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('service_type', '!=', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->withCompleteVisaRates()->orderByDesc('is_default')->orderByName()->get(['id', 'vendor_id', 'vendor_number', 'is_default', 'adult_retail_amount', 'adult_cost_amount', 'child_retail_amount', 'child_cost_amount']);
        $defaultVendor = $vendors->firstWhere('is_default', true);
        $transportVendors = VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->orderByName()->get(['id', 'vendor_id', 'is_company_owned', 'standard_bus_retail_amount', 'standard_bus_cost_amount', 'charge_child_fare']);
        $transportFares = TransportFare::where('company_id', $company->id)->where('is_active', true)->with(['transportVendor:id,vendor_id,is_company_owned', 'service:id,name,vehicle_type,pax_capacity', 'sector:id,code,name', 'package:id,name'])->orderBy('name')->get();
        if ($hidesFinancials) {
            $vendors->each->makeHidden([
                'adult_retail_amount',
                'adult_cost_amount',
                'child_retail_amount',
                'child_cost_amount',
            ]);
            // Who the transport provider is is not financial data -- its rates
            // are. Emptying the whole list hid the money and also removed the
            // only way to satisfy mandatory_transport_vendor_id, which the
            // backend requires on every standard-bus group, so operations staff
            // could not create one at all. Hide the amounts, keep the names.
            $transportVendors->each->makeHidden([
                'standard_bus_retail_amount',
                'standard_bus_cost_amount',
            ]);
            $transportFares->each(function (TransportFare $fare) {
                $fare->makeHidden([
                    'transport_vendor_id',
                    'sale_amount',
                    'cost_amount',
                    'hajj_terminal_sale_amount',
                    'hajj_terminal_cost_amount',
                ]);
                $fare->unsetRelation('transportVendor');
            });
        }

        return Inertia::render('Umrah/Groups/Create', [
            'company' => $this->companyPayload($company),
            'nextGroupNumber' => $this->service->nextGroupNumber($company->id),
            'agents' => Agent::where('company_id', $company->id)->where('is_active', true)->when($isMember, fn ($q) => $memberAgentId ? $q->whereKey($memberAgentId) : $q->whereRaw('1 = 0'))->orderByName()->get(['id', 'customer_id', 'agent_number', 'country']),
            'vendors' => $vendors,
            'transportVendors' => $transportVendors,
            'defaultVendorId' => $isMember ? null : $defaultVendor?->id,
            'agentVisaPricing' => $isMember && $defaultVendor ? [
                'adult_retail_amount' => (float) $defaultVendor->adult_retail_amount,
                'child_retail_amount' => (float) $defaultVendor->child_retail_amount,
            ] : null,
            'isAgent' => $isMember,
            'isOperations' => $this->access->companyRole($company->id, $request->user()) === 'operations',
            'transportFares' => $transportFares,
            'passengerStatuses' => Passenger::STATUSES,
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
        $hidesFinancials = $this->access->hidesFinancialData($company->id, request()->user());
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
                    ->with(['account:id,code,name', 'transaction:id,transaction_number', 'visaVendor:id,vendor_id', 'transportVendor:id,vendor_id', 'hotelVendor:id,name']),
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

        if ($hidesFinancials) {
            $record->makeHidden([
                'visa_sale_amount',
                'visa_cost_amount',
                'transport_amount',
                'transport_cost_amount',
                'hotel_sale_amount',
                'hotel_cost_amount',
                'total_receivable',
                'balance',
                'profit',
                'sale_transaction_id',
                'cost_transaction_id',
            ]);
            $record->vendor?->makeHidden([
                'adult_retail_amount',
                'adult_cost_amount',
                'child_retail_amount',
                'child_cost_amount',
                'included_bus_cost_amount',
                'total_cost',
                'total_paid',
                'balance',
            ]);
            $record->transportItems->each->makeHidden([
                'unit_sale_amount',
                'unit_cost_amount',
                'surcharge_sale_amount',
                'surcharge_cost_amount',
                'total_sale_amount',
                'total_cost_amount',
            ]);
            $record->setRelation('payments', collect());
        }
        $record->makeHidden('status');

        $canModify = $isMember
            ? $this->access->agentCanEditGroup($company->id, request()->user(), $record)
            : (bool) request()->user()?->hasCompanyPermission(\App\Constants\Permissions::UMRAH_GROUP_UPDATE);
        $hasStarted = $this->access->groupHasStarted($record);
        $changeLogs = $hidesFinancials ? collect() : ChangeLog::where('company_id', $company->id)
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
            'visaVendors' => $isMember ? [] : VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('service_type', '!=', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->orderByName()->get(['id', 'vendor_id', 'balance']),
            'transportVendors' => $isMember ? [] : VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->orderByName()->get(['id', 'vendor_id', 'balance', 'is_company_owned']),
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
        // The edit form seeds its vehicle rows from these, so an untouched
        // save writes back what the group already has rather than clearing it.
        $record->load('transportItems');

        return Inertia::render('Umrah/Groups/Edit', [
            'company' => $this->companyPayload($company),
            'group' => $record,
            'requiresOverrideReason' => ! $this->access->isAgentMember($company->id, $request->user()) && $this->access->groupHasStarted($record),
            'canManageVendors' => $canManageVendors,
            'vendors' => $canManageVendors ? VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('service_type', '!=', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->withCompleteVisaRates()->orderByDesc('is_default')->orderByName()->get(['id', 'vendor_id', 'is_default']) : [],
            'transportVendors' => $canManageVendors ? VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->orderByName()->get(['id', 'vendor_id', 'is_company_owned', 'standard_bus_retail_amount', 'standard_bus_cost_amount', 'charge_child_fare']) : [],
            // Only a specialized group can edit vehicles, and only a
            // non-agent prices them -- an agent member gets an empty list
            // and no transport section, the same way they get no vendors.
            'transportFares' => $canManageVendors && $record->transport_mode === VisaGroup::TRANSPORT_SPECIALIZED
                ? TransportFare::where('company_id', $company->id)->where('is_active', true)
                    ->with(['transportVendor:id,vendor_id', 'service:id,name,vehicle_type,pax_capacity', 'sector:id,code,name', 'package:id,name'])
                    ->orderBy('name')->get()
                : [],
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
            'transport_mode' => $data['transport_mode'],
            'includes_visa' => (bool) ($data['includes_visa'] ?? $record->includes_visa),
            'transport_required' => $data['transport_mode'] !== VisaGroup::TRANSPORT_NONE,
            'travel_date' => $data['travel_date'] ?? null,
            'flight_info' => ['airline' => $data['flight_airline'] ?? null, 'number' => $data['flight_number'] ?? null, 'notes' => $data['flight_notes'] ?? null],
            'hotel_info' => ['makkah' => $data['hotel_makkah'] ?? null, 'madinah' => $data['hotel_madinah'] ?? null, 'notes' => $data['hotel_notes'] ?? null],
            'notes' => $data['notes'] ?? null,
        ];
        if (! $isAgent) {
            $vendorData = $this->service->resolveGroupVendors($company->id, [
                'vendor_id' => $data['vendor_id'] ?? $record->vendor_id,
                'mandatory_transport_vendor_id' => $data['mandatory_transport_vendor_id'] ?? null,
                'transport_mode' => $data['transport_mode'],
            ], false);
            // A group that no longer sells a visa keeps no visa vendor.
            // resolveGroupVendors always resolves one (falling back to the
            // record's previous vendor) because it also carries the
            // mandatory-transport lookup for standard_bus -- so the visa
            // vendor half of its answer is only trusted when includes_visa
            // is still true.
            $changes['vendor_id'] = $changes['includes_visa'] ? $vendorData['vendor_id'] : null;
            $changes['mandatory_transport_vendor_id'] = $vendorData['mandatory_transport_vendor_id'];
        }
        // Flipping includes_visa is the whole point of this edit path, and a
        // group that no longer sells a visa cannot be left holding what it
        // charged for one -- recalculateGroup() below folds visa_sale_amount
        // and visa_cost_amount straight into total_receivable/balance/profit
        // regardless of includes_visa, unlike summary()'s display line which
        // already gates on it.
        $includesVisaChanged = $changes['includes_visa'] !== $record->includes_visa;
        if ($includesVisaChanged && ! $changes['includes_visa']) {
            $changes['visa_sale_amount'] = 0;
            $changes['visa_cost_amount'] = 0;
        }
        $oldValues = $record->only(array_keys($changes));
        $oldVendorIds = array_filter([
            $record->vendor_id,
            $record->mandatory_transport_vendor_id,
            ...$record->transportItems()->pluck('transport_vendor_id')->all(),
        ]);

        $financialBefore = $record->only(['visa_sale_amount', 'transport_amount', 'discount_amount', 'total_receivable', 'visa_cost_amount', 'transport_cost_amount']);
        DB::transaction(function () use ($request, $record, &$changes, $oldValues, $data, $hadStarted, $financialBefore, $includesVisaChanged, $isAgent) {
            if ($includesVisaChanged) {
                // The group decided once what it sells; every passenger already
                // in it was created agreeing with the old answer. Leaving their
                // service_type behind after the group's answer changes is
                // exactly the group/passenger mismatch that once billed a
                // self-arranged group for a bus it never bought.
                $record->passengers()->update([
                    'service_type' => $changes['includes_visa']
                        ? Passenger::SERVICE_VISA_TRANSPORT
                        : Passenger::SERVICE_TRANSPORT_ONLY,
                ]);
            }
            if ($data['transport_mode'] === VisaGroup::TRANSPORT_NONE) {
                $this->service->removeGroupTransport($record);
                $changes = [...$changes, ...$record->fresh()->only([
                    'mandatory_transport_vendor_id', 'transport_service_id', 'driver_id', 'transport_quantity',
                    'transport_pax_capacity', 'standard_bus_retail_amount', 'standard_bus_cost_amount',
                    'standard_bus_charge_child_fare', 'standard_bus_billable_passenger_count',
                    'mandatory_transport_cost_amount', 'transport_amount', 'transport_cost_amount',
                ])];
            } elseif ($data['transport_mode'] === VisaGroup::TRANSPORT_STANDARD_BUS) {
                $provider = VisaVendor::where('company_id', $record->company_id)->findOrFail($changes['mandatory_transport_vendor_id']);
                // Keep the rate this group was sold at unless the provider
                // itself changed. Otherwise editing a travel date re-costs
                // the bus at whatever the provider charges today.
                $keepStoredRates = $changes['mandatory_transport_vendor_id'] === $record->mandatory_transport_vendor_id;
                $pricing = $this->service->standardBusPricingForGroup($record, $provider, $keepStoredRates);
                $changes = [...$changes,
                    'standard_bus_retail_amount' => $pricing['retail_rate'],
                    'standard_bus_cost_amount' => $pricing['cost_rate'],
                    'standard_bus_charge_child_fare' => $pricing['charge_child_fare'],
                    'standard_bus_billable_passenger_count' => $pricing['passenger_count'],
                    'mandatory_transport_cost_amount' => $pricing['cost'],
                    'transport_amount' => round($pricing['sale'] + (float) $record->passengers()->where('service_type', Passenger::SERVICE_TRANSPORT_ONLY)->sum('transport_charge_amount'), 2),
                    'transport_cost_amount' => $pricing['cost'],
                ];
            } elseif ($data['transport_mode'] === VisaGroup::TRANSPORT_SPECIALIZED && ! $isAgent && array_key_exists('transport_items', $data)) {
                // An agent member reaches this route for their own groups but
                // never prices the work -- the vendor block above is skipped
                // for the same reason, and the edit page hides the vehicles
                // from them. An absent list means the operator changed
                // something else and the vehicles stand as they are.
                $changes = [...$changes, ...$this->service->syncGroupTransportItems($record, $data['transport_items'])];
            }
            $record->update($changes);
            $this->service->recalculateGroup($record->fresh());
            $this->service->postGroupFinancialAdjustment($record->fresh(), $financialBefore, $data['override_reason'] ?? 'Transport option updated');
            $this->changeLogger->log($request, $record, 'visa_group', 'updated', $oldValues, $changes, $data['override_reason'] ?? null, [
                'after_travel_start' => $hadStarted,
            ]);
        });
        // $oldVendorIds already carries the transport suppliers the group had
        // before this save. Re-reading them afterwards is what balances a
        // vehicle moved from one supplier to another: without it the supplier
        // that gained the work keeps a payable that never rose.
        $after = $record->fresh();
        foreach (array_unique([...$oldVendorIds, $after->vendor_id, $after->mandatory_transport_vendor_id, ...$after->transportItems()->pluck('transport_vendor_id')->all()]) as $vendorId) {
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
