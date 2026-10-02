<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Http\Requests\StoreEmployeeRequest;
use App\Modules\Payroll\Http\Requests\UpdateEmployeeRequest;
use App\Modules\Payroll\Models\Employee;
use App\Services\CompanyCurrencyOptions;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    private function nextEmployeeNumber(string $companyId): string
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["pay.employee_number:{$companyId}"]);

        $lastNumber = Employee::where('company_id', $companyId)
            ->where('employee_number', 'like', 'EMP-%')
            ->whereRaw("employee_number ~ '^EMP-[0-9]+$'")
            ->selectRaw("MAX((substring(employee_number from '[0-9]+$'))::integer) as max_number")
            ->value('max_number');

        return 'EMP-'.str_pad((string) (((int) $lastNumber) + 1), 5, '0', STR_PAD_LEFT);
    }

    private function setPayrollContext(string $companyId): void
    {
        try {
            DB::select("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);

            if (auth()->id()) {
                DB::select("SELECT set_config('app.current_user_id', ?, false)", [auth()->id()]);
            }
        } catch (\Throwable $e) {
            // Queries still include company_id filters; this only supports RLS-enabled installs.
        }
    }

    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

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
        $this->setPayrollContext($company->id);

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
            'currencies' => app(CompanyCurrencyOptions::class)->forCompany($company),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

        $employee = DB::transaction(function () use ($request, $company) {
            $data = $request->validated();
            $data['employee_number'] = filled($data['employee_number'] ?? null)
                ? $data['employee_number']
                : $this->nextEmployeeNumber($company->id);

            return Employee::create([
                ...$data,
                'company_id' => $company->id,
                'created_by_user_id' => auth()->id(),
            ]);
        });

        return redirect()
            ->route('employees.index', ['company' => $company->slug])
            ->with('success', "Employee {$employee->employee_number} created successfully.");
    }

    public function show(string $companySlug, string $employeeId): Response
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

        $employee = Employee::where('company_id', $company->id)
            ->with(['manager:id,first_name,last_name', 'directReports:id,first_name,last_name,employee_number,position'])
            ->findOrFail($employeeId);

        // Their statement for a month: salary (expected until payroll runs), advances, payments.
        $month = preg_match('/^\d{4}-\d{2}$/', (string) request()->query('month')) ? request()->query('month') : now()->format('Y-m');
        $monthStart = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $month.'-01');
        $statement = app(\App\Modules\Payroll\Services\EmployeeStatementService::class)
            ->statement($employee, $monthStart->toDateString(), $monthStart->copy()->endOfMonth()->toDateString());

        // Hourly pay: the month's logged hours and what they come to.
        $hours = [];
        $hoursTotal = 0.0;
        if ($employee->pay_frequency === 'hourly') {
            $entries = $employee->timeEntries()
                ->whereBetween('work_date', [$monthStart->toDateString(), $monthStart->copy()->endOfMonth()->toDateString()])
                ->orderBy('work_date')->orderBy('created_at')
                ->get();
            $hours = $entries->map(fn ($e) => [
                'id' => $e->id,
                'work_date' => $e->work_date->toDateString(),
                'hours' => (float) $e->hours,
                'notes' => $e->notes,
            ])->all();
            $hoursTotal = round((float) $entries->sum('hours'), 2);
        }

        return Inertia::render('Payroll/Employees/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'employee' => $employee,
            'month' => $month,
            'statement' => $statement,
            'hours' => $hours,
            'hoursMonth' => $month,
            'hoursTotal' => $hoursTotal,
            'hoursAmount' => round($hoursTotal * (float) $employee->hourly_rate, 2),
        ]);
    }

    public function edit(string $companySlug, string $employeeId): Response
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

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
            'currencies' => app(CompanyCurrencyOptions::class)->forCompany($company),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, string $companySlug, string $employeeId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

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
        $this->setPayrollContext($company->id);

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
