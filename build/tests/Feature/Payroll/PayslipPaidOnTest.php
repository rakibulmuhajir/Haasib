<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Services\PayrollPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The owner picks the date a salary was actually paid on ("paid_on"), rather than the payment
 * being silently dated to the payroll period's planned payment_date. That date also has to be
 * the one the payment journal is posted on and the one stored on the payslip, a wrongly-dated
 * payment has to be undoable, and a salary that's still due should keep showing up as due on
 * every later day until it's paid - not just on its exact pay day.
 */
function paidOnCompany(): Company
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Paid-on '.str()->random(6),
        'slug' => 'paid-on-'.str()->lower(str()->random(10)),
        'base_currency' => 'PKR',
        'settings' => ['modules' => ['payroll' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    Auth::login($user);

    return $company;
}

function paidOnPaymentAccount(Company $company): Account
{
    return Account::create([
        'company_id' => $company->id,
        'code' => '1011',
        'name' => 'Cash on Hand',
        'type' => 'asset',
        'subtype' => 'cash',
        'normal_balance' => 'debit',
        'is_active' => true,
        'is_system' => true,
    ]);
}

function paidOnApprovedPayslip(Company $company, string $paymentDate): Payslip
{
    $employee = Employee::create([
        'company_id' => $company->id,
        'employee_number' => 'EMP-'.str()->upper(str()->random(5)),
        'first_name' => 'Station',
        'last_name' => 'Attendant',
        'hire_date' => '2026-01-01',
        'employment_type' => 'full_time',
        'employment_status' => 'active',
        'pay_frequency' => 'monthly',
        'base_salary' => 35000,
        'currency' => 'PKR',
        'is_active' => true,
    ]);

    $period = PayrollPeriod::firstOrCreate(
        ['company_id' => $company->id, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31'],
        ['payment_date' => $paymentDate, 'status' => 'open'],
    );

    $service = app(PayrollPostingService::class);

    $payslip = Payslip::create([
        'company_id' => $company->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'payslip_number' => $service->nextPayslipNumber($company->id),
        // Same currency as the company, so no exchange rate is required and base amounts equal
        // the amounts.
        'currency' => 'PKR',
        'exchange_rate' => null,
        'base_currency' => 'PKR',
        'gross_pay' => 35000,
        'net_pay' => 35000,
        'base_gross_pay' => 35000,
        'base_net_pay' => 35000,
        'status' => 'draft',
    ]);

    $earningType = $service->ensureBaseSalaryEarningType($company->id);
    $payslip->lines()->create([
        'line_type' => 'earning',
        'earning_type_id' => $earningType->id,
        'description' => 'Base salary',
        'quantity' => 1,
        'rate' => 35000,
        'amount' => 35000,
        'sort_order' => 1,
    ]);

    $service->approve($payslip->fresh(), (string) auth()->id());

    return $payslip->fresh();
}

test('marking a payslip paid posts the payment journal on the chosen paid_on date, not the period payment date', function () {
    $company = paidOnCompany();
    $account = paidOnPaymentAccount($company);
    // Period plans payment for 1 September; the owner actually pays it on 5 September.
    $payslip = paidOnApprovedPayslip($company, '2026-09-01');

    $service = app(PayrollPostingService::class);
    $transaction = $service->markPaid($payslip, [
        'paid_on' => '2026-09-05',
        'payment_method' => 'cash',
        'payment_account_id' => $account->id,
    ], (string) auth()->id());

    $payslip->refresh();

    expect($transaction->transaction_date->toDateString())->toBe('2026-09-05')
        ->and($payslip->status)->toBe('paid')
        ->and($payslip->paid_at->toDateString())->toBe('2026-09-05')
        ->and($payslip->payment_gl_transaction_id)->toBe($transaction->id);
});

test('a payslip due on an earlier date is still listed as payable on a later date', function () {
    $company = paidOnCompany();
    // Due 1 September, still unpaid on 5 September - still owed, so it stays on the list.
    $payslip = paidOnApprovedPayslip($company, '2026-09-01');

    expect(Payslip::payableOn('2026-09-05')->pluck('id')->all())->toContain($payslip->id);
});

test('undoing a payment reverses the journal and lets the payslip be paid again on another date', function () {
    $company = paidOnCompany();
    $account = paidOnPaymentAccount($company);
    $payslip = paidOnApprovedPayslip($company, '2026-09-01');

    $service = app(PayrollPostingService::class);
    $firstPayment = $service->markPaid($payslip, [
        'paid_on' => '2026-09-05',
        'payment_method' => 'cash',
        'payment_account_id' => $account->id,
    ], (string) auth()->id());

    $service->reversePayment($payslip->fresh(), (string) auth()->id(), 'Wrong date entered');

    $payslip->refresh();
    expect($payslip->status)->toBe('approved')
        ->and($payslip->paid_at)->toBeNull()
        ->and($payslip->payment_gl_transaction_id)->toBeNull();

    $firstPayment->refresh();
    expect($firstPayment->reversed_by_id)->not->toBeNull();

    // Paying again, on the correct date, works cleanly.
    $secondPayment = $service->markPaid($payslip->fresh(), [
        'paid_on' => '2026-09-06',
        'payment_method' => 'bank_transfer',
        'payment_account_id' => $account->id,
    ], (string) auth()->id());

    $payslip->refresh();
    expect($secondPayment->transaction_date->toDateString())->toBe('2026-09-06')
        ->and($payslip->status)->toBe('paid')
        ->and($payslip->paid_at->toDateString())->toBe('2026-09-06');
});
