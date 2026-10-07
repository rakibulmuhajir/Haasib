<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Services\MonthEndPayrollDraft;
use App\Modules\Payroll\Services\PayrollPostingService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Approving payroll: a payslip with nothing left to pay is paid on the spot; one with money left
 * stays owed (setting on_entry) or is paid from the chosen account (setting on_approval).
 * Self-contained helpers, distinctly named, as each test file runs on its own.
 */
function payRecCompany(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Pay Rec '.str()->random(6),
        'slug' => 'pay-rec-'.str()->lower(str()->random(10)),
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
        'company_id' => $company->id, 'employee_number' => 'EMP-PR-1', 'first_name' => 'Pay', 'last_name' => 'Rec',
        'hire_date' => '2026-01-01', 'employment_type' => 'full_time', 'employment_status' => 'active',
        'pay_frequency' => 'monthly', 'base_salary' => 20000, 'currency' => 'PKR', 'is_active' => true,
    ]);

    return [$user, $company, $employee];
}

function payRecAccount(Company $company, string $code, string $subtype = 'cash'): Account
{
    return Account::create([
        'company_id' => $company->id, 'code' => $code, 'name' => ($subtype === 'cash' ? 'Cash ' : 'Bank ').$code,
        'type' => 'asset', 'subtype' => $subtype, 'normal_balance' => 'debit', 'is_active' => true,
    ]);
}

function payRecDraft(Company $company): Payslip
{
    app(MonthEndPayrollDraft::class)->prepare($company, '2026-09-01');

    return Payslip::where('company_id', $company->id)->sole();
}

function payRecSetting(Company $company, string $mode, ?string $accountId = null): void
{
    $company->refresh();
    $company->settings = array_merge((array) $company->settings, ['payroll' => ['payment_recording' => $mode, 'payment_account_id' => $accountId]]);
    $company->save();
}

test('employee statement trails preserve saved movements and mark expected salary', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-30 10:00:00'));
    [$user, $company, $employee] = payRecCompany();
    $service = app(\App\Modules\Payroll\Services\EmployeeStatementService::class);
    $expected = $service->statement($employee, '2026-09-01', '2026-09-30', true);
    expect($expected['valueTrail']['nodes']['statement:closing']['estimated'])->toBeTrue();
    $payslip = payRecDraft($company);
    $payslip->update(['status' => 'approved']);
    $employee->update(['base_salary' => 90000]);
    $plain = $service->statement($employee->fresh(), '2026-09-01', '2026-09-30');
    $traced = $service->statement($employee->fresh(), '2026-09-01', '2026-09-30', true);
    $graph = $traced['valueTrail'];
    unset($traced['valueTrail']);
    expect($traced)->toBe($plain)
        ->and($graph['nodes']['statement:closing']['estimated'])->toBeFalse()
        ->and($plain['closing_balance'])->toBe((float) $payslip->gross_pay);
    foreach ($graph['nodes'] as $node) {
        if ($node['children']) {
            expect(round(collect($node['children'])->sum(fn ($id) => $graph['nodes'][$id]['value']), 2))->toBe($node['value']);
        }
    }
    $url = "/{$company->slug}/reports/statements?kind=employee&id={$employee->id}&from=2026-09-01&to=2026-09-30";
    test()->actingAs($user)->get($url)->assertOk();
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => \Inertia\Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'accounting/reports/Statement', 'X-Inertia-Partial-Data' => 'statement,valueTrail'];
    test()->get($url, $headers)->assertOk()->assertJsonPath('props.valueTrail.nodes.statement:closing.value', (int) $plain['closing_balance']);
});

test('payroll trails use saved payslip lines and load only on demand', function () {
    [$user, $company, $employee] = payRecCompany();
    $payslip = payRecDraft($company);
    $employee->update(['base_salary' => 90000]);
    enterCompany($company);
    $service = app(\App\Modules\Payroll\Services\PayrollValueTrail::class);
    $graph = app(CompanyContextService::class)->withContext($company, fn () => $service->build($company, collect([$payslip]), $user, 'September payroll'));
    $prefix = 'payslip:'.$payslip->id;
    expect($graph['nodes'][$prefix.':gross']['value'])->toBe(20000.0)
        ->and($graph['nodes'][$prefix.':net']['value'])->toBe((float) $payslip->net_pay);
    $children = $graph['nodes'][$prefix.':gross']['children'];
    expect(array_sum(array_map(fn ($id) => $graph['nodes'][$id]['value'], $children)))->toBe(20000.0);
    $url = '/'.$company->slug.'/payslips/'.$payslip->id;
    $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('valueTrailsAvailable', true)->missing('valueTrail'));
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => \Inertia\Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'Payroll/Payslips/Show', 'X-Inertia-Partial-Data' => 'payslip,valueTrail'];
    $this->get($url, $headers)->assertOk()->assertJsonPath('props.valueTrail.nodes.'.$prefix.':gross.value', 20000);
    $monthlyHeaders = array_merge($headers, ['X-Inertia-Partial-Component' => 'Payroll/Dashboard/Index', 'X-Inertia-Partial-Data' => 'rows,month,valueTrail']);
    $this->get('/'.$company->slug.'/payroll?month=2026-09', $monthlyHeaders)->assertOk()
        ->assertJsonPath('props.valueTrail.nodes.'.$prefix.':gross.value', 20000)
        ->assertJsonPath('props.month', '2026-09');
    $user->update(['settings' => ['show_value_trails' => false]]);
    $this->get($url, $headers)->assertOk()->assertJsonPath('props.valueTrail', null);
});

test('payroll evidence refuses unpermitted users and does not fabricate formulas for mismatched saved totals', function () {
    [$user, $company] = payRecCompany();
    $payslip = payRecDraft($company);
    $payslip->gross_pay = 21000;
    enterCompany($company);
    $service = app(\App\Modules\Payroll\Services\PayrollValueTrail::class);
    $graph = app(CompanyContextService::class)->withContext($company, fn () => $service->build($company, collect([$payslip]), $user, 'Payroll'));
    $prefix = 'payslip:'.$payslip->id;
    expect($graph['nodes'][$prefix.':gross']['children'])->toBe([])
        ->and($graph['nodes'][$prefix.':gross']['formula'])->toBeNull();
    $unpermitted = User::factory()->withoutTwoFactor()->create();
    expect($service->build($company, collect([$payslip]), $unpermitted, 'Payroll'))->toBeNull();
});

test('a payslip whose advances cover it is paid on approval, with no payment journal', function () {
    [$user, $company, $employee] = payRecCompany();
    SalaryAdvance::create([
        'company_id' => $company->id, 'employee_id' => $employee->id, 'advance_date' => '2026-09-10',
        'amount' => 20000, 'amount_outstanding' => 20000, 'reason' => 'Advance', 'status' => 'pending', 'payment_method' => 'cash',
    ]);
    payRecSetting($company, 'on_entry');
    $payslip = payRecDraft($company);

    app(PayrollPostingService::class)->approve($payslip, $user->id);

    $payslip->refresh();
    expect((float) $payslip->net_pay)->toBe(0.0)
        ->and($payslip->status)->toBe('paid')
        ->and($payslip->paid_at)->not->toBeNull()
        ->and($payslip->payment_gl_transaction_id)->toBeNull();
});

test('on_entry leaves an approved payslip with money left owed', function () {
    [$user, $company] = payRecCompany();
    payRecAccount($company, '1011');
    payRecSetting($company, 'on_entry');
    $payslip = payRecDraft($company);

    app(PayrollPostingService::class)->approve($payslip, $user->id);

    $payslip->refresh();
    expect($payslip->status)->toBe('approved')
        ->and($payslip->payment_gl_transaction_id)->toBeNull();
});

test('on_approval pays the net from the chosen cash account when payroll is approved', function () {
    [$user, $company] = payRecCompany();
    payRecAccount($company, '1011');
    $chosen = payRecAccount($company, '1012');
    payRecSetting($company, 'on_approval', $chosen->id);
    $payslip = payRecDraft($company);

    app(PayrollPostingService::class)->approve($payslip, $user->id);

    $payslip->refresh();
    expect($payslip->status)->toBe('paid')
        ->and($payslip->payment_method)->toBe('cash')
        ->and($payslip->payment_gl_transaction_id)->not->toBeNull();

    $payment = Transaction::where('company_id', $company->id)->findOrFail($payslip->payment_gl_transaction_id);
    $credit = DB::table('acct.journal_entries')
        ->where('transaction_id', $payment->id)->where('account_id', $chosen->id)->sum('credit_amount');
    expect((float) $credit)->toBe(20000.0);
});

test('outstanding payroll trails follow full payment and undoing its posting', function () {
    [$user, $company] = payRecCompany();
    $cash = payRecAccount($company, '1011');
    payRecSetting($company, 'on_approval', $cash->id);
    $payslip = payRecDraft($company);
    $posting = app(PayrollPostingService::class);
    $posting->approve($payslip, $user->id);
    $payslip->refresh();
    $service = app(\App\Modules\Payroll\Services\PayrollValueTrail::class);
    $build = fn () => app(CompanyContextService::class)->withContext($company, fn () => $service->build($company, collect([$payslip]), $user, 'Payroll'));
    $prefix = 'payslip:'.$payslip->id;
    $graph = $build();
    expect($graph['nodes'][$prefix.':outstanding']['value'])->toBe(0.0)
        ->and($graph['nodes'][$prefix.':paid']['value'])->toBe(20000.0);
    $posting->reversePayment($payslip, $user->id, 'Wrong payment date');
    $payslip->refresh();
    $graph = $build();
    expect($graph['nodes'][$prefix.':outstanding']['value'])->toBe(20000.0)
        ->and($graph['nodes'][$prefix.':paid']['value'])->toBe(0.0);
});

test('the settings endpoint saves, keeps other keys, and rejects a non-cash account', function () {
    [$user, $company] = payRecCompany();
    $cash = payRecAccount($company, '1011');
    $expense = Account::create([
        'company_id' => $company->id, 'code' => '6999', 'name' => 'Misc expense',
        'type' => 'expense', 'subtype' => 'operating_expenses', 'normal_balance' => 'debit', 'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post("/{$company->slug}/payroll/settings", ['payment_recording' => 'on_approval', 'payment_account_id' => $expense->id])
        ->assertSessionHasErrors('payment_account_id');

    $this->actingAs($user)
        ->post("/{$company->slug}/payroll/settings", ['payment_recording' => 'on_approval', 'payment_account_id' => $cash->id])
        ->assertSessionHasNoErrors();

    $settings = (array) $company->refresh()->settings;
    expect($settings['payroll'])->toBe(['payment_recording' => 'on_approval', 'payment_account_id' => $cash->id])
        ->and($settings['modules']['payroll'])->toBeTrue()
        ->and(app(PayrollPostingService::class)->paymentRecording($company->id))->toBe(['mode' => 'on_approval', 'account_id' => $cash->id]);
});
