<?php

namespace App\Modules\Payroll\Services;

use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Payroll\Models\DeductionType;
use App\Modules\Payroll\Models\EarningType;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Models\SalaryAdvanceRecovery;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollPostingService
{
    public function __construct(private readonly GlPostingService $postingService) {}

    public function prepareAutomaticAdvanceDeductions(Payslip $payslip): void
    {
        $this->setRlsContext($payslip->company_id);

        $payslip->loadMissing(['lines', 'employee']);

        if ($payslip->status !== 'draft') {
            return;
        }

        $deductionType = $this->ensureSalaryAdvanceDeductionType($payslip->company_id);

        $payslip->lines()
            ->whereNotNull('salary_advance_id')
            ->delete();

        $payslip->refresh()->load('lines');

        $grossPay = (float) $payslip->lines
            ->where('line_type', 'earning')
            ->sum(fn ($line) => (float) $line->amount);
        $manualDeductions = (float) $payslip->lines
            ->where('line_type', 'deduction')
            ->sum(fn ($line) => (float) $line->amount);

        $availableNet = max(0, round($grossPay - $manualDeductions, 2));
        $advanceRecoveryCap = round($grossPay * 0.5, 2);
        $remaining = min($availableNet, $advanceRecoveryCap);

        if ($remaining <= 0) {
            return;
        }

        $advances = $payslip->employee
            ->outstandingAdvances()
            ->orderBy('advance_date')
            ->orderBy('created_at')
            ->get();

        $sortOrder = ((int) $payslip->lines()->max('sort_order')) + 1;

        foreach ($advances as $advance) {
            if ($remaining <= 0) {
                break;
            }

            $rate = (float) ($payslip->exchange_rate ?: 1);
            $outstandingInPayslipCurrency = round((float) $advance->amount_outstanding / $rate, 2);
            $amount = min($outstandingInPayslipCurrency, $remaining);
            if ($amount <= 0) {
                continue;
            }

            $payslip->lines()->create([
                'line_type' => 'deduction',
                'deduction_type_id' => $deductionType->id,
                'salary_advance_id' => $advance->id,
                'description' => 'Salary advance recovery - '.$advance->advance_date->format('Y-m-d'),
                'quantity' => 1,
                'rate' => $amount,
                'amount' => round($amount, 2),
                'sort_order' => $sortOrder++,
            ]);

            $remaining = round($remaining - $amount, 2);
        }
    }

    public function ensureDefaultPayrollAccounts(string $companyId): array
    {
        $this->setRlsContext($companyId);

        return [
            'salary_expense' => $this->accountSummary($this->findExpenseAccount($companyId, ['Salaries & Wages', 'Salaries', 'Wages', 'Salary Expense'], ['6200', '6150'])),
            'payroll_payable' => $this->accountSummary($this->findLiabilityAccount($companyId, ['Accrued Salaries & Wages', 'Salary Payable', 'Payroll Payable'], ['2210'], '2211', 'Payroll Salaries Payable')),
            'deduction_payable' => $this->accountSummary($this->findLiabilityAccount($companyId, ['Payroll Taxes Payable', 'Payroll Deductions Payable'], ['2220'], '2221', 'Payroll Deductions Payable')),
            'employee_advances' => $this->accountSummary($this->findAssetAccount($companyId, ['Employee Advances'], ['1150'], '1150', 'Employee Advances', 'other_current_asset')),
            'payment' => $this->accountSummary($this->resolvePaymentAccount($companyId, null)),
        ];
    }

    public function createSalaryAdvance(array $data, string $companyId, string $userId, string $baseCurrency): SalaryAdvance
    {
        return DB::transaction(function () use ($data, $companyId, $userId, $baseCurrency) {
            $this->setRlsContext($companyId);

            $amount = round((float) $data['amount'], 2);
            $paymentAccountId = $this->resolveBasePaymentAccount($companyId, $data['bank_account_id'] ?? null, $baseCurrency);
            $advanceAccountId = $this->findAssetAccount($companyId, ['Employee Advances'], ['1150'], '1150', 'Employee Advances', 'other_current_asset');

            $advance = SalaryAdvance::create([
                'company_id' => $companyId,
                'employee_id' => $data['employee_id'],
                'advance_date' => $data['advance_date'],
                'amount' => $amount,
                'amount_recovered' => 0,
                'amount_outstanding' => $amount,
                'reason' => $data['reason'] ?? null,
                'status' => 'pending',
                'payment_method' => $data['payment_method'] ?? 'cash',
                'bank_account_id' => $paymentAccountId,
                'reference' => $data['reference'] ?? null,
                'advance_account_id' => $advanceAccountId,
                'approved_by_user_id' => $userId,
                'approved_at' => now(),
                'recorded_by_user_id' => $userId,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->postingService->postBalancedTransaction([
                'company_id' => $companyId,
                'transaction_number' => 'ADV-'.strtoupper(substr($advance->id, 0, 8)),
                'transaction_type' => 'salary_advance',
                'date' => $advance->advance_date,
                'currency' => $baseCurrency,
                'base_currency' => $baseCurrency,
                'description' => 'Salary advance',
                'reference_type' => 'pay.salary_advances',
                'reference_id' => $advance->id,
                'metadata' => [
                    'employee_id' => $advance->employee_id,
                    'payment_method' => $advance->payment_method,
                    'reference' => $advance->reference,
                    'plain_english' => 'Advance given increases Employee Advances and reduces the selected cash or bank account.',
                ],
            ], [
                [
                    'account_id' => $advanceAccountId,
                    'type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Employee advance recoverable from salary',
                ],
                [
                    'account_id' => $paymentAccountId,
                    'type' => 'credit',
                    'amount' => $amount,
                    'description' => 'Cash or bank paid to employee',
                ],
            ]);

            return $advance;
        });
    }

    public function ensureBaseSalaryEarningType(string $companyId): EarningType
    {
        $this->setRlsContext($companyId);

        return EarningType::updateOrCreate(
            [
                'company_id' => $companyId,
                'code' => 'BASE',
            ],
            [
                'name' => 'Base Salary',
                'description' => 'Regular salary for payroll generation.',
                'is_taxable' => true,
                'affects_overtime' => false,
                'is_recurring' => true,
                'gl_account_id' => $this->findExpenseAccount($companyId, ['Salaries & Wages', 'Salaries', 'Wages', 'Salary Expense'], ['6200', '6150']),
                'is_system' => true,
                'is_active' => true,
            ]
        );
    }

    public function nextPayslipNumber(string $companyId): string
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["pay.payslip_number:{$companyId}"]);

        $lastNumber = Payslip::where('company_id', $companyId)
            ->where('payslip_number', 'like', 'PS%')
            ->whereRaw("payslip_number ~ '^PS[0-9]+$'")
            ->selectRaw("MAX((substring(payslip_number from '[0-9]+$'))::integer) as max_number")
            ->value('max_number');

        return 'PS'.str_pad((string) (((int) $lastNumber) + 1), 6, '0', STR_PAD_LEFT);
    }

    public function generatePayslipsForPeriod(PayrollPeriod $period, string $baseCurrency): int
    {
        return DB::transaction(function () use ($period, $baseCurrency) {
            $this->setRlsContext($period->company_id);

            $earningType = $this->ensureBaseSalaryEarningType($period->company_id);
            $created = 0;

            Employee::where('company_id', $period->company_id)
                ->where('is_active', true)
                ->where('employment_status', 'active')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->each(function (Employee $employee) use ($period, $earningType, $baseCurrency, &$created) {
                    $exists = Payslip::where('company_id', $period->company_id)
                        ->where('payroll_period_id', $period->id)
                        ->where('employee_id', $employee->id)
                        ->exists();

                    if ($exists || (float) $employee->base_salary <= 0) {
                        return;
                    }

                    $payslip = Payslip::create([
                        'company_id' => $period->company_id,
                        'payroll_period_id' => $period->id,
                        'employee_id' => $employee->id,
                        'payslip_number' => $this->nextPayslipNumber($period->company_id),
                        'currency' => $employee->currency ?: $baseCurrency,
                        'exchange_rate' => $this->resolveExchangeRate($period->company_id, $employee->currency ?: $baseCurrency, $baseCurrency),
                        'base_currency' => $baseCurrency,
                        'notes' => 'Generated from employee salary.',
                    ]);

                    $amount = round((float) $employee->base_salary, 2);
                    $payslip->lines()->create([
                        'line_type' => 'earning',
                        'earning_type_id' => $earningType->id,
                        'description' => 'Base salary',
                        'quantity' => 1,
                        'rate' => $amount,
                        'amount' => $amount,
                        'sort_order' => 1,
                    ]);

                    $this->prepareAutomaticAdvanceDeductions($payslip);
                    $created++;
                });

            return $created;
        });
    }

    public function approve(Payslip $payslip, string $userId): Transaction
    {
        return DB::transaction(function () use ($payslip, $userId) {
            $this->prepareAutomaticAdvanceDeductions($payslip);

            $payslip->refresh()->load([
                'employee',
                'payrollPeriod',
                'lines.earningType',
                'lines.deductionType',
            ]);

            $this->validateCurrencySnapshot($payslip);

            if ($payslip->gl_transaction_id) {
                $payslip->update([
                    'status' => 'approved',
                    'approved_at' => $payslip->approved_at ?? now(),
                    'approved_by_user_id' => $payslip->approved_by_user_id ?? $userId,
                ]);

                return Transaction::where('company_id', $payslip->company_id)
                    ->findOrFail($payslip->gl_transaction_id);
            }

            $accounts = $this->resolveAccounts($payslip);
            $entries = [];

            foreach ($this->groupLinesByAccount($payslip, 'earning', $accounts['salary_expense']) as $accountId => $amount) {
                $entries[] = [
                    'account_id' => $accountId,
                    'type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Payroll earnings expense',
                ];
            }

            foreach ($this->groupLinesByAccount($payslip, 'employer', $accounts['employer_expense']) as $accountId => $amount) {
                $entries[] = [
                    'account_id' => $accountId,
                    'type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Employer payroll costs',
                ];
            }

            $netPay = round((float) $payslip->net_pay, 2);
            if ($netPay > 0) {
                $entries[] = [
                    'account_id' => $accounts['payroll_payable'],
                    'type' => 'credit',
                    'amount' => $netPay,
                    'description' => 'Net pay owed to employee',
                ];
            }

            foreach ($payslip->lines->where('line_type', 'deduction') as $line) {
                $amount = round((float) $line->amount, 2);
                if ($amount <= 0) {
                    continue;
                }

                if ($line->salary_advance_id) {
                    $entries[] = [
                        'account_id' => $accounts['employee_advances'],
                        'type' => 'credit',
                        'amount' => $amount,
                        'description' => 'Salary advance recovery',
                    ];

                    SalaryAdvanceRecovery::create([
                        'company_id' => $payslip->company_id,
                        'salary_advance_id' => $line->salary_advance_id,
                        'payslip_id' => $payslip->id,
                        'recovery_date' => $payslip->payrollPeriod->payment_date,
                        'amount' => round($amount * (float) ($payslip->exchange_rate ?: 1), 2),
                        'recovery_type' => 'payroll_deduction',
                        'recorded_by_user_id' => $userId,
                    ]);

                    continue;
                }

                $entries[] = [
                    'account_id' => $this->validAccountId($line->deductionType?->gl_account_id, $payslip->company_id)
                        ?? $accounts['deduction_payable'],
                    'type' => 'credit',
                    'amount' => $amount,
                    'description' => $line->deductionType?->name ?? 'Payroll deduction',
                ];
            }

            foreach ($payslip->lines->where('line_type', 'employer') as $line) {
                $amount = round((float) $line->amount, 2);
                if ($amount <= 0) {
                    continue;
                }

                $entries[] = [
                    'account_id' => $accounts['deduction_payable'],
                    'type' => 'credit',
                    'amount' => $amount,
                    'description' => 'Employer payroll cost payable',
                ];
            }

            if (empty($entries)) {
                throw new \RuntimeException('Cannot approve a payslip with no payroll amounts.');
            }

            $transaction = $this->postingService->postBalancedTransaction([
                'company_id' => $payslip->company_id,
                'transaction_type' => 'payroll_accrual',
                'date' => $payslip->payrollPeriod->period_end,
                'currency' => $payslip->currency,
                'base_currency' => $payslip->base_currency,
                'exchange_rate' => $payslip->exchange_rate,
                'description' => 'Payroll accrual - '.$payslip->payslip_number,
                'reference_type' => 'pay.payslips',
                'reference_id' => $payslip->id,
                'metadata' => [
                    'employee_id' => $payslip->employee_id,
                    'payroll_period_id' => $payslip->payroll_period_id,
                ],
            ], $this->combineEntries($entries));

            $payslip->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by_user_id' => $userId,
                'gl_transaction_id' => $transaction->id,
            ]);

            return $transaction;
        });
    }

    public function markPaid(Payslip $payslip, array $data, string $userId): ?Transaction
    {
        return DB::transaction(function () use ($payslip, $data, $userId) {
            $payslip->refresh()->load(['payrollPeriod']);

            if (! $payslip->gl_transaction_id) {
                $this->approve($payslip, $userId);
                $payslip->refresh()->load(['payrollPeriod']);
            }

            if ($payslip->payment_gl_transaction_id) {
                $payslip->update([
                    'status' => 'paid',
                    'paid_at' => $payslip->paid_at ?? now(),
                    'payment_method' => $data['payment_method'] ?? $payslip->payment_method ?? 'bank_transfer',
                    'payment_reference' => $data['payment_reference'] ?? $payslip->payment_reference,
                ]);

                return Transaction::where('company_id', $payslip->company_id)
                    ->find($payslip->payment_gl_transaction_id);
            }

            $netPay = round((float) $payslip->net_pay, 2);
            $transaction = null;

            if ($netPay > 0) {
                $accounts = $this->resolveAccounts($payslip, $data['payment_account_id'] ?? null);

                $transaction = $this->postingService->postBalancedTransaction([
                    'company_id' => $payslip->company_id,
                    'transaction_type' => 'payroll_payment',
                    'date' => $payslip->payrollPeriod->payment_date,
                    'currency' => $payslip->currency,
                    'base_currency' => $payslip->base_currency,
                    'exchange_rate' => $payslip->exchange_rate,
                    'description' => 'Payroll payment - '.$payslip->payslip_number,
                    'reference_type' => 'pay.payslips',
                    'reference_id' => $payslip->id,
                    'metadata' => [
                        'employee_id' => $payslip->employee_id,
                        'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                        'payment_reference' => $data['payment_reference'] ?? null,
                    ],
                ], [
                    [
                        'account_id' => $accounts['payroll_payable'],
                        'type' => 'debit',
                        'amount' => $netPay,
                        'description' => 'Clear net payroll payable',
                    ],
                    [
                        'account_id' => $accounts['payment'],
                        'type' => 'credit',
                        'amount' => $netPay,
                        'description' => 'Payroll payment',
                    ],
                ]);
            }

            $payslip->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_gl_transaction_id' => $transaction?->id,
            ]);

            return $transaction;
        });
    }

    public function resolveExchangeRate(string $companyId, string $currency, string $baseCurrency): ?float
    {
        if ($currency === $baseCurrency) {
            return null;
        }

        $rate = CompanyCurrency::query()
            ->where('company_id', $companyId)
            ->where('currency_code', $currency)
            ->value('exchange_rate');

        if (! $rate || (float) $rate <= 0) {
            throw ValidationException::withMessages([
                'currency' => "Enable {$currency} with an exchange rate in Company & Currencies before creating payroll.",
            ]);
        }

        return (float) $rate;
    }

    private function validateCurrencySnapshot(Payslip $payslip): void
    {
        if ($payslip->currency === $payslip->base_currency && $payslip->exchange_rate !== null) {
            throw ValidationException::withMessages(['exchange_rate' => 'Base-currency payslips cannot have an exchange rate.']);
        }
        if ($payslip->currency !== $payslip->base_currency && (float) $payslip->exchange_rate <= 0) {
            throw ValidationException::withMessages(['exchange_rate' => 'A positive exchange rate is required before approving this payslip.']);
        }
    }

    private function ensureSalaryAdvanceDeductionType(string $companyId): DeductionType
    {
        $this->setRlsContext($companyId);

        return DeductionType::updateOrCreate(
            [
                'company_id' => $companyId,
                'code' => 'SALARY_ADVANCE',
            ],
            [
                'name' => 'Salary Advance Recovery',
                'description' => 'Automatically recovers employee salary advances from payslips.',
                'is_pre_tax' => false,
                'is_statutory' => false,
                'is_recurring' => false,
                'is_system' => true,
                'is_active' => true,
            ]
        );
    }

    private function resolveAccounts(Payslip $payslip, ?string $paymentAccountId = null): array
    {
        $companyId = $payslip->company_id;

        return [
            'salary_expense' => $this->findExpenseAccount($companyId, ['Salaries & Wages', 'Salaries', 'Wages', 'Salary Expense'], ['6200', '6150']),
            'employer_expense' => $this->findExpenseAccount($companyId, ['Payroll Taxes', 'Employee Benefits'], ['6210', '6220']),
            'payroll_payable' => $this->findLiabilityAccount($companyId, ['Accrued Salaries & Wages', 'Salary Payable', 'Payroll Payable'], ['2210'], '2211', 'Payroll Salaries Payable'),
            'deduction_payable' => $this->findLiabilityAccount($companyId, ['Payroll Taxes Payable', 'Payroll Deductions Payable'], ['2220'], '2221', 'Payroll Deductions Payable'),
            'employee_advances' => $this->findAssetAccount($companyId, ['Employee Advances'], ['1150'], '1150', 'Employee Advances', 'other_current_asset'),
            'payment' => $this->resolvePaymentAccount($companyId, $paymentAccountId),
        ];
    }

    private function accountSummary(string $accountId): array
    {
        $account = Account::findOrFail($accountId);

        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type,
            'subtype' => $account->subtype,
        ];
    }

    private function groupLinesByAccount(Payslip $payslip, string $lineType, string $fallbackAccountId): array
    {
        $grouped = [];

        foreach ($payslip->lines->where('line_type', $lineType) as $line) {
            $amount = round((float) $line->amount, 2);
            if ($amount <= 0) {
                continue;
            }

            $accountId = $lineType === 'earning'
                ? $this->validAccountId($line->earningType?->gl_account_id, $payslip->company_id)
                : null;

            $accountId ??= $fallbackAccountId;
            $grouped[$accountId] = round(($grouped[$accountId] ?? 0) + $amount, 2);
        }

        return $grouped;
    }

    private function combineEntries(array $entries): array
    {
        $combined = [];

        foreach ($entries as $entry) {
            $key = $entry['account_id'].':'.$entry['type'].':'.$entry['description'];
            if (! isset($combined[$key])) {
                $combined[$key] = $entry;

                continue;
            }

            $combined[$key]['amount'] = round($combined[$key]['amount'] + $entry['amount'], 2);
        }

        return array_values($combined);
    }

    private function validAccountId(?string $accountId, string $companyId): ?string
    {
        if (! $accountId) {
            return null;
        }

        return Account::where('company_id', $companyId)
            ->where('id', $accountId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->value('id');
    }

    private function resolvePaymentAccount(string $companyId, ?string $paymentAccountId): string
    {
        $valid = $this->validAccountId($paymentAccountId, $companyId);
        if ($valid) {
            return $valid;
        }

        $companyDefault = Company::whereKey($companyId)->value('bank_account_id');
        $valid = $this->validAccountId($companyDefault, $companyId);
        if ($valid) {
            return $valid;
        }

        $existing = Account::where('company_id', $companyId)
            ->whereIn('subtype', ['bank', 'cash'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->value('id');

        return $existing ?? $this->ensureAccount($companyId, '1010', 'Payroll Bank Account', 'asset', 'bank', 'debit');
    }

    private function resolveBasePaymentAccount(string $companyId, ?string $paymentAccountId, string $baseCurrency): string
    {
        $query = fn () => Account::where('company_id', $companyId)
            ->whereIn('subtype', ['bank', 'cash'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(fn ($accountQuery) => $accountQuery
                ->whereNull('currency')
                ->orWhere('currency', $baseCurrency));

        $selected = $paymentAccountId
            ? $query()->whereKey($paymentAccountId)->value('id')
            : null;
        if ($selected) {
            return $selected;
        }

        $companyDefault = Company::whereKey($companyId)->value('bank_account_id');
        $default = $companyDefault
            ? $query()->whereKey($companyDefault)->value('id')
            : null;
        if ($default) {
            return $default;
        }

        return $query()->orderBy('code')->value('id')
            ?? $this->ensureAccount($companyId, '1010', 'Payroll Bank Account', 'asset', 'bank', 'debit');
    }

    private function findExpenseAccount(string $companyId, array $names, array $codes): string
    {
        $byName = $this->findByNames($companyId, $names, 'expense');
        if ($byName) {
            return $byName;
        }

        foreach ($codes as $code) {
            $account = Account::where('company_id', $companyId)
                ->where('code', $code)
                ->where('type', 'expense')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->first();

            if ($account && $this->nameMatches($account->name, $names)) {
                return $account->id;
            }
        }

        return $this->ensureAccount($companyId, $codes[0], $names[0], 'expense', 'expense', 'debit');
    }

    private function findLiabilityAccount(string $companyId, array $names, array $codes, string $createCode, string $createName): string
    {
        $byName = $this->findByNames($companyId, $names, 'liability');
        if ($byName) {
            return $byName;
        }

        foreach ($codes as $code) {
            $account = Account::where('company_id', $companyId)
                ->where('code', $code)
                ->where('type', 'liability')
                ->where('name', 'not like', '%Investor%')
                ->where('name', 'not like', '%Commission%')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->first();

            if ($account) {
                return $account->id;
            }
        }

        return $this->ensureAccount($companyId, $createCode, $createName, 'liability', 'other_current_liability', 'credit');
    }

    private function findAssetAccount(string $companyId, array $names, array $codes, string $createCode, string $createName, string $subtype): string
    {
        $byName = $this->findByNames($companyId, $names, 'asset');
        if ($byName) {
            return $byName;
        }

        foreach ($codes as $code) {
            $account = Account::where('company_id', $companyId)
                ->where('code', $code)
                ->where('type', 'asset')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->first();

            if ($account) {
                return $account->id;
            }
        }

        return $this->ensureAccount($companyId, $createCode, $createName, 'asset', $subtype, 'debit');
    }

    private function findByNames(string $companyId, array $names, string $type): ?string
    {
        foreach ($names as $name) {
            $account = Account::where('company_id', $companyId)
                ->where('type', $type)
                ->where('name', 'ilike', '%'.$name.'%')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('code')
                ->first();

            if ($account) {
                return $account->id;
            }
        }

        return null;
    }

    private function ensureAccount(string $companyId, string $code, string $name, string $type, string $subtype, string $normalBalance): string
    {
        $existing = Account::where('company_id', $companyId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->first();

        if (
            $existing
            && $existing->type === $type
            && $existing->subtype === $subtype
            && $this->nameMatches($existing->name, [$name])
        ) {
            if (! $existing->is_active) {
                $existing->update(['is_active' => true]);
            }

            return $existing->id;
        }

        $codeToUse = $existing ? $this->nextAvailableCode($companyId, $code) : $code;

        return Account::create([
            'company_id' => $companyId,
            'code' => $codeToUse,
            'name' => $name,
            'type' => $type,
            'subtype' => $subtype,
            'normal_balance' => $normalBalance,
            'is_active' => true,
            'is_system' => true,
            'description' => 'Auto-created for payroll posting.',
        ])->id;
    }

    private function nameMatches(string $actual, array $expectedNames): bool
    {
        $actual = strtolower($actual);

        foreach ($expectedNames as $expectedName) {
            $expected = strtolower($expectedName);

            if ($actual === $expected || str_contains($actual, $expected) || str_contains($expected, $actual)) {
                return true;
            }
        }

        return false;
    }

    private function nextAvailableCode(string $companyId, string $startCode): string
    {
        $code = (int) $startCode;

        do {
            $candidate = (string) $code++;
            $exists = Account::where('company_id', $companyId)
                ->where('code', $candidate)
                ->whereNull('deleted_at')
                ->exists();
        } while ($exists);

        return $candidate;
    }

    private function setRlsContext(string $companyId): void
    {
        DB::select("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);
    }
}
