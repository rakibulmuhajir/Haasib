<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalaryReportController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

        $month = (string) $request->query('month', now()->format('Y-m'));
        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }

        $end = $start->copy()->endOfMonth();

        $periodConstraint = fn ($query) => $query
            ->whereDate('period_start', '>=', $start->toDateString())
            ->whereDate('period_end', '<=', $end->toDateString());

        $employees = Employee::where('company_id', $company->id)
            ->where(function ($query) use ($periodConstraint) {
                $query->where('is_active', true)
                    ->orWhereHas('payslips.payrollPeriod', $periodConstraint)
                    ->orWhereHas('salaryAdvances');
            })
            ->with([
                'payslips' => fn ($query) => $query
                    ->whereHas('payrollPeriod', $periodConstraint)
                    ->with('payrollPeriod:id,period_start,period_end,payment_date'),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $rows = $employees->map(function (Employee $employee) use ($company, $start, $end) {
            $payslips = $employee->payslips;
            $employeeAdvanceQuery = SalaryAdvance::where('company_id', $company->id)
                ->where('employee_id', $employee->id);

            $advanceRecovered = (float) DB::table('pay.salary_advance_recoveries')
                ->join('pay.salary_advances', 'pay.salary_advances.id', '=', 'pay.salary_advance_recoveries.salary_advance_id')
                ->where('pay.salary_advance_recoveries.company_id', $company->id)
                ->where('pay.salary_advances.employee_id', $employee->id)
                ->whereBetween('pay.salary_advance_recoveries.recovery_date', [$start->toDateString(), $end->toDateString()])
                ->sum('pay.salary_advance_recoveries.amount');

            return [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'employee_name' => trim($employee->first_name . ' ' . $employee->last_name),
                'position' => $employee->position,
                'base_salary' => (float) $employee->base_salary,
                'payslip_count' => $payslips->count(),
                'gross_pay' => (float) $payslips->sum(fn (Payslip $payslip) => (float) $payslip->gross_pay),
                'deductions' => (float) $payslips->sum(fn (Payslip $payslip) => (float) $payslip->total_deductions),
                'net_pay' => (float) $payslips->sum(fn (Payslip $payslip) => (float) $payslip->net_pay),
                'paid' => (float) $payslips->where('status', 'paid')->sum(fn (Payslip $payslip) => (float) $payslip->net_pay),
                'unpaid' => (float) $payslips->where('status', 'approved')->sum(fn (Payslip $payslip) => (float) $payslip->net_pay),
                'draft' => (float) $payslips->where('status', 'draft')->sum(fn (Payslip $payslip) => (float) $payslip->net_pay),
                'advance_given' => (float) (clone $employeeAdvanceQuery)
                    ->whereBetween('advance_date', [$start->toDateString(), $end->toDateString()])
                    ->sum('amount'),
                'advance_recovered' => $advanceRecovered,
                'advance_outstanding' => (float) (clone $employeeAdvanceQuery)
                    ->whereIn('status', ['pending', 'partially_recovered'])
                    ->sum('amount_outstanding'),
            ];
        })->values();

        return Inertia::render('Payroll/Reports/Salary', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'filters' => [
                'month' => $month,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'summary' => [
                'employees' => $rows->count(),
                'gross_pay' => (float) $rows->sum('gross_pay'),
                'deductions' => (float) $rows->sum('deductions'),
                'net_pay' => (float) $rows->sum('net_pay'),
                'paid' => (float) $rows->sum('paid'),
                'unpaid' => (float) $rows->sum('unpaid'),
                'draft' => (float) $rows->sum('draft'),
                'advance_given' => (float) $rows->sum('advance_given'),
                'advance_recovered' => (float) $rows->sum('advance_recovered'),
                'advance_outstanding' => (float) $rows->sum('advance_outstanding'),
            ],
            'rows' => $rows,
        ]);
    }
}
