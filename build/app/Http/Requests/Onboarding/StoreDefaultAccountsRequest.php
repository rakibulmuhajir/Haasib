<?php

namespace App\Http\Requests\Onboarding;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

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
            'ar_account_id' => 'required|uuid|exists:acct.accounts,id',
            'ap_account_id' => 'required|uuid|exists:acct.accounts,id',
            'income_account_id' => 'required|uuid|exists:acct.accounts,id',
            'expense_account_id' => 'required|uuid|exists:acct.accounts,id',
            'bank_account_id' => 'required|uuid|exists:acct.accounts,id',
            'retained_earnings_account_id' => 'required|uuid|exists:acct.accounts,id',
            'sales_tax_payable_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'purchase_tax_receivable_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'transit_loss_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'transit_gain_account_id' => 'nullable|uuid|exists:acct.accounts,id',
        ];
    }
}
