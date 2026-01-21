<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Http\Requests\StoreTankReadingRequest;
use App\Modules\FuelStation\Http\Requests\UpdateTankReadingRequest;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\TankReadingService;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TankReadingController extends Controller
{
    public function __construct(
        private TankReadingService $tankReadingService
    ) {}

    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $readings = TankReading::where('company_id', $company->id)
            ->with(['tank.linkedItem', 'item', 'recordedBy', 'confirmedBy'])
            ->orderByDesc('reading_date')
            ->paginate(50);

        $tanks = Warehouse::where('company_id', $company->id)
            ->where('warehouse_type', 'tank')
            ->with('linkedItem')
            ->get();

        return Inertia::render('FuelStation/TankReadings/Index', [
            'readings' => $readings,
            'tanks' => $tanks,
            'varianceReasons' => TankReading::getVarianceReasons(),
            'readingTypes' => TankReading::getReadingTypes(),
        ]);
    }

    public function store(StoreTankReadingRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        // Get tank and derive item_id
        $tank = Warehouse::find($data['tank_id']);

        if (!$tank || $tank->warehouse_type !== 'tank') {
            return redirect()->back()->with('error', 'Invalid tank selected.');
        }

        if (!$tank->linked_item_id) {
            return redirect()->back()->with('error', 'Tank does not have a linked fuel item. Please configure the tank first.');
        }

        $data['item_id'] = $tank->linked_item_id;

        // Calculate system expected liters
        $data['system_calculated_liters'] = $this->tankReadingService->calculateSystemLiters(
            $data['tank_id'],
            $data['item_id'],
            $data['reading_date']
        );

        // Calculate variance
        $data['variance_liters'] = $data['dip_measurement_liters'] - $data['system_calculated_liters'];

        if ($data['variance_liters'] < 0) {
            $data['variance_type'] = TankReading::VARIANCE_LOSS;
        } elseif ($data['variance_liters'] > 0) {
            $data['variance_type'] = TankReading::VARIANCE_GAIN;
        } else {
            $data['variance_type'] = TankReading::VARIANCE_NONE;
        }

        TankReading::create([
            'company_id' => $company->id,
            'recorded_by_user_id' => auth()->id(),
            'status' => TankReading::STATUS_DRAFT,
            ...$data,
        ]);

        return redirect()->back()->with('success', 'Tank reading recorded successfully.');
    }

    public function show(string $company, TankReading $tankReading): Response
    {
        $tankReading->load(['tank.linkedItem', 'item', 'recordedBy', 'confirmedBy', 'journalEntry']);

        return Inertia::render('FuelStation/TankReadings/Show', [
            'reading' => $tankReading,
            'varianceReasons' => TankReading::getVarianceReasons(),
        ]);
    }

    public function update(UpdateTankReadingRequest $request, string $company, TankReading $tankReading): RedirectResponse
    {
        if (!$tankReading->isEditable()) {
            return redirect()->back()->with('error', 'Only draft readings can be edited.');
        }

        $data = $request->validated();

        // Recalculate variance if dip measurement changed
        if (isset($data['dip_measurement_liters'])) {
            $data['variance_liters'] = $data['dip_measurement_liters'] - $tankReading->system_calculated_liters;

            if ($data['variance_liters'] < 0) {
                $data['variance_type'] = TankReading::VARIANCE_LOSS;
            } elseif ($data['variance_liters'] > 0) {
                $data['variance_type'] = TankReading::VARIANCE_GAIN;
            } else {
                $data['variance_type'] = TankReading::VARIANCE_NONE;
            }
        }

        $tankReading->update($data);

        return redirect()->back()->with('success', 'Tank reading updated successfully.');
    }

    public function confirm(string $company, TankReading $tankReading): RedirectResponse
    {
        try {
            $this->tankReadingService->confirm($tankReading, auth()->id());

            return redirect()->back()->with('success', 'Tank reading confirmed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function post(string $company, TankReading $tankReading): RedirectResponse
    {
        try {
            $this->tankReadingService->post($tankReading);

            return redirect()->back()->with('success', 'Tank reading posted successfully. Journal entry created.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
