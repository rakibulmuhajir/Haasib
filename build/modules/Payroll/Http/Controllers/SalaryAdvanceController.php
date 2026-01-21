<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Services\CurrentCompany;
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
            ->map(fn($a) => [
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

        // Get employees for filter
        $employees = [];
        try {
            $employees = Employee::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'position'])
                ->map(fn($e) => [
                    'id' => $e->id,
                    'name' => "{$e->first_name} {$e->last_name}",
                    'position' => $e->position,
                ]);
        } catch (\Throwable $e) {
            // Ignore if payroll module not available
        }

        return Inertia::render('Payroll/SalaryAdvances/Index', [
            'advances' => $advances,
            'employees' => $employees,
            'stats' => $stats,
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }
}
