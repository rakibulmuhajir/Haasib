<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class UpdateBankRuleRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::BANK_RULE_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bank_account_id' => ['nullable', 'uuid', 'exists:acct.company_bank_accounts,id'],
            'priority' => ['required', 'integer', 'min:1'],
            'conditions' => ['required', 'array', 'min:1'],
            'conditions.*.field' => ['required', 'string', 'in:description,payee_name,amount,reference_number,transaction_type'],
            'conditions.*.operator' => ['required', 'string', 'in:contains,equals,starts_with,ends_with,gt,lt,between,regex'],
            'conditions.*.value' => ['required'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.set_category' => ['nullable', 'string', 'max:255'],
            'actions.set_payee' => ['nullable', 'string', 'max:255'],
            'actions.set_gl_account_id' => ['nullable', 'uuid', 'exists:acct.accounts,id'],
            'actions.set_transaction_type' => ['nullable', 'string', 'in:deposit,withdrawal,transfer,fee,interest,adjustment'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'conditions.required' => 'At least one condition is required.',
            'conditions.min' => 'At least one condition is required.',
            'actions.required' => 'At least one action is required.',
            'actions.min' => 'At least one action is required.',
        ];
    }
}
