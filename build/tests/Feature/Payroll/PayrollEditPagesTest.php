<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Payroll\Models\DeductionType;
use App\Modules\Payroll\Models\EarningType;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\LeaveRequest;
use App\Modules\Payroll\Models\LeaveType;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\Payslip;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Five Payroll pages that controllers rendered but which had no .vue file: the
 * edit screens for deduction types, earning types, leave types, leave requests
 * and payslips, plus the leave request detail page. Every one of them answered
 * with a blank screen, so nothing about them was ever exercised over HTTP.
 *
 * These tests go through the real routes, with the real FormRequests, so the
 * permission each controller already checks has to actually be held.
 *
 * NOTE on `->component(name, false)`: Payroll renders module-prefixed names
 * ('Payroll/DeductionTypes/Edit') while its pages live at
 * modules/Payroll/Resources/js/pages/DeductionTypes/Edit.vue -- the runtime
 * resolver strips the module slug, but Inertia's testing view-finder does not.
 * The existence check is therefore switched off here; that the page really is
 * on disk, spelled exactly as rendered, is what
 * tests/Feature/InertiaPageCaseSensitivityTest.php asserts.
 */
function payrollPageCompany(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Payroll Pages '.str()->random(8),
        'slug' => 'payroll-pages-'.str()->lower(str()->random(10)),
        'base_currency' => 'SAR',
        'settings' => ['modules' => ['payroll' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($user, 'owner'),
    );
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    test()->actingAs($user);
    app(CurrentCompany::class)->set($company);

    return [$user, $company];
}

function payrollPageEmployee(Company $company, string $number = 'EMP-PAGE-1'): Employee
{
    return Employee::create([
        'company_id' => $company->id,
        'employee_number' => $number,
        'first_name' => 'Page',
        'last_name' => 'Tester',
        'hire_date' => '2026-01-05',
        'employment_type' => 'full_time',
        'employment_status' => 'active',
        'pay_frequency' => 'monthly',
        'base_salary' => 5000,
        'currency' => 'SAR',
        'is_active' => true,
    ]);
}

test('the deduction type edit page renders with the record it is editing', function () {
    [$user, $company] = payrollPageCompany();

    $deductionType = DeductionType::create([
        'company_id' => $company->id,
        'code' => 'TAX',
        'name' => 'Income tax',
        'is_pre_tax' => true,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get("/{$company->slug}/deduction-types/{$deductionType->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/DeductionTypes/Edit', false)
            ->where('deductionType.id', $deductionType->id)
            ->where('deductionType.code', 'TAX')
            ->where('company.slug', $company->slug));
});

test('a deduction type edit saves through the existing update route', function () {
    [$user, $company] = payrollPageCompany();

    $deductionType = DeductionType::create([
        'company_id' => $company->id,
        'code' => 'TAX',
        'name' => 'Income tax',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->put("/{$company->slug}/deduction-types/{$deductionType->id}", [
            'code' => 'TAX2',
            'name' => 'Income tax (revised)',
            'description' => 'Revised band',
            'is_pre_tax' => true,
            'is_statutory' => false,
            'is_recurring' => true,
            'is_active' => true,
        ])
        ->assertRedirect("/{$company->slug}/deduction-types");

    expect($deductionType->fresh()->code)->toBe('TAX2')
        ->and($deductionType->fresh()->name)->toBe('Income tax (revised)');
});

test('the earning type edit page renders with the record it is editing', function () {
    [$user, $company] = payrollPageCompany();

    $earningType = EarningType::create([
        'company_id' => $company->id,
        'code' => 'BONUS',
        'name' => 'Performance bonus',
        'is_taxable' => true,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get("/{$company->slug}/earning-types/{$earningType->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/EarningTypes/Edit', false)
            ->where('earningType.id', $earningType->id)
            ->where('earningType.code', 'BONUS')
            ->where('company.slug', $company->slug));
});

test('an earning type edit saves through the existing update route', function () {
    [$user, $company] = payrollPageCompany();

    $earningType = EarningType::create([
        'company_id' => $company->id,
        'code' => 'BONUS',
        'name' => 'Performance bonus',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->put("/{$company->slug}/earning-types/{$earningType->id}", [
            'code' => 'BONUS',
            'name' => 'Annual bonus',
            'is_taxable' => true,
            'affects_overtime' => false,
            'is_recurring' => false,
            'is_active' => false,
        ])
        ->assertRedirect("/{$company->slug}/earning-types");

    expect($earningType->fresh()->name)->toBe('Annual bonus')
        ->and($earningType->fresh()->is_active)->toBeFalse();
});

test('the leave type edit page renders with the record it is editing', function () {
    [$user, $company] = payrollPageCompany();

    $leaveType = LeaveType::create([
        'company_id' => $company->id,
        'code' => 'ANNUAL',
        'name' => 'Annual leave',
        'is_paid' => true,
        'accrual_rate_hours' => 14,
        'requires_approval' => true,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get("/{$company->slug}/leave-types/{$leaveType->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/LeaveTypes/Edit', false)
            ->where('leaveType.id', $leaveType->id)
            ->where('leaveType.code', 'ANNUAL')
            ->where('company.slug', $company->slug));
});

test('a leave type edit saves through the existing update route', function () {
    [$user, $company] = payrollPageCompany();

    $leaveType = LeaveType::create([
        'company_id' => $company->id,
        'code' => 'ANNUAL',
        'name' => 'Annual leave',
        'accrual_rate_hours' => 14,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->put("/{$company->slug}/leave-types/{$leaveType->id}", [
            'code' => 'ANNUAL',
            'name' => 'Annual leave (2027)',
            'is_paid' => true,
            'accrual_rate_hours' => 16,
            'max_carryover_hours' => 40,
            'max_balance_hours' => null,
            'requires_approval' => true,
            'is_active' => true,
        ])
        ->assertRedirect("/{$company->slug}/leave-types");

    expect($leaveType->fresh()->name)->toBe('Annual leave (2027)')
        ->and((float) $leaveType->fresh()->accrual_rate_hours)->toBe(16.0);
});

test('the leave request detail page renders the request with its employee and type', function () {
    [$user, $company] = payrollPageCompany();

    $employee = payrollPageEmployee($company);
    $leaveType = LeaveType::create([
        'company_id' => $company->id,
        'code' => 'ANNUAL',
        'name' => 'Annual leave',
        'is_active' => true,
    ]);
    $leaveRequest = LeaveRequest::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-03',
        'hours' => 24,
        'reason' => 'Family trip',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get("/{$company->slug}/leave-requests/{$leaveRequest->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/LeaveRequests/Show', false)
            ->where('leaveRequest.id', $leaveRequest->id)
            ->where('leaveRequest.status', 'pending')
            ->where('leaveRequest.employee.first_name', 'Page')
            ->where('leaveRequest.leave_type.code', 'ANNUAL')
            ->where('company.slug', $company->slug));
});

test('the leave request edit page renders the request with its pickers', function () {
    [$user, $company] = payrollPageCompany();

    $employee = payrollPageEmployee($company);
    $leaveType = LeaveType::create([
        'company_id' => $company->id,
        'code' => 'ANNUAL',
        'name' => 'Annual leave',
        'is_active' => true,
    ]);
    $leaveRequest = LeaveRequest::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-03',
        'hours' => 24,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get("/{$company->slug}/leave-requests/{$leaveRequest->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/LeaveRequests/Edit', false)
            ->where('leaveRequest.id', $leaveRequest->id)
            ->has('employees', 1)
            ->has('leaveTypes', 1));
});

test('a leave request edit saves through the existing update route', function () {
    [$user, $company] = payrollPageCompany();

    $employee = payrollPageEmployee($company);
    $leaveType = LeaveType::create([
        'company_id' => $company->id,
        'code' => 'ANNUAL',
        'name' => 'Annual leave',
        'is_active' => true,
    ]);
    $leaveRequest = LeaveRequest::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-03',
        'hours' => 24,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->put("/{$company->slug}/leave-requests/{$leaveRequest->id}", [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'hours' => 40,
            'reason' => 'Extended trip',
            'notes' => null,
        ])
        ->assertRedirect("/{$company->slug}/leave-requests/{$leaveRequest->id}");

    $updated = $leaveRequest->fresh();
    expect($updated->end_date->toDateString())->toBe('2026-10-05')
        ->and((float) $updated->hours)->toBe(40.0)
        ->and($updated->reason)->toBe('Extended trip');
});

test('editing an already-approved leave request sends you back to the detail page', function () {
    [$user, $company] = payrollPageCompany();

    $employee = payrollPageEmployee($company);
    $leaveType = LeaveType::create([
        'company_id' => $company->id,
        'code' => 'ANNUAL',
        'name' => 'Annual leave',
        'is_active' => true,
    ]);
    $leaveRequest = LeaveRequest::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-03',
        'hours' => 24,
        'status' => 'approved',
    ]);

    // This redirect names the route parameter, and naming it wrong throws
    // rather than redirecting -- which is exactly what it used to do.
    $this->actingAs($user)
        ->get("/{$company->slug}/leave-requests/{$leaveRequest->id}/edit")
        ->assertRedirect("/{$company->slug}/leave-requests/{$leaveRequest->id}")
        ->assertSessionHas('error', 'Only pending requests can be edited.');
});

test('a leave request edit with an end date before the start date is rejected inline', function () {
    [$user, $company] = payrollPageCompany();

    $employee = payrollPageEmployee($company);
    $leaveType = LeaveType::create([
        'company_id' => $company->id,
        'code' => 'ANNUAL',
        'name' => 'Annual leave',
        'is_active' => true,
    ]);
    $leaveRequest = LeaveRequest::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-03',
        'hours' => 24,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->from("/{$company->slug}/leave-requests/{$leaveRequest->id}/edit")
        ->put("/{$company->slug}/leave-requests/{$leaveRequest->id}", [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-01',
            'hours' => 8,
        ])
        ->assertSessionHasErrors('end_date');

    expect($leaveRequest->fresh()->end_date->toDateString())->toBe('2026-10-03');
});

test('the payslip edit page renders a draft payslip with its lines and type pickers', function () {
    [$user, $company] = payrollPageCompany();

    $employee = payrollPageEmployee($company);
    $period = PayrollPeriod::create([
        'company_id' => $company->id,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'payment_date' => '2026-09-30',
        'status' => 'open',
    ]);
    $earningType = EarningType::create([
        'company_id' => $company->id,
        'code' => 'BASE',
        'name' => 'Base salary',
        'is_active' => true,
    ]);
    $payslip = Payslip::create([
        'company_id' => $company->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'payslip_number' => 'PS-PAGE-1',
        'currency' => 'SAR',
        'base_currency' => 'SAR',
        'status' => 'draft',
    ]);
    $payslip->lines()->create([
        'line_type' => 'earning',
        'earning_type_id' => $earningType->id,
        'description' => 'Base salary',
        'quantity' => 1,
        'rate' => 5000,
        'amount' => 5000,
    ]);

    $this->actingAs($user)
        ->get("/{$company->slug}/payslips/{$payslip->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payroll/Payslips/Edit', false)
            ->where('payslip.id', $payslip->id)
            ->has('payslip.lines', 1)
            ->has('earningTypes', 1)
            ->has('deductionTypes', 0));
});

test('a payslip edit rewrites its lines through the update route', function () {
    [$user, $company] = payrollPageCompany();

    $employee = payrollPageEmployee($company);
    $period = PayrollPeriod::create([
        'company_id' => $company->id,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'payment_date' => '2026-09-30',
        'status' => 'open',
    ]);
    $earningType = EarningType::create([
        'company_id' => $company->id,
        'code' => 'BASE',
        'name' => 'Base salary',
        'is_active' => true,
    ]);
    $payslip = Payslip::create([
        'company_id' => $company->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'payslip_number' => 'PS-PAGE-2',
        'currency' => 'SAR',
        'base_currency' => 'SAR',
        'status' => 'draft',
    ]);
    $payslip->lines()->create([
        'line_type' => 'earning',
        'earning_type_id' => $earningType->id,
        'description' => 'Base salary',
        'quantity' => 1,
        'rate' => 5000,
        'amount' => 5000,
    ]);

    $this->actingAs($user)
        ->put("/{$company->slug}/payslips/{$payslip->id}", [
            'notes' => 'Corrected after review',
            'lines' => [
                [
                    'line_type' => 'earning',
                    'earning_type_id' => $earningType->id,
                    'description' => 'Base salary',
                    'quantity' => 1,
                    'rate' => 6000,
                    'amount' => 6000,
                ],
            ],
        ])
        ->assertRedirect("/{$company->slug}/payslips/{$payslip->id}");

    $updated = $payslip->fresh(['lines']);
    expect($updated->notes)->toBe('Corrected after review')
        ->and($updated->lines)->toHaveCount(1)
        ->and((float) $updated->lines->first()->amount)->toBe(6000.0)
        // The totals trigger recomputes from the lines, so the rewrite has to
        // reach the header figures too -- not just the line rows.
        ->and((float) $updated->gross_pay)->toBe(6000.0);
});

test('a payslip edit with a negative line amount is rejected inline', function () {
    [$user, $company] = payrollPageCompany();

    $employee = payrollPageEmployee($company);
    $period = PayrollPeriod::create([
        'company_id' => $company->id,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'payment_date' => '2026-09-30',
        'status' => 'open',
    ]);
    $earningType = EarningType::create([
        'company_id' => $company->id,
        'code' => 'BASE',
        'name' => 'Base salary',
        'is_active' => true,
    ]);
    $payslip = Payslip::create([
        'company_id' => $company->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'payslip_number' => 'PS-PAGE-3',
        'currency' => 'SAR',
        'base_currency' => 'SAR',
        'status' => 'draft',
    ]);
    $payslip->lines()->create([
        'line_type' => 'earning',
        'earning_type_id' => $earningType->id,
        'description' => 'Base salary',
        'quantity' => 1,
        'rate' => 5000,
        'amount' => 5000,
    ]);

    $this->actingAs($user)
        ->from("/{$company->slug}/payslips/{$payslip->id}/edit")
        ->put("/{$company->slug}/payslips/{$payslip->id}", [
            'lines' => [
                [
                    'line_type' => 'earning',
                    'earning_type_id' => $earningType->id,
                    'description' => 'Base salary',
                    'quantity' => 1,
                    'rate' => 5000,
                    'amount' => -1,
                ],
            ],
        ])
        ->assertSessionHasErrors('lines.0.amount');

    expect((float) $payslip->fresh()->gross_pay)->toBe(5000.0);
});
