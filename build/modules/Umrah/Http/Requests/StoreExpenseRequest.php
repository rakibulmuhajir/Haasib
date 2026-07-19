<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Models\CompanyCurrency;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Models\Expense;
use App\Services\CompanyContextService;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExpenseRequest extends UmrahFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper((string) $this->input('currency')),
            'expense_number' => $this->input('expense_number') ?: null,
            'payee' => $this->input('payee') ?: null,
            'reference' => $this->input('reference') ?: null,
        ]);
    }

    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        return DB::table('auth.company_user')
            ->where('company_id', app(CompanyContextService::class)->getCompanyId())
            ->where('user_id', $this->user()?->id)
            ->where('is_active', true)
            ->value('role') !== 'agent';
    }

    protected function permission(): string
    {
        return Permissions::UMRAH_EXPENSE_CREATE;
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $baseCurrency = app(CompanyContextService::class)->getCompany()?->base_currency;

        return [
            'expense_number' => ['nullable', 'string', 'max:50', $this->uniqueForCompany(Expense::class, 'expense_number', 'This expense number is already used.')],
            'expense_date' => ['required', 'date'],
            'expense_account_id' => ['required', 'uuid', $this->accountRule($companyId, ['expense', 'other_expense', 'cogs'], null, 'Selected expense category was not found.')],
            'payment_account_id' => ['required', 'uuid', $this->accountRule($companyId, [], ['bank', 'cash', 'credit_card'], 'Selected payment account was not found.')],
            'payee' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.000001', 'decimal:0,6'],
            'currency' => [
                'required',
                'string',
                'size:3',
                function (string $attribute, mixed $value, Closure $fail) use ($baseCurrency, $companyId): void {
                    if ($value === $baseCurrency) {
                        return;
                    }

                    if (! CompanyCurrency::query()->where('company_id', $companyId)->where('currency_code', $value)->exists()) {
                        $fail('The selected currency is not enabled for this company.');
                    }
                },
            ],
            'exchange_rate' => [
                'nullable',
                'numeric',
                'min:0.00000001',
                'decimal:0,8',
                Rule::requiredIf(fn () => $this->input('currency') && $this->input('currency') !== $baseCurrency),
                Rule::prohibitedIf(fn () => $this->input('currency') === $baseCurrency && $this->filled('exchange_rate')),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['payment_account_id', 'currency'])) {
                return;
            }

            $company = app(CompanyContextService::class)->getCompany();
            $account = Account::query()
                ->where('company_id', $company?->id)
                ->find($this->input('payment_account_id'));

            if ($account?->currency && ! in_array($account->currency, [$this->input('currency'), $company?->base_currency], true)) {
                $validator->errors()->add('payment_account_id', 'Use an account in the expense currency or the company base currency.');
            }
        }];
    }

    private function accountRule(string $companyId, array $types, ?array $subtypes, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($companyId, $types, $subtypes, $message): void {
            $query = Account::query()
                ->where('company_id', $companyId)
                ->whereKey($value)
                ->where('is_active', true)
                ->whereNull('deleted_at');

            if ($types !== []) {
                $query->whereIn('type', $types);
            }
            if ($subtypes !== null) {
                $query->whereIn('subtype', $subtypes);
            }
            if (! $query->exists()) {
                $fail($message);
            }
        };
    }
}
