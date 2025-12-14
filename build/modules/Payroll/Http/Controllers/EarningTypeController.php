<?php

namespace Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Payroll\Http\Requests\StoreEarningTypeRequest;
use Modules\Payroll\Http\Requests\UpdateEarningTypeRequest;
use Modules\Payroll\Models\EarningType;

class EarningTypeController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $earningTypes = EarningType::where('company_id', $company->id)
            ->orderBy('code')
            ->paginate(20);

        return Inertia::render('Payroll/EarningTypes/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'earningTypes' => $earningTypes,
            'filters' => request()->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('Payroll/EarningTypes/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
        ]);
    }

    public function store(StoreEarningTypeRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $earningType = EarningType::create([
            ...$request->validated(),
            'company_id' => $company->id,
        ]);

        return redirect()
            ->route('earning-types.index', ['company' => $company->slug])
            ->with('success', 'Earning type created successfully.');
    }

    public function edit(string $companySlug, string $earningTypeId): Response
    {
        $company = app(CurrentCompany::class)->get();

        $earningType = EarningType::where('company_id', $company->id)->findOrFail($earningTypeId);

        return Inertia::render('Payroll/EarningTypes/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'earningType' => $earningType,
        ]);
    }

    public function update(UpdateEarningTypeRequest $request, string $companySlug, string $earningTypeId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $earningType = EarningType::where('company_id', $company->id)->findOrFail($earningTypeId);

        if ($earningType->is_system) {
            return back()->with('error', 'System earning types cannot be modified.');
        }

        $earningType->update($request->validated());

        return redirect()
            ->route('earning-types.index', ['company' => $company->slug])
            ->with('success', 'Earning type updated successfully.');
    }

    public function destroy(string $companySlug, string $earningTypeId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $earningType = EarningType::where('company_id', $company->id)->findOrFail($earningTypeId);

        if ($earningType->is_system) {
            return back()->with('error', 'System earning types cannot be deleted.');
        }

        $earningType->delete();

        return redirect()
            ->route('earning-types.index', ['company' => $company->slug])
            ->with('success', 'Earning type deleted successfully.');
    }
}
