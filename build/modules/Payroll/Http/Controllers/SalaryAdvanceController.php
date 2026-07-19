<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CompanyCurrency;
use App\Modules\Accounting\Models\Account;
use App\Modules\Payroll\Http\Requests\StoreSalaryAdvanceRequest;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Services\CommandBus;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalaryAdvanceController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        // Set RLS context
        DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

        $advances = SalaryAdvance::where('company_id', $company->id)
            ->with('employee:id,first_name,last_name,position')
            ->orderByDesc('advance_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'employee_id' => $a->employee_id,
                'employee_name' => $a->employee ? "{$a->employee->first_name} {$a->employee->last_name}" : 'Unknown',
                'employee_position' => $a->employee?->position,
                'advance_date' => $a->advance_date->format('Y-m-d'),
                'amount' => (float) $a->amount,
                'amount_recovered' => (float) $a->amount_recovered,
                'amount_outstanding' => (float) $a->amount_outstanding,
                'status' => $a->status,
                'reason' => $a->reason,
                'payment_method' => $a->payment_method,
            ]);

        $stats = [
            'total_advances' => $advances->count(),
            'total_amount' => $advances->sum('amount'),
            'total_outstanding' => $advances->sum('amount_outstanding'),
            'total_recovered' => $advances->sum('amount_recovered'),
            'pending_count' => $advances->where('status', 'pending')->count(),
            'partially_recovered_count' => $advances->where('status', 'partially_recovered')->count(),
        ];

        $currencyRates = CompanyCurrency::where('company_id', $company->id)
            ->pluck('exchange_rate', 'currency_code')
            ->map(fn ($rate) => (float) $rate)
            ->put($company->base_currency, 1.0);

        $employees = Employee::where('company_id', $company->id)
            ->where('is_active', true)
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'position', 'base_salary', 'currency'])
            ->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => "{$employee->first_name} {$employee->last_name}",
                'position' => $employee->position,
                'base_salary' => (float) $employee->base_salary,
                'base_salary_in_company_currency' => round(
                    (float) $employee->base_salary * (float) ($currencyRates[$employee->currency] ?? 0),
                    2
                ),
                'currency' => $employee->currency,
            ]);

        $usesDailyClose = $company->isModuleEnabled('fuel_station')
            || $company->industry_code === 'fuel_station'
            || $company->industry === 'fuel_station';

        $paymentAccounts = $usesDailyClose
            ? collect()
            : Account::where('company_id', $company->id)
                ->whereIn('subtype', ['bank', 'cash'])
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->where(fn ($query) => $query
                    ->whereNull('currency')
                    ->orWhere('currency', $company->base_currency))
                ->orderBy('code')
                ->get(['id', 'code', 'name']);

        return Inertia::render('Payroll/SalaryAdvances/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'advances' => $advances,
            'employees' => $employees,
            'stats' => $stats,
            'currency' => $company->base_currency ?? 'PKR',
            'usesDailyClose' => $usesDailyClose,
            'paymentAccounts' => $paymentAccounts,
        ]);
    }

    public function store(StoreSalaryAdvanceRequest $request, CommandBus $commandBus): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        if ($company->isModuleEnabled('fuel_station') || $company->industry_code === 'fuel_station' || $company->industry === 'fuel_station') {
            return redirect()
                ->route('fuel.daily-close.create', ['company' => $company->slug])
                ->with('error', 'Salary advances are recorded from Daily Close so station cash has one source of truth.');
        }

        try {
            $result = $commandBus->dispatch('payroll.salary-advance.create', [
                ...$request->validated(),
                'recorded_by_user_id' => (string) $request->user()->id,
            ], $request->user());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', 'Salary advance could not be recorded. Check the payment account and try again.');
        }

        return back()->with('success', $result['message'] ?? 'Salary advance recorded.');
    }
}
