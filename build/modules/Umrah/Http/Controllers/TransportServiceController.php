<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\DestroyTransportServiceRequest;
use App\Modules\Umrah\Http\Requests\StoreTransportServiceRequest;
use App\Modules\Umrah\Http\Requests\UpdateTransportServiceRequest;
use App\Modules\Umrah\Models\Driver;
use App\Modules\Umrah\Models\TransportService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TransportServiceController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

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

        $record->update(['is_active' => false]);
        $record->delete();

        return back()->with('success', 'Transport service removed successfully.');
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
