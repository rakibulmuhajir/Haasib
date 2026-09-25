<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Services\PayrollPostingService;
use Illuminate\Validation\ValidationException;

/**
 * "Salaries owed" opening balances: a salary earned before the opening date and not yet paid.
 * Unlike the other opening sections it does not just post a journal line - it creates a real,
 * approved payslip the payroll module can pay normally, whose accrual IS the opening line (no
 * second accrual is ever posted for it). Paying it off has to hit the exact account the opening
 * line credited, or the two postings would never net to zero.
 */
require_once __DIR__.'/OpeningBalanceFixtures.php';

function salariesOwedEmployee(array $f, string $number = 'EMP-SAL-1'): Employee
{
    return Employee::create([
        'company_id' => $f['company']->id,
        'employee_number' => $number,
        'first_name' => 'Bilal',
        'last_name' => 'Sheikh',
        'hire_date' => '2025-01-01',
        'employment_type' => 'full_time',
        'employment_status' => 'active',
        'pay_frequency' => 'monthly',
        'base_salary' => 40000,
        'currency' => 'PKR',
        'is_active' => true,
    ]);
}

test('saving a salaries_owed row posts the credit to salaries payable and the debit to opening balance equity, and creates one approved payslip due on the opening date', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));

    $f = openingBalanceFixture();
    $employee = salariesOwedEmployee($f);

    $result = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'salaries_owed' => [['employee_id' => $employee->id, 'amount' => 40000]],
    ]);

    $journalId = $result['data']['journal_id'];
    $entries = Transaction::find($journalId)->journalEntries;

    $payrollService = app(PayrollPostingService::class);
    $payableAccountId = $payrollService->ensureDefaultPayrollAccounts($f['company']->id)['payroll_payable']['id'];
    $equityAccount = Account::where('company_id', $f['company']->id)->where('code', '3080')->first();

    $payableEntry = $entries->first(fn ($e) => $e->account_id === $payableAccountId && (float) $e->credit_amount === 40000.0);
    $equityEntry = $entries->first(fn ($e) => $e->account_id === $equityAccount->id && (float) $e->debit_amount === 40000.0);
    expect($payableEntry)->not->toBeNull()
        ->and($equityEntry)->not->toBeNull();

    $payslips = Payslip::where('company_id', $f['company']->id)->where('employee_id', $employee->id)->get();
    expect($payslips)->toHaveCount(1);

    $payslip = $payslips->first()->load('payrollPeriod');
    expect($payslip->status)->toBe('approved')
        ->and((float) $payslip->net_pay)->toBe(40000.0)
        ->and($payslip->gl_transaction_id)->toBe($journalId)
        ->and($payslip->payrollPeriod->payment_date->toDateString())->toBe('2026-08-31');
});

test('paying the opening salary payslip the next day clears the salaries payable account to zero', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));

    $f = openingBalanceFixture();
    $employee = salariesOwedEmployee($f);

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'salaries_owed' => [['employee_id' => $employee->id, 'amount' => 40000]],
    ]);

    $payslip = Payslip::where('company_id', $f['company']->id)->where('employee_id', $employee->id)->firstOrFail();

    $payrollService = app(PayrollPostingService::class);
    $payrollService->markPaid($payslip->fresh(), [
        'paid_on' => '2026-09-01',
        'payment_method' => 'bank_transfer',
        'payment_account_id' => $f['accounts']['bank']->id,
    ], (string) $f['user']->id);

    $payableAccountId = $payrollService->ensureDefaultPayrollAccounts($f['company']->id)['payroll_payable']['id'];
    expect(ledgerBalance(Account::find($payableAccountId)))->toBe(0.0)
        ->and($payslip->fresh()->status)->toBe('paid');
});

test('the opening salary payslip is listed by Payslip::payableOn the day after the opening date', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));

    $f = openingBalanceFixture();
    $employee = salariesOwedEmployee($f);

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'salaries_owed' => [['employee_id' => $employee->id, 'amount' => 40000]],
    ]);

    $payslip = Payslip::where('company_id', $f['company']->id)->where('employee_id', $employee->id)->firstOrFail();

    expect(Payslip::payableOn('2026-09-01')->pluck('id')->all())->toContain($payslip->id);
});

test('re-saving salaries_owed with a different amount replaces the unpaid opening payslip', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));

    $f = openingBalanceFixture();
    $employee = salariesOwedEmployee($f);

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'salaries_owed' => [['employee_id' => $employee->id, 'amount' => 40000]],
    ]);
    $firstPayslip = Payslip::where('company_id', $f['company']->id)->where('employee_id', $employee->id)->firstOrFail();

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'salaries_owed' => [['employee_id' => $employee->id, 'amount' => 45000]],
    ]);

    expect(Payslip::find($firstPayslip->id))->toBeNull();

    $payslips = Payslip::where('company_id', $f['company']->id)->where('employee_id', $employee->id)->get();
    expect($payslips)->toHaveCount(1)
        ->and((float) $payslips->first()->net_pay)->toBe(45000.0)
        ->and($payslips->first()->id)->not->toBe($firstPayslip->id);
});

test('re-saving opening balances after the opening salary has been paid is refused', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-24 10:00:00'));

    $f = openingBalanceFixture();
    $employee = salariesOwedEmployee($f);

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'salaries_owed' => [['employee_id' => $employee->id, 'amount' => 40000]],
    ]);
    $payslip = Payslip::where('company_id', $f['company']->id)->where('employee_id', $employee->id)->firstOrFail();

    $payrollService = app(PayrollPostingService::class);
    $payrollService->markPaid($payslip->fresh(), [
        'paid_on' => '2026-09-01',
        'payment_method' => 'bank_transfer',
        'payment_account_id' => $f['accounts']['bank']->id,
    ], (string) $f['user']->id);

    expect(fn () => dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'salaries_owed' => [['employee_id' => $employee->id, 'amount' => 50000]],
    ]))->toThrow(ValidationException::class);

    expect(Payslip::find($payslip->id)->status)->toBe('paid');
});
