<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Http\Requests\StorePumpReadingRequest;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\PumpReading;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PumpReadingController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $readings = PumpReading::where('company_id', $company->id)
            ->with(['pump', 'item', 'recordedBy'])
            ->orderByDesc('reading_date')
            ->orderBy('shift')
            ->paginate(50);

        $pumps = Pump::where('company_id', $company->id)
            ->where('is_active', true)
            ->with('tank.linkedItem')
            ->get();

        return Inertia::render('FuelStation/PumpReadings/Index', [
            'readings' => $readings,
            'pumps' => $pumps,
            'shifts' => PumpReading::getShifts(),
        ]);
    }

    public function store(StorePumpReadingRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        // Derive item_id from pump → tank → linked_item_id
        $pump = Pump::with('tank')->find($data['pump_id']);

        if (!$pump?->tank?->linked_item_id) {
            return redirect()->back()->with('error', 'Pump tank does not have a linked fuel item configured.');
        }

        $data['item_id'] = $pump->tank->linked_item_id;

        // Calculate liters dispensed
        $data['liters_dispensed'] = $data['closing_meter'] - $data['opening_meter'];

        PumpReading::create([
            'company_id' => $company->id,
            'recorded_by_user_id' => auth()->id(),
            ...$data,
        ]);

        // Update pump's current meter reading
        $pump->update(['current_meter_reading' => $data['closing_meter']]);

        return redirect()->back()->with('success', 'Pump reading recorded successfully.');
    }
}
