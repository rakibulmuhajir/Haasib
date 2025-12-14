<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Payroll\Http\Requests\StoreLeaveTypeRequest;
use Modules\Payroll\Http\Requests\UpdateLeaveTypeRequest;
use Modules\Payroll\Models\LeaveType;

class LeaveTypeController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $leaveTypes = LeaveType::where('company_id', $company->id)
            ->orderBy('code')
            ->paginate(20);

        return Inertia::render('Payroll/LeaveTypes/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'leaveTypes' => $leaveTypes,
            'filters' => request()->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('Payroll/LeaveTypes/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
        ]);
    }

    public function store(StoreLeaveTypeRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $leaveType = LeaveType::create([
            ...$request->validated(),
            'company_id' => $company->id,
        ]);

        return redirect()
            ->route('leave-types.index', ['company' => $company->slug])
            ->with('success', 'Leave type created successfully.');
    }

    public function edit(string $companySlug, string $leaveTypeId): Response
    {
        $company = app(CurrentCompany::class)->get();

        $leaveType = LeaveType::where('company_id', $company->id)->findOrFail($leaveTypeId);

        return Inertia::render('Payroll/LeaveTypes/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'leaveType' => $leaveType,
        ]);
    }

    public function update(UpdateLeaveTypeRequest $request, string $companySlug, string $leaveTypeId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $leaveType = LeaveType::where('company_id', $company->id)->findOrFail($leaveTypeId);

        $leaveType->update($request->validated());

        return redirect()
            ->route('leave-types.index', ['company' => $company->slug])
            ->with('success', 'Leave type updated successfully.');
    }

    public function destroy(string $companySlug, string $leaveTypeId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $leaveType = LeaveType::where('company_id', $company->id)->findOrFail($leaveTypeId);

        if ($leaveType->leaveRequests()->exists()) {
            return back()->with('error', 'Cannot delete leave type with existing requests.');
        }

        $leaveType->delete();

        return redirect()
            ->route('leave-types.index', ['company' => $company->slug])
            ->with('success', 'Leave type deleted successfully.');
    }
}
