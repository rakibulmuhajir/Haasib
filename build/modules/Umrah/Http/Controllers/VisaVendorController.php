<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\StoreVisaVendorRequest;
use App\Modules\Umrah\Http\Requests\UpdateVisaVendorRequest;
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

class VisaVendorController extends Controller
{
    public function __construct(private UmrahCoreService $service) {}

    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless(request()->user()?->hasCompanyPermission(Permissions::UMRAH_VENDOR_VIEW), 403);

        return Inertia::render('Umrah/Vendors/Index', [
            'company' => $this->companyPayload($company),
            'vendors' => VisaVendor::where('company_id', $company->id)->orderBy('name')->paginate(20),
            'vendorTypes' => VisaVendor::TYPES,
            'nextVendorNumber' => $this->service->nextVendorNumber($company->id),
            'canManageVendors' => (bool) request()->user()?->hasCompanyPermission(Permissions::UMRAH_VENDOR_UPDATE),
        ]);
    }

    public function store(StoreVisaVendorRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        VisaVendor::create([
            'company_id' => $company->id,
            'vendor_number' => $data['vendor_number'] ?: $this->service->nextVendorNumber($company->id),
            'name' => $data['name'],
            'vendor_type' => $data['vendor_type'],
            'is_company_owned' => $data['vendor_type'] === VisaVendor::TYPE_TRANSPORT_PROVIDER && (bool) ($data['is_company_owned'] ?? false),
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
            'notes' => $data['notes'] ?? null,
            ...$this->pricingPayload($data),
            'is_active' => true,
        ]);

        return back()->with('success', 'Visa vendor created successfully.');
    }

    public function show(VendorStatementRequest $request, string $companySlug, string $vendor): Response
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaVendor::where('company_id', $company->id)->findOrFail($vendor);
        $this->service->recalculateVendor($record->id);

        return Inertia::render('Umrah/Vendors/Show', [
            'company' => $this->companyPayload($company),
            'vendor' => $record->fresh(),
            'statement' => $this->service->vendorStatement($record, $request->validated('date_from'), $request->validated('date_to')),
            'filters' => $request->validated(),
        ]);
    }

    public function statementPdf(VendorStatementRequest $request, string $companySlug, string $vendor)
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaVendor::where('company_id', $company->id)->findOrFail($vendor);
        $this->service->recalculateVendor($record->id);
        $statement = $this->service->vendorStatement($record->fresh(), $request->validated('date_from'), $request->validated('date_to'));

        return Pdf::loadView('umrah::vendors.statement', [
            'company' => $company,
            'vendor' => $record->fresh(),
            'statement' => $statement,
            'filters' => $request->validated(),
        ])->setPaper('a4')->download($record->vendor_number.'-statement.pdf');
    }

    public function quickStore(StoreVisaVendorRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        $vendor = VisaVendor::create([
            'company_id' => $company->id,
            'vendor_number' => $data['vendor_number'] ?: $this->service->nextVendorNumber($company->id),
            'name' => $data['name'],
            'vendor_type' => $data['vendor_type'],
            'is_company_owned' => $data['vendor_type'] === VisaVendor::TYPE_TRANSPORT_PROVIDER && (bool) ($data['is_company_owned'] ?? false),
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
            'notes' => $data['notes'] ?? null,
            ...$this->pricingPayload($data),
            'is_active' => true,
        ]);

        return back()
            ->with('success', "Visa vendor {$vendor->name} created successfully.")
            ->with('created_vendor_id', $vendor->id);
    }

    public function update(UpdateVisaVendorRequest $request, string $companySlug, string $vendor): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaVendor::where('company_id', $company->id)->findOrFail($vendor);
        $data = $request->validated();

        $record->update([
            'vendor_number' => $data['vendor_number'] ?: $record->vendor_number,
            'name' => $data['name'],
            'vendor_type' => $data['vendor_type'],
            'is_company_owned' => $data['vendor_type'] === VisaVendor::TYPE_TRANSPORT_PROVIDER && (bool) ($data['is_company_owned'] ?? false),
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
            'notes' => $data['notes'] ?? null,
            ...$this->pricingPayload($data),
        ]);

        return back()->with('success', 'Visa vendor updated successfully.');
    }

    public function updateStatus(UpdateVisaVendorStatusRequest $request, string $companySlug, string $vendor): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaVendor::where('company_id', $company->id)->findOrFail($vendor);
        $active = (bool) $request->validated('is_active');
        if (! $active && TransportFare::where('company_id', $company->id)->where('transport_vendor_id', $record->id)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['vendor' => 'Deactivate this transport provider\'s active fares first.']);
        }
        $record->update(['is_active' => $active]);

        return back()->with('success', $active ? 'Vendor reactivated successfully.' : 'Vendor deactivated successfully. Historical balances and statements remain available.');
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

    private function pricingPayload(array $data): array
    {
        if (($data['vendor_type'] ?? null) === VisaVendor::TYPE_TRANSPORT_PROVIDER) {
            return [
                'adult_retail_amount' => 0,
                'adult_cost_amount' => 0,
                'child_retail_amount' => 0,
                'child_cost_amount' => 0,
                'included_bus_cost_amount' => 0,
            ];
        }

        $adultRetail = $this->money($data['adult_retail_amount'] ?? 0);
        $adultCost = $this->money($data['adult_cost_amount'] ?? 0);

        return [
            'adult_retail_amount' => $adultRetail,
            'adult_cost_amount' => $adultCost,
            'child_retail_amount' => $this->money($data['child_retail_amount'] ?? $adultRetail),
            'child_cost_amount' => $this->money($data['child_cost_amount'] ?? $adultCost),
            'included_bus_cost_amount' => $this->money($data['included_bus_cost_amount'] ?? 50),
        ];
    }

    private function money(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}
