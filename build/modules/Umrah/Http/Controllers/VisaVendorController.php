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
use Illuminate\Support\Facades\DB;
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
            'transportVendors' => VisaVendor::where('company_id', $company->id)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canManageVendors' => (bool) request()->user()?->hasCompanyPermission(Permissions::UMRAH_VENDOR_UPDATE),
        ]);
    }

    public function store(StoreVisaVendorRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        DB::transaction(function () use ($company, $data): void {
            $makeDefault = $data['vendor_type'] !== VisaVendor::TYPE_TRANSPORT_PROVIDER
                && ((bool) ($data['is_default'] ?? false) || ! VisaVendor::where('company_id', $company->id)->where('is_default', true)->exists());
            if ($makeDefault) {
                VisaVendor::where('company_id', $company->id)->update(['is_default' => false]);
            }
            VisaVendor::create($this->vendorPayload($company->id, $data, $makeDefault));
        });

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

        $vendor = DB::transaction(function () use ($company, $data): VisaVendor {
            $makeDefault = $data['vendor_type'] !== VisaVendor::TYPE_TRANSPORT_PROVIDER
                && ((bool) ($data['is_default'] ?? false) || ! VisaVendor::where('company_id', $company->id)->where('is_default', true)->exists());
            if ($makeDefault) {
                VisaVendor::where('company_id', $company->id)->update(['is_default' => false]);
            }

            return VisaVendor::create($this->vendorPayload($company->id, $data, $makeDefault));
        });

        return back()
            ->with('success', "Visa vendor {$vendor->name} created successfully.")
            ->with('created_vendor_id', $vendor->id);
    }

    public function update(UpdateVisaVendorRequest $request, string $companySlug, string $vendor): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaVendor::where('company_id', $company->id)->findOrFail($vendor);
        $data = $request->validated();

        DB::transaction(function () use ($company, $data, $record): void {
            $makeDefault = $data['vendor_type'] !== VisaVendor::TYPE_TRANSPORT_PROVIDER
                && ((bool) ($data['is_default'] ?? false) || $record->is_default);
            if ($makeDefault) {
                VisaVendor::where('company_id', $company->id)->where('id', '!=', $record->id)->update(['is_default' => false]);
            }
            $record->update($this->vendorPayload($company->id, $data, $makeDefault, $record));
        });

        return back()->with('success', 'Visa vendor updated successfully.');
    }

    public function updateStatus(UpdateVisaVendorStatusRequest $request, string $companySlug, string $vendor): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaVendor::where('company_id', $company->id)->findOrFail($vendor);
        $active = (bool) $request->validated('is_active');
        if (! $active && $record->is_default) {
            throw ValidationException::withMessages(['vendor' => 'Choose another default visa vendor before deactivating this one.']);
        }
        if (! $active && VisaVendor::where('company_id', $company->id)->where('mandatory_transport_vendor_id', $record->id)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['vendor' => 'Assign another mandatory transport provider to linked visa vendors first.']);
        }
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

    private function vendorPayload(string $companyId, array $data, bool $isDefault, ?VisaVendor $record = null): array
    {
        $isTransportProvider = $data['vendor_type'] === VisaVendor::TYPE_TRANSPORT_PROVIDER;
        $providesTransport = ! $isTransportProvider && (bool) ($data['provides_mandatory_transport'] ?? false);

        return [
            'company_id' => $companyId,
            'vendor_number' => $data['vendor_number'] ?: ($record?->vendor_number ?? $this->service->nextVendorNumber($companyId)),
            'name' => $data['name'],
            'vendor_type' => $data['vendor_type'],
            'is_company_owned' => $isTransportProvider && (bool) ($data['is_company_owned'] ?? false),
            'is_default' => $isDefault,
            'provides_mandatory_transport' => $providesTransport,
            'mandatory_transport_vendor_id' => ! $isTransportProvider && ! $providesTransport ? ($data['mandatory_transport_vendor_id'] ?? null) : null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
            'notes' => $data['notes'] ?? null,
            ...$this->pricingPayload($data),
            'is_active' => $record?->is_active ?? true,
        ];
    }
}
