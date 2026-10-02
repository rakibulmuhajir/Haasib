<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Http\Requests\StoreTimeEntryRequest;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\TimeEntry;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/** Hours worked, logged per day on the employee's page (hourly pay). */
class TimeEntryController extends Controller
{
    private function setContext(string $companyId): void
    {
        DB::select("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);
        if (auth()->id()) {
            DB::select("SELECT set_config('app.current_user_id', ?, false)", [auth()->id()]);
        }
    }

    public function store(StoreTimeEntryRequest $request, string $companySlug, string $employeeId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $this->setContext($company->id);

        $employee = Employee::where('company_id', $company->id)->findOrFail($employeeId);

        TimeEntry::create([
            ...$request->validated(),
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'created_by_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Hours added.');
    }

    public function destroy(string $companySlug, string $timeEntryId): RedirectResponse
    {
        abort_unless(auth()->user()?->hasCompanyPermission(Permissions::EMPLOYEE_UPDATE), 403);

        $company = app(CurrentCompany::class)->get();
        $this->setContext($company->id);

        $entry = TimeEntry::where('company_id', $company->id)->findOrFail($timeEntryId);

        if (TimeEntry::monthIsLocked($company->id, $entry->employee_id, $entry->work_date)) {
            return back()->with('error', $entry->work_date->format('F').' is already approved.');
        }

        $entry->delete();

        return back()->with('success', 'Hours removed.');
    }
}
