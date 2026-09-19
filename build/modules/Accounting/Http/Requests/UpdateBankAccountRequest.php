<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bank;
use App\Modules\Accounting\Models\BankAccount;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class UpdateBankAccountRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::BANK_ACCOUNT_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $bankAccountId = $this->route('bankAccount');

        // Check if account has transactions (currency immutability)
        $bankAccount = BankAccount::where('company_id', $companyId)->find($bankAccountId);
        $hasTransactions = $bankAccount ? $bankAccount->hasTransactions() : false;

        $rules = [
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique(BankAccount::class, 'account_number')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->ignore($bankAccountId),
            ],
            'account_type' => ['required', 'in:checking,savings,credit_card,cash,other', ...($hasTransactions ? [Rule::in([$bankAccount->account_type])] : [])],
            'bank_id' => ['nullable', 'uuid', Rule::exists(Bank::class, 'id')],
            'gl_account_id' => [
                $hasTransactions ? 'required' : 'nullable',
                'uuid',
                Rule::exists(Account::class, 'id')->where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at'),
                ...($hasTransactions ? [] : [Rule::unique(BankAccount::class, 'gl_account_id')->where('company_id', $companyId)->whereNull('deleted_at')->ignore($bankAccountId)]),
                ...($hasTransactions ? [Rule::in([$bankAccount->gl_account_id])] : []),
                function (string $attribute, mixed $value, \Closure $fail) use ($companyId, $hasTransactions, $bankAccount) {
                    // Preserve a locked legacy link when changing unrelated details
                    // (including deactivating the misconfigured record).
                    if ($hasTransactions && $value === $bankAccount->gl_account_id) {
                        return;
                    }
                    $want = match ($this->input('account_type')) {
                        'cash' => 'cash',
                        'credit_card' => 'credit_card',
                        default => 'bank',
                    };
                    $account = Account::where('company_id', $companyId)->find($value);
                    if ($account && $account->subtype !== $want) {
                        $fail('Choose a matching ledger account, or select Create automatically to give this account its own ledger.');
                    }
                },
            ],
            'iban' => ['nullable', 'string', 'max:34', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/'],
            'swift_code' => ['nullable', 'string', 'max:11'],
            'routing_number' => ['nullable', 'string', 'max:50'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'branch_address' => ['nullable', 'string'],
            'is_primary' => ['boolean'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];

        // Currency can only be updated if no transactions exist
        if (! $hasTransactions) {
            $rules['currency'] = ['required', 'string', 'size:3', 'uppercase'];
            $rules['opening_balance'] = ['nullable', 'numeric'];
            $rules['opening_balance_date'] = ['nullable', 'date'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'account_number.unique' => 'This account number is already in use.',
            'iban.regex' => 'IBAN format is invalid.',
            'currency.size' => 'Currency must be a 3-letter code.',
        ];
    }
}
