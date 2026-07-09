<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\DestroyVisaServiceRequest;
use App\Modules\Umrah\Http\Requests\StoreVisaServiceRequest;
use App\Modules\Umrah\Http\Requests\UpdateVisaServiceRequest;
use App\Modules\Umrah\Models\VisaService;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VisaServiceController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('Umrah/Settings/VisaServices', [
            'company' => $this->companyPayload($company),
            'visaServices' => VisaService::where('company_id', $company->id)
                ->with('vendor:id,name')
                ->orderBy('name')
                ->get(),
            'vendors' => VisaVendor::where('company_id', $company->id)
                ->where('is_active', true)
                ->whereIn('vendor_type', [VisaVendor::TYPE_GOVERNMENT, VisaVendor::TYPE_VISA_PROVIDER, VisaVendor::TYPE_OTHER])
                ->orderBy('name')
                ->get(['id', 'name', 'vendor_number']),
        ]);
    }

    public function store(StoreVisaServiceRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        VisaService::create([
            'company_id' => $company->id,
            'vendor_id' => $data['vendor_id'] ?? null,
            'name' => $data['name'],
            ...$this->pricingPayload($data),
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Visa service added successfully.');
    }

    public function update(UpdateVisaServiceRequest $request, string $companySlug, string $visaService): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaService::where('company_id', $company->id)->findOrFail($visaService);
        $data = $request->validated();

        $record->update([
            'vendor_id' => $data['vendor_id'] ?? null,
            'name' => $data['name'],
            ...$this->pricingPayload($data),
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Visa service updated successfully.');
    }

    public function destroy(DestroyVisaServiceRequest $request, string $companySlug, string $visaService): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaService::where('company_id', $company->id)->findOrFail($visaService);

        $record->update(['is_active' => false]);
        $record->delete();

        return back()->with('success', 'Visa service removed successfully.');
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
        $adultRetail = $this->money($data['retail_amount'] ?? 0);
        $adultCost = $this->money($data['cost_amount'] ?? 0);

        return [
            'retail_amount' => $adultRetail,
            'cost_amount' => $adultCost,
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
