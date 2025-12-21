<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Http\Requests\StorePumpRequest;
use App\Modules\FuelStation\Http\Requests\UpdatePumpRequest;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PumpController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $pumps = Pump::where('company_id', $company->id)
            ->with('tank.linkedItem')
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

        Pump::create([
            'company_id' => $company->id,
            ...$request->validated(),
        ]);

        return redirect()->back()->with('success', 'Pump point created successfully.');
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
}
