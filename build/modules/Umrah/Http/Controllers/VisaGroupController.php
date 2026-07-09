<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Http\Requests\BulkUpdatePassengerStatusRequest;
use App\Modules\Umrah\Http\Requests\StoreGroupPaymentRequest;
use App\Modules\Umrah\Http\Requests\StorePassengerRequest;
use App\Modules\Umrah\Http\Requests\StoreVisaGroupRequest;
use App\Modules\Umrah\Http\Requests\UpdatePassengerStatusRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Driver;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisaGroupController extends Controller
{
    public function __construct(private UmrahCoreService $service) {}

    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', '');

        $groups = VisaGroup::where('company_id', $company->id)
            ->with(['agent:id,name', 'vendor:id,name', 'visaService:id,name', 'transportService:id,name,vehicle_type,pax_capacity', 'driver:id,name,phone'])
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

    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('Umrah/Groups/Create', [
            'company' => $this->companyPayload($company),
            'nextGroupNumber' => $this->service->nextGroupNumber($company->id),
            'agents' => Agent::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'agent_number', 'country']),
            'vendors' => VisaVendor::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'vendor_number', 'adult_retail_amount', 'adult_cost_amount', 'child_retail_amount', 'child_cost_amount', 'infant_retail_amount', 'infant_cost_amount']),
            'transportServices' => TransportService::where('company_id', $company->id)->where('is_active', true)->with('driver:id,name,phone')->orderBy('name')->get(['id', 'driver_id', 'name', 'vehicle_type', 'pax_capacity', 'make', 'model', 'color', 'number_plate', 'driver_name', 'driver_contact', 'default_sale_amount', 'default_cost_amount']),
            'drivers' => Driver::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone']),
            'statuses' => VisaGroup::STATUSES,
            'passengerStatuses' => Passenger::STATUSES,
            'countries' => Agent::COUNTRIES,
        ]);
    }

    public function store(StoreVisaGroupRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $group = $this->service->createGroup($company->id, $request->validated());

        return redirect()->route('umrah.groups.show', ['company' => $company->slug, 'group' => $group->id])
            ->with('success', 'Visa group created successfully.');
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
                'saleTransaction:id,transaction_number',
                'costTransaction:id,transaction_number',
                'passengers' => fn ($query) => $query->orderBy('sort_order')->orderBy('created_at'),
                'payments' => fn ($query) => $query
                    ->with(['account:id,code,name', 'transaction:id,transaction_number'])
                    ->orderByDesc('payment_date')
                    ->orderByDesc('created_at'),
            ])
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
}
