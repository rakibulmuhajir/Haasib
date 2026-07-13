<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\DestroyTransportServiceRequest;
use App\Modules\Umrah\Http\Requests\ManageTransportCatalogRequest;
use App\Modules\Umrah\Http\Requests\StoreTransportFareRequest;
use App\Modules\Umrah\Http\Requests\StoreTransportPackageRequest;
use App\Modules\Umrah\Http\Requests\StoreTransportSectorRequest;
use App\Modules\Umrah\Http\Requests\StoreTransportServiceRequest;
use App\Modules\Umrah\Http\Requests\UpdateTransportServiceRequest;
use App\Modules\Umrah\Models\Driver;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\TransportPackage;
use App\Modules\Umrah\Models\TransportSector;
use App\Modules\Umrah\Services\TransportCatalogService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\ValidationException;

class TransportServiceController extends Controller
{
    public function __construct(private TransportCatalogService $catalog) {}

    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();
        $this->catalog->ensureDefaultSectors($company->id);

        return Inertia::render('Umrah/Settings/TransportServices', [
            'company' => $this->companyPayload($company),
            'transportServices' => TransportService::where('company_id', $company->id)
                ->with('driver:id,name,phone')
                ->orderBy('name')
                ->get(),
            'drivers' => Driver::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'phone']),
            'sectors' => TransportSector::where('company_id', $company->id)->where('is_active', true)->orderBy('sort_order')->get(),
            'packages' => TransportPackage::where('company_id', $company->id)->where('is_active', true)->with('sectors:id,code,name,origin,destination')->orderBy('name')->get(),
            'fares' => TransportFare::where('company_id', $company->id)->with(['service:id,name,vehicle_type,pax_capacity', 'sector:id,code,name', 'package:id,name'])->orderBy('name')->get(),
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

    public function destroy(DestroyTransportServiceRequest $request, string $companySlug, string $transportService): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = TransportService::where('company_id', $company->id)->findOrFail($transportService);

        if ($record->fares()->exists()) {
            throw ValidationException::withMessages(['transport' => 'Remove this vehicle from its fares first.']);
        }

        $record->update(['is_active' => false]);
        $record->delete();

        return back()->with('success', 'Transport service removed successfully.');
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

    public function storeFare(StoreTransportFareRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $this->catalog->createFare($company->id, $request->validated());

        return back()->with('success', 'Transport fare added successfully.');
    }

    public function destroySector(ManageTransportCatalogRequest $request, string $companySlug, string $sector): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = TransportSector::where('company_id', $company->id)->findOrFail($sector);
        $this->catalog->remove($record);

        return back()->with('success', 'Transport sector removed successfully.');
    }

    public function destroyPackage(ManageTransportCatalogRequest $request, string $companySlug, string $package): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = TransportPackage::where('company_id', $company->id)->findOrFail($package);
        $this->catalog->remove($record);

        return back()->with('success', 'Journey package removed successfully.');
    }

    public function destroyFare(ManageTransportCatalogRequest $request, string $companySlug, string $fare): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = TransportFare::where('company_id', $company->id)->findOrFail($fare);
        $this->catalog->remove($record);

        return back()->with('success', 'Transport fare removed successfully.');
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
