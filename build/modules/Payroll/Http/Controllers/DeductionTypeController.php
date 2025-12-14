<?php

namespace Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Payroll\Http\Requests\StoreDeductionTypeRequest;
use Modules\Payroll\Http\Requests\UpdateDeductionTypeRequest;
use Modules\Payroll\Models\DeductionType;

class DeductionTypeController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $deductionTypes = DeductionType::where('company_id', $company->id)
            ->orderBy('code')
            ->paginate(20);

        return Inertia::render('Payroll/DeductionTypes/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'deductionTypes' => $deductionTypes,
            'filters' => request()->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('Payroll/DeductionTypes/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
        ]);
    }

    public function store(StoreDeductionTypeRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $deductionType = DeductionType::create([
            ...$request->validated(),
            'company_id' => $company->id,
        ]);

        return redirect()
            ->route('deduction-types.index', ['company' => $company->slug])
            ->with('success', 'Deduction type created successfully.');
    }

    public function edit(string $companySlug, string $deductionTypeId): Response
    {
        $company = app(CurrentCompany::class)->get();

        $deductionType = DeductionType::where('company_id', $company->id)->findOrFail($deductionTypeId);

        return Inertia::render('Payroll/DeductionTypes/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'deductionType' => $deductionType,
        ]);
    }

    public function update(UpdateDeductionTypeRequest $request, string $companySlug, string $deductionTypeId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $deductionType = DeductionType::where('company_id', $company->id)->findOrFail($deductionTypeId);

        if ($deductionType->is_system) {
            return back()->with('error', 'System deduction types cannot be modified.');
        }

        $deductionType->update($request->validated());

        return redirect()
            ->route('deduction-types.index', ['company' => $company->slug])
            ->with('success', 'Deduction type updated successfully.');
    }

    public function destroy(string $companySlug, string $deductionTypeId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $deductionType = DeductionType::where('company_id', $company->id)->findOrFail($deductionTypeId);

        if ($deductionType->is_system) {
            return back()->with('error', 'System deduction types cannot be deleted.');
        }

        $deductionType->delete();

        return redirect()
            ->route('deduction-types.index', ['company' => $company->slug])
            ->with('success', 'Deduction type deleted successfully.');
    }
}
