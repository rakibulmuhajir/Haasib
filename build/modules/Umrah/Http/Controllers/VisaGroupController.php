<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Http\Requests\BulkUpdatePassengerStatusRequest;
use App\Modules\Umrah\Http\Requests\ImportMutamersRequest;
use App\Modules\Umrah\Http\Requests\StoreGroupPaymentRequest;
use App\Modules\Umrah\Http\Requests\StorePassengerRequest;
use App\Modules\Umrah\Http\Requests\StoreVisaGroupRequest;
use App\Modules\Umrah\Http\Requests\UpdatePassengerStatusRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\MutamerSheetImportService;
use App\Modules\Umrah\Services\TransportCatalogService;
use App\Modules\Umrah\Services\UmrahCoreService;
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
    ) {}

    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        $this->transportCatalog->ensureDefaultSectors($company->id);
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', '');
        $memberAgentId = $this->memberAgentId($company->id, $request);

        $groups = VisaGroup::where('company_id', $company->id)
            ->with(['agent:id,name', 'vendor:id,name', 'visaService:id,name', 'transportService:id,name,vehicle_type,pax_capacity', 'driver:id,name,phone'])
            ->when($this->isMember($company->id, $request), fn ($q) => $memberAgentId ? $q->where('agent_id', $memberAgentId) : $q->whereRaw('1 = 0'))
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('group_number', 'ilike', "%{$search}%")
                ->orWhereHas('agent', fn ($agent) => $agent->where('name', 'ilike', "%{$search}%"))))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Umrah/Groups/Index', [
            'company' => $this->companyPayload($company),
            'groups' => $groups,
            'statuses' => VisaGroup::STATUSES,
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function create(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        $memberAgentId = $this->memberAgentId($company->id, $request);
        $isMember = $this->isMember($company->id, $request);
        $vendors = VisaVendor::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'vendor_number', 'adult_retail_amount', 'adult_cost_amount', 'child_retail_amount', 'child_cost_amount', 'included_bus_cost_amount']);
        $transportFares = TransportFare::where('company_id', $company->id)->where('is_active', true)->with(['service:id,name,vehicle_type,pax_capacity', 'sector:id,code,name', 'package:id,name'])->orderBy('name')->get();
        if ($isMember) {
            $vendors->each->makeHidden(['adult_cost_amount', 'child_cost_amount', 'included_bus_cost_amount']);
            $transportFares->each->makeHidden(['cost_amount', 'hajj_terminal_cost_amount']);
        }

        return Inertia::render('Umrah/Groups/Create', [
            'company' => $this->companyPayload($company),
            'nextGroupNumber' => $this->service->nextGroupNumber($company->id),
            'agents' => Agent::where('company_id', $company->id)->where('is_active', true)->when($isMember, fn ($q) => $memberAgentId ? $q->whereKey($memberAgentId) : $q->whereRaw('1 = 0'))->orderBy('name')->get(['id', 'name', 'agent_number', 'country']),
            'vendors' => $vendors,
            'transportFares' => $transportFares,
            'statuses' => VisaGroup::STATUSES,
            'passengerStatuses' => Passenger::STATUSES,
            'passengerServiceTypes' => Passenger::SERVICE_TYPES,
            'countries' => Agent::COUNTRIES,
        ]);
    }

    public function store(StoreVisaGroupRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();
        if ($this->isMember($company->id, $request)) {
            $data['agent_id'] = $this->memberAgentId($company->id, $request) ?? abort(403, 'Agent login is not linked.');
        }
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
        $record = VisaGroup::where('company_id', $company->id)
            ->with([
                'agent',
                'vendor',
                'visaService',
                'transportService',
                'driver',
                'transportItems.service',
                'transportItems.sector',
                'transportItems.package.sectors',
                'transportItems.driver',
                'saleTransaction:id,transaction_number',
                'costTransaction:id,transaction_number',
                'passengers' => fn ($query) => $query->orderBy('sort_order')->orderBy('created_at'),
                'payments' => fn ($query) => $query
                    ->with(['account:id,code,name', 'transaction:id,transaction_number'])
                    ->orderByDesc('payment_date')
                    ->orderByDesc('created_at'),
            ])
            ->when($this->isMember($company->id, request()), fn ($q) => ($agentId = $this->memberAgentId($company->id, request())) ? $q->where('agent_id', $agentId) : $q->whereRaw('1 = 0'))
            ->findOrFail($group);

        return Inertia::render('Umrah/Groups/Show', [
            'company' => $this->companyPayload($company),
            'group' => $record,
            'paymentMethods' => GroupPayment::METHODS,
            'passengerStatuses' => Passenger::STATUSES,
            'accounts' => Account::where('company_id', $company->id)
                ->whereIn('subtype', ['bank', 'cash'])
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function addPassenger(StorePassengerRequest $request, string $companySlug, string $group): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaGroup::where('company_id', $company->id)->findOrFail($group);

        $this->service->addPassenger($record, $request->validated());

        return back()->with('success', 'Passenger added successfully.');
    }

    public function updatePassengerStatus(UpdatePassengerStatusRequest $request, string $companySlug, string $group, string $passenger): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaGroup::where('company_id', $company->id)->findOrFail($group);
        $member = Passenger::where('company_id', $company->id)
            ->where('visa_group_id', $record->id)
            ->findOrFail($passenger);

        $member->update(['visa_status' => $request->validated('visa_status')]);

        return back()->with('success', 'Passenger visa status updated successfully.');
    }

    public function bulkUpdatePassengerStatus(BulkUpdatePassengerStatusRequest $request, string $companySlug, string $group): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaGroup::where('company_id', $company->id)->findOrFail($group);
        $data = $request->validated();

        $updated = Passenger::where('company_id', $company->id)
            ->where('visa_group_id', $record->id)
            ->whereIn('id', $data['passenger_ids'])
            ->update(['visa_status' => $data['visa_status']]);

        return back()->with('success', "{$updated} passenger visa status updated successfully.");
    }

    public function addPayment(StoreGroupPaymentRequest $request, string $companySlug, string $group): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaGroup::where('company_id', $company->id)->findOrFail($group);

        $this->service->addPayment($record, $request->validated());

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
        return DB::table('auth.company_user')->where('company_id', $companyId)->where('user_id', $request->user()?->id)->where('is_active', true)->value('role') === 'member';
    }

    private function memberAgentId(string $companyId, Request $request): ?string
    {
        if (! $this->isMember($companyId, $request)) return null;
        return Agent::where('company_id', $companyId)->where('user_id', $request->user()?->id)->where('is_active', true)->value('id');
    }
}
