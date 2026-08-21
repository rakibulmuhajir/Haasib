<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\StoreTransportProviderRequest;
use App\Modules\Umrah\Http\Requests\UpdateTransportProviderRequest;
use App\Modules\Umrah\Http\Requests\UpdateVisaVendorStatusRequest;
use App\Modules\Umrah\Http\Requests\VendorStatementRequest;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TransportProviderController extends Controller
{
    public function __construct(private UmrahCoreService $service) {}

    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless(request()->user()?->hasCompanyPermission(Permissions::UMRAH_VENDOR_VIEW), 403);

        return Inertia::render('Umrah/TransportProviders/Index', [
            'company' => $this->companyPayload($company),
            'providers' => VisaVendor::where('company_id', $company->id)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderBy('name')->paginate(20),
            'nextProviderNumber' => $this->service->nextTransportProviderNumber($company->id),
            'canManageProviders' => (bool) request()->user()?->hasCompanyPermission(Permissions::UMRAH_VENDOR_UPDATE),
        ]);
    }

    public function store(StoreTransportProviderRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        VisaVendor::create($this->payload($company->id, $request->validated()));

        return back()->with('success', 'Transport vendor created successfully.');
    }

    public function update(UpdateTransportProviderRequest $request, string $companySlug, string $transportProvider): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $provider = $this->provider($company->id, $transportProvider);
        $provider->update($this->payload($company->id, $request->validated(), $provider));

        return back()->with('success', 'Transport vendor updated successfully.');
    }

    public function updateStatus(UpdateVisaVendorStatusRequest $request, string $companySlug, string $transportProvider): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $provider = $this->provider($company->id, $transportProvider);
        $active = (bool) $request->validated('is_active');
        if (! $active && TransportFare::where('company_id', $company->id)->where('transport_vendor_id', $provider->id)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['vendor' => 'Deactivate this transport vendor\'s active fares first.']);
        }
        $provider->update(['is_active' => $active]);

        return back()->with('success', $active ? 'Transport vendor reactivated.' : 'Transport vendor deactivated.');
    }

    public function show(VendorStatementRequest $request, string $companySlug, string $transportProvider): Response
    {
        $company = app(CurrentCompany::class)->get();
        $provider = $this->provider($company->id, $transportProvider);
        $this->service->recalculateVendor($provider->id);

        return Inertia::render('Umrah/Vendors/Show', [
            'company' => $this->companyPayload($company),
            'vendor' => $provider->fresh(),
            'statement' => $this->service->vendorStatement($provider, $request->validated('date_from'), $request->validated('date_to')),
            'filters' => $request->validated(),
            'backUrl' => "/{$company->slug}/umrah/transport-providers",
            'statementUrl' => "/{$company->slug}/umrah/transport-providers/{$provider->id}/statement.pdf",
            'canCreateRefund' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_REFUND_CREATE),
        ]);
    }

    public function statementPdf(VendorStatementRequest $request, string $companySlug, string $transportProvider)
    {
        $company = app(CurrentCompany::class)->get();
        $provider = $this->provider($company->id, $transportProvider);
        $this->service->recalculateVendor($provider->id);
        $statement = $this->service->vendorStatement($provider->fresh(), $request->validated('date_from'), $request->validated('date_to'));

        return Pdf::loadView('umrah::vendors.statement', compact('company', 'statement') + ['vendor' => $provider->fresh(), 'filters' => $request->validated()])
            ->setPaper('a4')->download($provider->vendor_number.'-statement.pdf');
    }

    private function provider(string $companyId, string $id): VisaVendor
    {
        return VisaVendor::where('company_id', $companyId)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->findOrFail($id);
    }

    private function payload(string $companyId, array $data, ?VisaVendor $record = null): array
    {
        return [
            'company_id' => $companyId,
            'vendor_number' => $data['vendor_number'] ?: ($record?->vendor_number ?? $this->service->nextTransportProviderNumber($companyId)),
            'name' => $data['name'],
            'vendor_type' => VisaVendor::TYPE_TRANSPORT_PROVIDER,
            'is_company_owned' => (bool) ($data['is_company_owned'] ?? false),
            'is_default' => false,
            'provides_mandatory_transport' => false,
            'mandatory_transport_vendor_id' => null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'notes' => $data['notes'] ?? null,
            'adult_retail_amount' => 0,
            'adult_cost_amount' => 0,
            'child_retail_amount' => 0,
            'child_cost_amount' => 0,
            'included_bus_cost_amount' => 0,
            'standard_bus_retail_amount' => round((float) $data['standard_bus_retail_amount'], 2),
            'standard_bus_cost_amount' => round((float) $data['standard_bus_cost_amount'], 2),
            'charge_child_fare' => (bool) $data['charge_child_fare'],
            'is_active' => $record?->is_active ?? true,
        ];
    }

    private function companyPayload($company): array
    {
        return ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency];
    }
}
