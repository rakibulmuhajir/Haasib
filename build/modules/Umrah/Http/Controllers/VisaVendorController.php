<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\StoreVisaVendorRequest;
use App\Modules\Umrah\Http\Requests\UpdateVisaVendorRequest;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VisaVendorController extends Controller
{
    public function __construct(private UmrahCoreService $service) {}

    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('Umrah/Vendors/Index', [
            'company' => $this->companyPayload($company),
            'vendors' => VisaVendor::where('company_id', $company->id)->orderBy('name')->paginate(20),
            'vendorTypes' => VisaVendor::TYPES,
            'nextVendorNumber' => $this->service->nextVendorNumber($company->id),
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
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
            'notes' => $data['notes'] ?? null,
            ...$this->pricingPayload($data),
            'is_active' => true,
        ]);

        return back()->with('success', 'Visa vendor created successfully.');
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
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
            'notes' => $data['notes'] ?? null,
            ...$this->pricingPayload($data),
        ]);

        return back()->with('success', 'Visa vendor updated successfully.');
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
        $adultRetail = $this->money($data['adult_retail_amount'] ?? 0);
        $adultCost = $this->money($data['adult_cost_amount'] ?? 0);

        return [
            'adult_retail_amount' => $adultRetail,
            'adult_cost_amount' => $adultCost,
            'child_retail_amount' => $this->money($data['child_retail_amount'] ?? $adultRetail),
            'child_cost_amount' => $this->money($data['child_cost_amount'] ?? $adultCost),
            'infant_retail_amount' => $this->money($data['infant_retail_amount'] ?? $adultRetail),
            'infant_cost_amount' => $this->money($data['infant_cost_amount'] ?? $adultCost),
        ];
    }

    private function money(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}
