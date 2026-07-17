<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\StoreTransportFareRequest;
use App\Modules\Umrah\Http\Requests\StoreTransportPackageRequest;
use App\Modules\Umrah\Http\Requests\StoreTransportSectorRequest;
use App\Modules\Umrah\Http\Requests\StoreTransportServiceRequest;
use App\Modules\Umrah\Http\Requests\UpdateMasterDataStatusRequest;
use App\Modules\Umrah\Http\Requests\UpdateTransportPackageRequest;
use App\Modules\Umrah\Http\Requests\UpdateTransportSectorRequest;
use App\Modules\Umrah\Http\Requests\UpdateTransportServiceRequest;
use App\Modules\Umrah\Models\Driver;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\TransportPackage;
use App\Modules\Umrah\Models\TransportSector;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\TransportCatalogService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TransportServiceController extends Controller
{
    public function __construct(private TransportCatalogService $catalog) {}

    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless(request()->user()?->hasCompanyPermission(Permissions::UMRAH_SETTINGS_UPDATE), 403);
        $this->catalog->ensureDefaultSectors($company->id);

        return Inertia::render('Umrah/Settings/TransportServices', [
            'company' => $this->companyPayload($company),
            'transportServices' => TransportService::where('company_id', $company->id)
                ->with('driver:id,name,phone,is_active')
                ->orderBy('name')
                ->get(),
            'drivers' => Driver::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),
            'sectors' => TransportSector::where('company_id', $company->id)->orderBy('sort_order')->get(),
            'packages' => TransportPackage::where('company_id', $company->id)->with('sectors:id,code,name,origin,destination,is_active')->orderBy('name')->get(),
            'fares' => TransportFare::where('company_id', $company->id)->with(['transportVendor:id,name,is_company_owned', 'service:id,name,vehicle_type,pax_capacity', 'sector:id,code,name', 'package:id,name'])->orderBy('name')->get(),
            'transportVendors' => VisaVendor::where('company_id', $company->id)
                ->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'is_company_owned']),
            'chargingBases' => TransportFare::BASES,
        ]);
    }

    public function store(StoreTransportServiceRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        TransportService::create([
            'company_id' => $company->id,
            'driver_id' => $data['driver_id'] ?? null,
            'name' => $data['name'],
            'vehicle_type' => $data['vehicle_type'] ?? null,
            'pax_capacity' => $data['pax_capacity'] ?? null,
            'make' => $data['make'] ?? null,
            'model' => $data['model'] ?? null,
            'color' => $data['color'] ?? null,
            'number_plate' => $data['number_plate'] ?? null,
            'driver_name' => $data['driver_name'] ?? null,
            'driver_contact' => $data['driver_contact'] ?? null,
            'default_sale_amount' => round((float) ($data['default_sale_amount'] ?? 0), 2),
            'default_cost_amount' => round((float) ($data['default_cost_amount'] ?? 0), 2),
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Transport service added successfully.');
    }

    public function update(UpdateTransportServiceRequest $request, string $companySlug, string $transportService): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = TransportService::where('company_id', $company->id)->findOrFail($transportService);
        $data = $request->validated();

        $record->update([
            'driver_id' => $data['driver_id'] ?? null,
            'name' => $data['name'],
            'vehicle_type' => $data['vehicle_type'] ?? null,
            'pax_capacity' => $data['pax_capacity'] ?? null,
            'make' => $data['make'] ?? null,
            'model' => $data['model'] ?? null,
            'color' => $data['color'] ?? null,
            'number_plate' => $data['number_plate'] ?? null,
            'driver_name' => $data['driver_name'] ?? null,
            'driver_contact' => $data['driver_contact'] ?? null,
            'default_sale_amount' => round((float) ($data['default_sale_amount'] ?? 0), 2),
            'default_cost_amount' => round((float) ($data['default_cost_amount'] ?? 0), 2),
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Transport service updated successfully.');
    }

    public function updateStatus(UpdateMasterDataStatusRequest $request, string $companySlug, string $transportService): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = TransportService::where('company_id', $company->id)->with('driver')->findOrFail($transportService);
        $active = (bool) $request->validated('is_active');
        if (! $active && $record->fares()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['transport' => 'Deactivate this vehicle\'s active fares first.']);
        }
        if ($active && $record->driver_id && ! $record->driver?->is_active) {
            throw ValidationException::withMessages(['transport' => 'Reactivate or replace the assigned driver first.']);
        }
        $record->update(['is_active' => $active]);

        return back()->with('success', $active ? 'Transport service reactivated successfully.' : 'Transport service deactivated successfully.');
    }

    public function storeSector(StoreTransportSectorRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $this->catalog->createSector($company->id, $request->validated());

        return back()->with('success', 'Transport sector added successfully.');
    }

    public function storePackage(StoreTransportPackageRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $this->catalog->createPackage($company->id, $request->validated());

        return back()->with('success', 'Journey package added successfully.');
    }

    public function updateSector(UpdateTransportSectorRequest $request, string $companySlug, string $sector): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = TransportSector::where('company_id', $company->id)->findOrFail($sector);
        $this->catalog->updateSector($record, $request->validated());

        return back()->with('success', 'Transport sector updated successfully. Existing group snapshots are unchanged.');
    }

    public function updatePackage(UpdateTransportPackageRequest $request, string $companySlug, string $package): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = TransportPackage::where('company_id', $company->id)->findOrFail($package);
        $this->catalog->updatePackage($record, $request->validated());

        return back()->with('success', 'Journey package updated successfully. Existing group snapshots are unchanged.');
    }

    public function storeFare(StoreTransportFareRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $this->catalog->createFare($company->id, $request->validated());

        return back()->with('success', 'Transport fare added successfully.');
    }

    public function updateFare(StoreTransportFareRequest $request, string $companySlug, string $fare): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = TransportFare::where('company_id', $company->id)->findOrFail($fare);
        $this->catalog->updateFare($record, $request->validated());

        return back()->with('success', 'Transport fare updated successfully. Existing groups keep their original fare snapshots.');
    }

    public function updateSectorStatus(UpdateMasterDataStatusRequest $request, string $companySlug, string $sector): RedirectResponse
    {
        return $this->updateCatalogStatus($request, TransportSector::class, $sector, 'Transport sector');
    }

    public function updatePackageStatus(UpdateMasterDataStatusRequest $request, string $companySlug, string $package): RedirectResponse
    {
        return $this->updateCatalogStatus($request, TransportPackage::class, $package, 'Journey package');
    }

    public function updateFareStatus(UpdateMasterDataStatusRequest $request, string $companySlug, string $fare): RedirectResponse
    {
        return $this->updateCatalogStatus($request, TransportFare::class, $fare, 'Transport fare');
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

    private function updateCatalogStatus(UpdateMasterDataStatusRequest $request, string $modelClass, string $id, string $label): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = $modelClass::where('company_id', $company->id)->findOrFail($id);
        $active = (bool) $request->validated('is_active');
        $this->catalog->setActive($record, $active);

        return back()->with('success', $active ? "{$label} reactivated successfully." : "{$label} deactivated successfully.");
    }
}
