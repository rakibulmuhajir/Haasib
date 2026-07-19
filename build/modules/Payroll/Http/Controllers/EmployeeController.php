<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Http\Requests\StoreEmployeeRequest;
use App\Modules\Payroll\Http\Requests\UpdateEmployeeRequest;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Models\SalaryAdvanceRecovery;
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

        $payslips = Payslip::where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->with('payrollPeriod:id,period_start,period_end,payment_date')
            ->orderByDesc('created_at')
            ->limit(12)
            ->get()
            ->map(fn (Payslip $payslip) => [
                'id' => $payslip->id,
                'date' => $payslip->payrollPeriod?->payment_date,
                'label' => $payslip->payslip_number,
                'type' => 'payslip',
                'gross_pay' => (float) $payslip->gross_pay,
                'deductions' => (float) $payslip->total_deductions,
                'net_pay' => (float) $payslip->net_pay,
                'status' => $payslip->status,
                'currency' => $payslip->currency,
            ]);

        $advances = SalaryAdvance::where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('advance_date')
            ->limit(12)
            ->get()
            ->map(fn (SalaryAdvance $advance) => [
                'id' => $advance->id,
                'date' => $advance->advance_date,
                'label' => 'Advance',
                'type' => 'advance',
                'amount' => (float) $advance->amount,
                'recovered' => (float) $advance->amount_recovered,
                'outstanding' => (float) $advance->amount_outstanding,
                'status' => $advance->status,
                'reason' => $advance->reason,
                'payment_method' => $advance->payment_method,
            ]);

        $recoveries = SalaryAdvanceRecovery::where('company_id', $company->id)
            ->whereHas('salaryAdvance', fn ($query) => $query->where('employee_id', $employee->id))
            ->with('payslip:id,payslip_number')
            ->orderByDesc('recovery_date')
            ->limit(12)
            ->get()
            ->map(fn (SalaryAdvanceRecovery $recovery) => [
                'id' => $recovery->id,
                'date' => $recovery->recovery_date,
                'label' => $recovery->payslip?->payslip_number ?? 'Recovery',
                'type' => 'recovery',
                'amount' => (float) $recovery->amount,
                'recovery_type' => $recovery->recovery_type,
            ]);

        return Inertia::render('Payroll/Employees/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'employee' => $employee,
            'statement' => [
                'summary' => [
                    'salary_due' => (float) Payslip::where('company_id', $company->id)->where('employee_id', $employee->id)->where('status', 'approved')->sum('base_net_pay'),
                    'salary_paid' => (float) Payslip::where('company_id', $company->id)->where('employee_id', $employee->id)->where('status', 'paid')->sum('base_net_pay'),
                    'advance_given' => (float) SalaryAdvance::where('company_id', $company->id)->where('employee_id', $employee->id)->sum('amount'),
                    'advance_recovered' => (float) SalaryAdvance::where('company_id', $company->id)->where('employee_id', $employee->id)->sum('amount_recovered'),
                    'advance_outstanding' => (float) SalaryAdvance::where('company_id', $company->id)->where('employee_id', $employee->id)->whereIn('status', ['pending', 'partially_recovered'])->sum('amount_outstanding'),
                ],
                'payslips' => $payslips,
                'advances' => $advances,
                'recoveries' => $recoveries,
            ],
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
