<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\StoreTransportServiceRequest;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VehicleType;
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
                ->with('vehicleType:id,name,seats')
                ->orderBy('name')
                ->get(),
            'vehicleTypes' => VehicleType::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'seats']),
        ]);
    }

    public function store(StoreTransportServiceRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        TransportService::create([
            'company_id' => $company->id,
            'vehicle_type_id' => $data['vehicle_type_id'] ?? null,
            'name' => $data['name'],
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
