<?php

namespace App\Http\Requests\Onboarding;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use Illuminate\Validation\Rule;

class StoreDefaultAccountsRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sales_tax_payable_account_id' => $this->sales_tax_payable_account_id ?: null,
            'purchase_tax_receivable_account_id' => $this->purchase_tax_receivable_account_id ?: null,
            'transit_loss_account_id' => $this->transit_loss_account_id ?: null,
            'transit_gain_account_id' => $this->transit_gain_account_id ?: null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::COMPANY_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'ar_account_id' => ['required', 'uuid', Rule::exists(Account::class, 'id')],
            'ap_account_id' => ['required', 'uuid', Rule::exists(Account::class, 'id')],
            'income_account_id' => ['required', 'uuid', Rule::exists(Account::class, 'id')],
            'expense_account_id' => ['required', 'uuid', Rule::exists(Account::class, 'id')],
            'bank_account_id' => ['required', 'uuid', Rule::exists(Account::class, 'id')],
            'retained_earnings_account_id' => ['required', 'uuid', Rule::exists(Account::class, 'id')],
            'sales_tax_payable_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')],
            'purchase_tax_receivable_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')],
            'transit_loss_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')],
            'transit_gain_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')],
        ];
    }
}
