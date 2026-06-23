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
        $status = (string) $request->query('status', 'all');
        $employeeId = (string) $request->query('employee_id', 'all');

        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }

        if ($request->filled('start_date')) {
            try {
                $start = Carbon::parse($request->query('start_date'))->startOfDay();
            } catch (\Throwable) {
                $start = now()->startOfMonth();
            }
        }

        try {
            $end = $request->filled('end_date')
                ? Carbon::parse($request->query('end_date'))->endOfDay()
                : $start->copy()->endOfMonth();
        } catch (\Throwable) {
            $end = $start->copy()->endOfMonth();
        }

        if ($end->lt($start)) {
            $end = $start->copy()->endOfMonth();
        }

        $periodConstraint = fn ($query) => $query
            ->whereDate('period_start', '>=', $start->toDateString())
            ->whereDate('period_end', '<=', $end->toDateString());

        $employees = Employee::where('company_id', $company->id)
            ->when($employeeId !== 'all' && $employeeId !== '', fn ($query) => $query->where('id', $employeeId))
            ->where(function ($query) use ($periodConstraint) {
                $query->where('is_active', true)
                    ->orWhereHas('payslips.payrollPeriod', $periodConstraint)
                    ->orWhereHas('salaryAdvances');
            })
            ->with([
                'payslips' => fn ($query) => $query
                    ->whereHas('payrollPeriod', $periodConstraint)
                    ->when($status !== 'all' && $status !== '', fn ($statusQuery) => $statusQuery->where('status', $status))
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
                'payslips' => $payslips
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn (Payslip $payslip) => [
                        'id' => $payslip->id,
                        'payslip_number' => $payslip->payslip_number,
                        'status' => $payslip->status,
                        'gross_pay' => (float) $payslip->gross_pay,
                        'deductions' => (float) $payslip->total_deductions,
                        'net_pay' => (float) $payslip->net_pay,
                        'period_id' => $payslip->payroll_period_id,
                        'period_start' => $payslip->payrollPeriod?->period_start?->toDateString(),
                        'period_end' => $payslip->payrollPeriod?->period_end?->toDateString(),
                    ])
                    ->all(),
            ];
        })
            ->filter(fn (array $row) => $status === 'all' || $row['payslip_count'] > 0 || $row['advance_given'] > 0 || $row['advance_recovered'] > 0)
            ->values();

        $employeeOptions = Employee::where('company_id', $company->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'employee_number', 'first_name', 'last_name'])
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'label' => trim($employee->first_name . ' ' . $employee->last_name) . ' · ' . $employee->employee_number,
            ])
            ->values();

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
                'employee_id' => $employeeId ?: 'all',
                'status' => $status ?: 'all',
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
            'employeeOptions' => $employeeOptions,
            'rows' => $rows,
        ]);
    }
}
