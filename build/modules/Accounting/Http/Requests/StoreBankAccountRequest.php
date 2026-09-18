<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bank;
use App\Modules\Accounting\Models\BankAccount;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class StoreBankAccountRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::BANK_ACCOUNT_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return [
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique(BankAccount::class, 'account_number')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at'),
            ],
            'account_type' => ['required', 'in:checking,savings,credit_card,cash,other'],
            'currency' => ['required', 'string', 'size:3', 'uppercase'],
            'bank_id' => ['nullable', 'uuid', Rule::exists(Bank::class, 'id')],
            // A bank record owns exactly one GL account: postings hit the GL account, so two
            // records sharing one make their balances indistinguishable (a transfer between
            // them would debit and credit the same account). Scope to this company, refuse an
            // account another record already owns, and refuse one whose subtype disagrees with
            // the account_type. Left null, the controller creates the right account.
            'gl_account_id' => [
                'nullable',
                'uuid',
                Rule::exists(Account::class, 'id')->where('company_id', $companyId),
                Rule::unique(BankAccount::class, 'gl_account_id')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $want = match ($this->input('account_type')) {
                        'cash' => 'cash',
                        'credit_card' => 'credit_card',
                        default => 'bank',
                    };
                    $subtype = Account::whereKey($value)->value('subtype');
                    if ($subtype !== null && $subtype !== $want) {
                        $fail("That ledger account is a {$subtype} account; a {$this->input('account_type')} account needs a {$want} one.");
                    }
                },
            ],
            'iban' => ['nullable', 'string', 'max:34', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/'],
            'swift_code' => ['nullable', 'string', 'max:11'],
            'routing_number' => ['nullable', 'string', 'max:50'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'branch_address' => ['nullable', 'string'],
            'opening_balance' => ['nullable', 'numeric'],
            'opening_balance_date' => ['nullable', 'date'],
            'is_primary' => ['boolean'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
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
