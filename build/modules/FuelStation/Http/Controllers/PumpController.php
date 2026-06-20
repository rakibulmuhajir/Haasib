<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Http\Requests\StorePumpRequest;
use App\Modules\FuelStation\Http\Requests\UpdatePumpRequest;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CurrentCompany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PumpController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $pumps = Pump::where('company_id', $company->id)
            ->with(['tank.linkedItem', 'nozzles' => fn($q) => $q->orderBy('sort_order')])
            ->orderBy('name')
            ->get();

        $tanks = Warehouse::where('company_id', $company->id)
            ->where('warehouse_type', 'tank')
            ->with('linkedItem')
            ->get();

        // DEBUG: Get user role and permissions
        $user = request()->user();
        $userRole = null;
        $canCreatePump = false;
        $hasCompanyContext = false;

        if ($user && $company) {
            // Use the existing permission checking method
            $canCreatePump = $user->hasCompanyPermission('pump.create');
            $hasCompanyContext = true;

            // Try to get user role for the current company
            try {
                $companyUser = $user->companies()
                    ->where('company_id', $company->id)
                    ->withPivot('role')
                    ->first();
                $userRole = $companyUser?->pivot->role;
            } catch (\Exception $e) {
                try {
                    $role = $user->roles()->first();
                    $userRole = $role?->name;
                } catch (\Exception $e2) {
                    $userRole = 'Unknown';
                }
            }
        }

        return Inertia::render('FuelStation/Pumps/Index', [
            'pumps' => $pumps,
            'tanks' => $tanks,
            'debug' => [
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'user_role' => $userRole,
                'can_create_pump' => $canCreatePump,
                'has_company_context' => $hasCompanyContext,
                'company_id' => $company?->id,
                'company_name' => $company?->name,
            ],
        ]);
    }

    public function store(StorePumpRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $validated = $request->validated();

        $createdNozzleCodes = [];

        try {
            DB::transaction(function () use ($company, $validated, &$createdNozzleCodes) {
                $pump = Pump::create([
                    'company_id' => $company->id,
                    'name' => $validated['name'],
                    'tank_id' => $validated['tank_id'],
                    'current_meter_reading' => $validated['current_meter_reading'] ?? 0,
                    'is_active' => $validated['is_active'] ?? true,
                ]);

                // Create nozzles based on user-specified nozzle_count
                $tank = Warehouse::where('id', $validated['tank_id'])
                    ->where('company_id', $company->id)
                    ->where('warehouse_type', 'tank')
                    ->first();

                if ($tank && $tank->linked_item_id) {
                    $nozzleCount = (int) ($validated['nozzle_count'] ?? 2);
                    $preferredPumpNumber = $this->preferredNozzlePrefix($company->id, $validated['name']);
                    $pumpNumber = $this->nextAvailableNozzlePrefix($company->id, $preferredPumpNumber);

                    if ($this->pumpNameHasNumber($validated['name']) && $pumpNumber !== $preferredPumpNumber) {
                        throw ValidationException::withMessages([
                            'name' => "Point {$preferredPumpNumber} already exists. Edit that point or use Point {$pumpNumber}.",
                        ]);
                    }

                    $nozzleLabels = ['A', 'B'];
                    $nozzleSideNames = ['Front', 'Back'];
                    $sideReadings = [
                        0 => [
                            'electronic' => (float) ($validated['front_electronic'] ?? 0),
                            'manual' => (float) ($validated['front_manual'] ?? 0),
                        ],
                        1 => [
                            'electronic' => (float) ($validated['back_electronic'] ?? 0),
                            'manual' => (float) ($validated['back_manual'] ?? 0),
                        ],
                    ];

                    for ($i = 0; $i < $nozzleCount; $i++) {
                        $electronicReading = $sideReadings[$i]['electronic'];
                        $manualReading = $sideReadings[$i]['manual'];
                        $nozzleCode = $pumpNumber . $nozzleLabels[$i];

                        Nozzle::create([
                            'company_id' => $company->id,
                            'pump_id' => $pump->id,
                            'tank_id' => $tank->id,
                            'item_id' => $tank->linked_item_id,
                            'code' => $nozzleCode,
                            'label' => $validated['name'] . ' - ' . $nozzleSideNames[$i],
                            'current_meter_reading' => $electronicReading,
                            'last_closing_reading' => $electronicReading,
                            'last_manual_reading' => $manualReading,
                            'has_electronic_meter' => true,
                            'is_active' => true,
                            'sort_order' => $i,
                        ]);
                        $createdNozzleCodes[] = $nozzleCode;
                    }
                }

                return $pump;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                'name' => 'A pump or nozzle with this number already exists. Use a different pump point name.',
            ]);
        }

        $nozzleCount = $validated['nozzle_count'] ?? 2;
        $codeSuffix = empty($createdNozzleCodes) ? '' : ' (' . implode(', ', $createdNozzleCodes) . ')';

        return redirect()->back()->with('success', "Pump point created successfully with {$nozzleCount} nozzle(s){$codeSuffix}.");
    }

    public function show(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        $pumpId = $request->route('pump');
        $pump = Pump::where('company_id', $company->id)
            ->findOrFail($pumpId);

        $pump->load(['tank.linkedItem', 'pumpReadings' => fn($q) => $q->latest()->limit(20)]);

        return Inertia::render('FuelStation/Pumps/Show', [
            'pump' => $pump,
        ]);
    }

    public function update(UpdatePumpRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $pumpId = $request->route('pump');
        $pump = Pump::where('company_id', $company->id)
            ->findOrFail($pumpId);

        $pump->update($request->validated());

        return redirect()->back()->with('success', 'Pump point updated successfully.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $pumpId = $request->route('pump');
        $pump = Pump::where('company_id', $company->id)
            ->findOrFail($pumpId);

        // Check if pump has readings
        if ($pump->pumpReadings()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete pump with readings. Deactivate it instead.');
        }

        $pump->delete();

        return redirect()->back()->with('success', 'Pump point deleted successfully.');
    }

    private function preferredNozzlePrefix(string $companyId, string $pumpName): int
    {
        if (preg_match('/(\d+)/', $pumpName, $matches)) {
            return max(1, (int) $matches[1]);
        }

        return $this->nextNozzlePrefix($companyId);
    }

    private function pumpNameHasNumber(string $pumpName): bool
    {
        return preg_match('/\d+/', $pumpName) === 1;
    }

    private function nextNozzlePrefix(string $companyId): int
    {
        $maxPrefix = Nozzle::where('company_id', $companyId)
            ->pluck('code')
            ->map(function ($code) {
                preg_match('/^(\d+)/', (string) $code, $matches);

                return isset($matches[1]) ? (int) $matches[1] : 0;
            })
            ->max();

        return max(1, ((int) $maxPrefix) + 1);
    }

    private function nextAvailableNozzlePrefix(string $companyId, int $preferredPrefix): int
    {
        $takenCodes = Nozzle::where('company_id', $companyId)
            ->pluck('code')
            ->mapWithKeys(fn ($code) => [strtoupper((string) $code) => true])
            ->all();

        $preferredPrefix = max(1, $preferredPrefix);

        for ($prefix = $preferredPrefix; $prefix <= 9999; $prefix++) {
            if ($this->nozzleCodesAreAvailable($prefix, $takenCodes)) {
                return $prefix;
            }
        }

        for ($prefix = 1; $prefix < $preferredPrefix; $prefix++) {
            if ($this->nozzleCodesAreAvailable($prefix, $takenCodes)) {
                return $prefix;
            }
        }

        throw ValidationException::withMessages([
            'name' => 'No available nozzle number was found. Deactivate or remove unused nozzles first.',
        ]);
    }

    private function nozzleCodesAreAvailable(int $prefix, array $takenCodes): bool
    {
        $labels = ['A', 'B'];

        foreach ($labels as $label) {
            if (isset($takenCodes[strtoupper($prefix . $label)])) {
                return false;
            }
        }

        return true;
    }
}
