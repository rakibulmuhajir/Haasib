<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Http\Requests\StoreTankReadingRequest;
use App\Modules\FuelStation\Http\Requests\UpdateTankReadingRequest;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\TankReadingService;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CurrentCompany;
use Illuminate\Database\QueryException;
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
        $data = $request->validated();

        try {
            $reading = $this->tankReadingService->create($data);
            $this->tankReadingService->confirm($reading, auth()->id());
            $this->tankReadingService->post($reading->fresh());

            return redirect()->back()->with('success', 'Tank dip recorded and stock updated.');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (QueryException $e) {
            return redirect()->back()->with('error', $this->friendlyDatabaseErrorMessage($e));
        }
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

        try {
            $tankReading->update($data);
        } catch (QueryException $e) {
            return redirect()->back()->with('error', $this->friendlyDatabaseErrorMessage($e));
        }

        return redirect()->back()->with('success', 'Tank reading updated successfully.');
    }

    /**
     * The capture_post_close_activity / protect_locked_opening triggers raise
     * plain, human-readable RAISE EXCEPTION messages (e.g. "Physical
     * observations on a posted Daily Close are immutable; record a separate
     * adjustment"). Surface those to the user via a Sonner toast (see
     * AI_PROMPTS/toast.md) instead of letting the QueryException bubble into
     * a generic 500.
     */
    private function friendlyDatabaseErrorMessage(QueryException $e): string
    {
        if (preg_match('/ERROR:\s*(.+?)(?:\r?\nCONTEXT:|$)/s', $e->getMessage(), $matches)) {
            $message = trim(preg_replace('/\s+/', ' ', $matches[1]));
            if ($message !== '') {
                return $message;
            }
        }

        return 'This change could not be saved because it conflicts with existing records.';
    }

    public function confirm(string $company, TankReading $tankReading): RedirectResponse
    {
        try {
            $this->tankReadingService->confirm($tankReading, auth()->id());

            return redirect()->back()->with('success', 'Tank reading confirmed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (QueryException $e) {
            return redirect()->back()->with('error', $this->friendlyDatabaseErrorMessage($e));
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
        } catch (QueryException $e) {
            return redirect()->back()->with('error', $this->friendlyDatabaseErrorMessage($e));
        }
    }
}
