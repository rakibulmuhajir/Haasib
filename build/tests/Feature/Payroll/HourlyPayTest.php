<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Models\TimeEntry;
use App\Modules\Payroll\Services\MonthEndPayrollDraft;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Hourly pay: hours logged per day, the month's payroll pays total hours x rate. Self-contained
 * helpers, distinctly named, as each test file runs on its own.
 */
function hourlyPayCompany(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Hourly '.str()->random(6),
        'slug' => 'hourly-'.str()->lower(str()->random(10)),
        'base_currency' => 'PKR',
        'settings' => ['modules' => ['payroll' => true]],
    ]);
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    Auth::login($user);

    $employee = Employee::create([
        'company_id' => $company->id, 'employee_number' => 'EMP-HR-1', 'first_name' => 'Hour', 'last_name' => 'Ly',
        'hire_date' => '2026-01-01', 'employment_type' => 'part_time', 'employment_status' => 'active',
        'pay_frequency' => 'hourly', 'hourly_rate' => 250, 'base_salary' => 0, 'currency' => 'PKR', 'is_active' => true,
    ]);

    return [$user, $company, $employee];
}

function hourlyPayLog(Company $company, Employee $employee, string $date, float $hours): TimeEntry
{
    return TimeEntry::create([
        'company_id' => $company->id, 'employee_id' => $employee->id, 'work_date' => $date, 'hours' => $hours,
    ]);
}

test('an hourly draft pays the months hours x rate, with its advances taken off', function () {
    [, $company, $employee] = hourlyPayCompany();
    hourlyPayLog($company, $employee, '2026-09-01', 8);
    hourlyPayLog($company, $employee, '2026-09-02', 6.5);
    hourlyPayLog($company, $employee, '2026-09-03', 5);
    hourlyPayLog($company, $employee, '2026-10-01', 9);
    SalaryAdvance::create([
        'company_id' => $company->id, 'employee_id' => $employee->id, 'advance_date' => '2026-09-10',
        'amount' => 1000, 'amount_outstanding' => 1000, 'reason' => 'Advance', 'status' => 'pending', 'payment_method' => 'cash',
    ]);

    expect(app(MonthEndPayrollDraft::class)->prepare($company, '2026-09-01'))->toBe(1);

    $payslip = Payslip::where('company_id', $company->id)->sole()->refresh();
    expect((float) $payslip->gross_pay)->toBe(19.5 * 250)
        ->and((float) $payslip->total_deductions)->toBe(1000.0)
        ->and((float) $payslip->net_pay)->toBe(19.5 * 250 - 1000);
});

test('logging more hours and preparing again updates the draft', function () {
    [, $company, $employee] = hourlyPayCompany();
    hourlyPayLog($company, $employee, '2026-09-01', 8);
    $draft = app(MonthEndPayrollDraft::class);
    $draft->prepare($company, '2026-09-01');

    hourlyPayLog($company, $employee, '2026-09-02', 4);
    expect($draft->prepare($company, '2026-09-01'))->toBe(0);

    $payslip = Payslip::where('company_id', $company->id)->sole()->refresh();
    expect((float) $payslip->gross_pay)->toBe(12 * 250.0)
        ->and((float) $payslip->base_gross_pay)->toBe(12 * 250.0);
});

test('hours cannot be logged for a month that is already approved', function () {
    [$user, $company, $employee] = hourlyPayCompany();
    hourlyPayLog($company, $employee, '2026-09-01', 8);
    app(MonthEndPayrollDraft::class)->prepare($company, '2026-09-01');
    Payslip::where('company_id', $company->id)->update(['status' => 'approved']);

    $this->actingAs($user)
        ->post("/{$company->slug}/employees/{$employee->id}/hours", ['work_date' => '2026-09-15', 'hours' => 4])
        ->assertSessionHasErrors('work_date');

    expect(TimeEntry::where('company_id', $company->id)->count())->toBe(1);
});

test('the hours endpoint logs an entry', function () {
    [$user, $company, $employee] = hourlyPayCompany();

    $this->actingAs($user)
        ->post("/{$company->slug}/employees/{$employee->id}/hours", ['work_date' => '2026-09-05', 'hours' => 7.5, 'notes' => 'Shop'])
        ->assertSessionHasNoErrors();

    $entry = TimeEntry::where('company_id', $company->id)->sole();
    expect((float) $entry->hours)->toBe(7.5)
        ->and($entry->work_date->toDateString())->toBe('2026-09-05')
        ->and($entry->notes)->toBe('Shop');
});
