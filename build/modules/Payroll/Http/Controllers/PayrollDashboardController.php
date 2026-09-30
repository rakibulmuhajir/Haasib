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
    /**
     * Payroll, one month on one page: every active employee with their salary, the advances they
     * took that month, and that month's payslip (deductions, net, approved / paid), plus the
     * month's actions -- run payroll, approve, pay. It replaced the payroll overview, the periods
     * list, the payslips list and the salary report, which showed the same month four ways.
     */
    public function index(\Illuminate\Http\Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $start = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $monthStart = $start->toDateString();
        $monthEnd = $start->copy()->endOfMonth()->toDateString();

        $period = PayrollPeriod::where('company_id', $company->id)
            ->whereDate('period_start', $monthStart)->whereDate('period_end', $monthEnd)->first();
        $payslips = $period
            ? Payslip::where('company_id', $company->id)->where('payroll_period_id', $period->id)
                ->whereNotIn('status', ['voided', 'void', 'cancelled'])->get()->keyBy('employee_id')
            : collect();
        $advances = SalaryAdvance::where('company_id', $company->id)->where('status', '!=', 'cancelled')
            ->whereBetween('advance_date', [$monthStart, $monthEnd])
            ->selectRaw('employee_id, SUM(amount) as total, COUNT(*) as n')->groupBy('employee_id')->get()->keyBy('employee_id');

        $employees = Employee::where('company_id', $company->id)
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $payslips->keys()))
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_number', 'base_salary', 'currency', 'is_active']);

        $rows = $employees->map(function (Employee $employee) use ($payslips, $advances) {
            $payslip = $payslips[$employee->id] ?? null;

            return [
                'id' => $employee->id,
                'name' => trim($employee->first_name.' '.$employee->last_name),
                'employee_number' => $employee->employee_number,
                'salary' => (float) $employee->base_salary,
                'advances' => round((float) ($advances[$employee->id]->total ?? 0), 2),
                'advance_count' => (int) ($advances[$employee->id]->n ?? 0),
                'payslip' => $payslip ? [
                    'id' => $payslip->id,
                    'number' => $payslip->payslip_number,
                    'gross' => (float) $payslip->gross_pay,
                    'deductions' => (float) $payslip->total_deductions,
                    'net' => (float) $payslip->net_pay,
                    'status' => $payslip->status,
                    'paid_at' => $payslip->paid_at?->toDateString(),
                ] : null,
            ];
        })->values();

        $count = fn (string $status) => $payslips->where('status', $status)->count();

        return Inertia::render('Payroll/Dashboard/Index', [
            'company' => ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'month' => $month,
            'period' => $period ? ['id' => $period->id, 'status' => $period->status] : null,
            'rows' => $rows,
            'counts' => [
                'employees' => $rows->count(),
                'payslips' => $payslips->count(),
                'draft' => $count('draft'),
                'approved' => $count('approved'),
                'paid' => $count('paid'),
            ],
        ]);
    }

    public function runMonthly(GeneratePeriodPayslipsRequest $request, PayrollPostingService $postingService): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

        // The month comes from the request when given; otherwise the current month. There is no
        // fixed pay day - employees are paid whenever they ask, or whenever the company pays at
        // its convenience - so payment_date is only filled to satisfy the not-null column and is
        // never shown to the user as "the pay date". A caller may still send one; it is kept.
        $month = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', ($request->input('month') ?: now()->format('Y-m')).'-01');
        $monthStart = $month->copy()->startOfMonth()->toDateString();
        $monthEnd = $month->copy()->endOfMonth()->toDateString();
        $paymentDate = $request->input('payment_date') ?: $monthEnd;

        try {
            $period = PayrollPeriod::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'period_start' => $monthStart,
                    'period_end' => $monthEnd,
                ],
                [
                    'payment_date' => $paymentDate,
                    'status' => 'open',
                ]
            );

            if ($request->filled('payment_date') && in_array($period->status, ['open', 'processing'], true)
                && optional($period->payment_date)->toDateString() !== $paymentDate) {
                $period->update(['payment_date' => $paymentDate]);
            }

            if (! in_array($period->status, ['open', 'processing'], true)) {
                return back()->with('error', 'This month payroll is already closed.');
            }

            $created = $postingService->generatePayslipsForPeriod($period, $company->base_currency ?? 'PKR');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Monthly payroll could not be prepared. Check employee salaries and payroll accounts.');
        }

        $label = $month->format('F Y');
        $message = $created > 0
            ? "{$created} payslips prepared for {$label}."
            : "{$label} payroll is already prepared.";

        return back()->with('success', $message);
    }
}
