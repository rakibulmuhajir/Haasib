<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\StoreVisaVendorRequest;
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
            'is_active' => true,
        ]);

        return back()->with('success', 'Visa vendor created successfully.');
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
