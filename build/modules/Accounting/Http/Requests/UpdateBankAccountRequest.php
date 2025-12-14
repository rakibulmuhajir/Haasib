<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\BankAccount;
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
        $company = $this->getCompany();
        $bankAccountId = $this->route('bankAccount');

        // Check if account has transactions (currency immutability)
        $bankAccount = BankAccount::find($bankAccountId);
        $hasTransactions = $bankAccount ? $bankAccount->hasTransactions() : false;

        $rules = [
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('acct.company_bank_accounts', 'account_number')
                    ->where('company_id', $company->id)
                    ->whereNull('deleted_at')
                    ->ignore($bankAccountId),
            ],
            'account_type' => ['required', 'in:checking,savings,credit_card,cash,other'],
            'bank_id' => ['nullable', 'uuid', 'exists:acct.banks,id'],
            'gl_account_id' => ['nullable', 'uuid', 'exists:acct.accounts,id'],
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
