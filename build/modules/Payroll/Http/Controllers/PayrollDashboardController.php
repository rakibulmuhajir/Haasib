<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Http\Requests\GeneratePeriodPayslipsRequest;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Services\PayrollPostingService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayrollDashboardController extends Controller
{
    public function index(PayrollPostingService $postingService): Response
    {
        $company = app(CurrentCompany::class)->get();
        DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $currentPeriod = PayrollPeriod::where('company_id', $company->id)
            ->whereDate('period_start', $monthStart)
            ->whereDate('period_end', $monthEnd)
            ->first();

        $currentPeriod ??= PayrollPeriod::where('company_id', $company->id)
            ->whereIn('status', ['open', 'processing'])
            ->orderByDesc('period_start')
            ->first();

        $payslipBase = Payslip::where('company_id', $company->id);
        $advanceBase = SalaryAdvance::where('company_id', $company->id);

        $recentPayslips = Payslip::where('company_id', $company->id)
            ->with([
                'employee:id,first_name,last_name,employee_number',
                'payrollPeriod:id,period_start,period_end',
            ])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (Payslip $payslip) => [
                'id' => $payslip->id,
                'payslip_number' => $payslip->payslip_number,
                'employee_name' => trim($payslip->employee->first_name . ' ' . $payslip->employee->last_name),
                'period' => [
                    'start' => $payslip->payrollPeriod?->period_start,
                    'end' => $payslip->payrollPeriod?->period_end,
                ],
                'net_pay' => (float) $payslip->net_pay,
                'currency' => $payslip->currency,
                'status' => $payslip->status,
            ]);

        $employeesWithAdvances = Employee::where('company_id', $company->id)
            ->whereHas('salaryAdvances', fn ($query) => $query->whereIn('status', ['pending', 'partially_recovered']))
            ->withSum(['salaryAdvances as outstanding_advances' => fn ($query) => $query->whereIn('status', ['pending', 'partially_recovered'])], 'amount_outstanding')
            ->orderByDesc('outstanding_advances')
            ->limit(8)
            ->get(['id', 'first_name', 'last_name', 'employee_number'])
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => trim($employee->first_name . ' ' . $employee->last_name),
                'employee_number' => $employee->employee_number,
                'outstanding_advances' => (float) $employee->outstanding_advances,
            ]);

        return Inertia::render('Payroll/Dashboard/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'currentPeriod' => $currentPeriod ? [
                'id' => $currentPeriod->id,
                'period_start' => $currentPeriod->period_start,
                'period_end' => $currentPeriod->period_end,
                'payment_date' => $currentPeriod->payment_date,
                'status' => $currentPeriod->status,
            ] : null,
            'summary' => [
                'active_employees' => Employee::where('company_id', $company->id)->where('is_active', true)->where('employment_status', 'active')->count(),
                'draft_payslips' => (clone $payslipBase)->where('status', 'draft')->count(),
                'approved_unpaid_count' => (clone $payslipBase)->where('status', 'approved')->count(),
                'approved_unpaid_amount' => (float) (clone $payslipBase)->where('status', 'approved')->sum('net_pay'),
                'paid_this_month' => (float) (clone $payslipBase)->where('status', 'paid')->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('net_pay'),
                'salary_expense_this_month' => (float) (clone $payslipBase)->whereIn('status', ['approved', 'paid'])->whereMonth('approved_at', now()->month)->whereYear('approved_at', now()->year)->sum('gross_pay'),
                'outstanding_advances' => (float) (clone $advanceBase)->whereIn('status', ['pending', 'partially_recovered'])->sum('amount_outstanding'),
                'recovered_this_month' => (float) DB::table('pay.salary_advance_recoveries')
                    ->where('company_id', $company->id)
                    ->whereMonth('recovery_date', now()->month)
                    ->whereYear('recovery_date', now()->year)
                    ->sum('amount'),
            ],
            'accounts' => $postingService->ensureDefaultPayrollAccounts($company->id),
            'recentPayslips' => $recentPayslips,
            'employeesWithAdvances' => $employeesWithAdvances,
        ]);
    }

    public function runMonthly(GeneratePeriodPayslipsRequest $request, PayrollPostingService $postingService): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        try {
            $period = PayrollPeriod::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'period_start' => $monthStart,
                    'period_end' => $monthEnd,
                ],
                [
                    'payment_date' => $monthEnd,
                    'status' => 'open',
                ]
            );

            if (!in_array($period->status, ['open', 'processing'], true)) {
                return back()->with('error', 'This month payroll is already closed.');
            }

            $created = $postingService->generatePayslipsForPeriod($period, $company->base_currency ?? 'PKR');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Monthly payroll could not be prepared. Check employee salaries and payroll accounts.');
        }

        $message = $created > 0
            ? "{$created} payslips prepared for this month."
            : 'This month payroll is already prepared.';

        return back()->with('success', $message);
    }
}
