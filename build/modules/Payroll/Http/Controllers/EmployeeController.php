<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use App\Modules\Payroll\Http\Requests\StoreEmployeeRequest;
use App\Modules\Payroll\Http\Requests\UpdateEmployeeRequest;
use App\Modules\Payroll\Models\Employee;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $employees = Employee::where('company_id', $company->id)
            ->with('manager:id,first_name,last_name')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20);

        return Inertia::render('Payroll/Employees/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'employees' => $employees,
            'filters' => request()->only(['search', 'status', 'department']),
        ]);
    }

    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $managers = Employee::where('company_id', $company->id)
            ->where('is_active', true)
            ->select('id', 'first_name', 'last_name', 'employee_number')
            ->orderBy('last_name')
            ->get();

        return Inertia::render('Payroll/Employees/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'managers' => $managers,
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $employee = Employee::create([
            ...$request->validated(),
            'company_id' => $company->id,
            'created_by_user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('employees.show', ['company' => $company->slug, 'employee' => $employee->id])
            ->with('success', 'Employee created successfully.');
    }

    public function show(string $companySlug, string $employeeId): Response
    {
        $company = app(CurrentCompany::class)->get();

        $employee = Employee::where('company_id', $company->id)
            ->with(['manager:id,first_name,last_name', 'directReports:id,first_name,last_name,employee_number,position'])
            ->findOrFail($employeeId);

        return Inertia::render('Payroll/Employees/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'employee' => $employee,
        ]);
    }

    public function edit(string $companySlug, string $employeeId): Response
    {
        $company = app(CurrentCompany::class)->get();

        $employee = Employee::where('company_id', $company->id)->findOrFail($employeeId);

        $managers = Employee::where('company_id', $company->id)
            ->where('is_active', true)
            ->where('id', '!=', $employeeId)
            ->select('id', 'first_name', 'last_name', 'employee_number')
            ->orderBy('last_name')
            ->get();

        return Inertia::render('Payroll/Employees/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'employee' => $employee,
            'managers' => $managers,
        ]);
    }

    public function update(UpdateEmployeeRequest $request, string $companySlug, string $employeeId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $employee = Employee::where('company_id', $company->id)->findOrFail($employeeId);

        $employee->update([
            ...$request->validated(),
            'updated_by_user_id' => auth()->id(),
        ]);

        return redirect()
            ->route('employees.show', ['company' => $company->slug, 'employee' => $employee->id])
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(string $companySlug, string $employeeId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $employee = Employee::where('company_id', $company->id)->findOrFail($employeeId);

        // Check for active payslips
        if ($employee->payslips()->whereNotIn('status', ['cancelled'])->exists()) {
            return back()->with('error', 'Cannot delete employee with payroll history.');
        }

        $employee->delete();

        return redirect()
            ->route('employees.index', ['company' => $company->slug])
            ->with('success', 'Employee deleted successfully.');
    }
}
