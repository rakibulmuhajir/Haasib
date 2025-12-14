<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Services\FiscalYearService;
use App\Services\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class FiscalYearController extends Controller
{
    public function __construct(
        private FiscalYearService $fiscalYearService,
        private CompanyContextService $companyContext
    ) {}

    public function index(): Response
    {
        $company = $this->companyContext->requireCompany();

        $fiscalYears = FiscalYear::where('company_id', $company->id)
            ->with(['periods' => function ($query) {
                $query->orderBy('period_number');
            }])
            ->orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('accounting/fiscal-years/Index', [
            'fiscalYears' => $fiscalYears,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ]
        ]);
    }

    public function create(): Response
    {
        $company = $this->companyContext->requireCompany();

        return Inertia::render('accounting/fiscal-years/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'existingFiscalYears' => FiscalYear::where('company_id', $company->id)
                ->orderBy('start_date', 'desc')
                ->get(['id', 'name', 'start_date', 'end_date']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->companyContext->requireCompany();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'period_type' => 'required|in:monthly,quarterly,yearly',
            'auto_create_periods' => 'boolean',
        ]);

        $fiscalYear = $this->fiscalYearService->createFiscalYear(
            $company->id,
            $validated
        );

        return response()->json([
            'success' => true,
            'data' => $fiscalYear,
            'message' => 'Fiscal year created successfully.'
        ], 201);
    }

    public function show(FiscalYear $fiscalYear): Response
    {
        $this->authorize('view', $fiscalYear);

        $fiscalYear->load(['periods' => function ($query) {
            $query->orderBy('period_number');
        }]);

        $company = $this->companyContext->requireCompany();

        return Inertia::render('accounting/fiscal-years/Show', [
            'fiscalYear' => $fiscalYear,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ]
        ]);
    }

    public function edit(FiscalYear $fiscalYear): Response
    {
        $this->authorize('update', $fiscalYear);

        $company = $this->companyContext->requireCompany();

        return Inertia::render('accounting/fiscal-years/Edit', [
            'fiscalYear' => $fiscalYear,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ]
        ]);
    }

    public function update(Request $request, FiscalYear $fiscalYear): JsonResponse
    {
        $this->authorize('update', $fiscalYear);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'is_current' => 'sometimes|boolean',
        ]);

        $fiscalYear->update($validated);

        return response()->json([
            'success' => true,
            'data' => $fiscalYear,
            'message' => 'Fiscal year updated successfully.'
        ]);
    }

    public function destroy(FiscalYear $fiscalYear): JsonResponse
    {
        $this->authorize('delete', $fiscalYear);

        // Check if fiscal year has any transactions
        if ($fiscalYear->transactions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete fiscal year with existing transactions.'
            ], 422);
        }

        $fiscalYear->delete();

        return response()->json([
            'success' => true,
            'message' => 'Fiscal year deleted successfully.'
        ]);
    }

    public function createPeriods(Request $request, FiscalYear $fiscalYear): JsonResponse
    {
        $this->authorize('update', $fiscalYear);

        $validated = $request->validate([
            'period_type' => 'required|in:monthly,quarterly,yearly',
        ]);

        $periods = $this->fiscalYearService->createPeriods(
            $fiscalYear,
            $validated['period_type']
        );

        return response()->json([
            'success' => true,
            'data' => $periods,
            'message' => 'Accounting periods created successfully.'
        ]);
    }
}