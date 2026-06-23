<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\StoreVehicleTypeRequest;
use App\Modules\Umrah\Models\VehicleType;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VehicleTypeController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('Umrah/Settings/VehicleTypes', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'vehicleTypes' => VehicleType::where('company_id', $company->id)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreVehicleTypeRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        VehicleType::create([
            'company_id' => $company->id,
            ...$request->validated(),
            'is_active' => true,
        ]);

        return back()->with('success', 'Vehicle type added successfully.');
    }
}
