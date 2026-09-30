<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CompanyCurrency;
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
    /** The salary report became the Payroll page (one month, every employee). */
    public function index(Request $request): \Illuminate\Http\RedirectResponse
    {
        $month = preg_match('/^\d{4}-\d{2}/', (string) $request->query('month', $request->query('start_date', ''))) ? substr((string) $request->query('month', $request->query('start_date')), 0, 7) : null;

        return redirect()->route('payroll.index', array_filter(['company' => app(\App\Services\CurrentCompany::class)->get()->slug, 'month' => $month]));
    }
}
