<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class SettleVendorCardRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYMENTS_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['uuid', 'exists:acct.invoices,id'],
            'amount_received' => ['required', 'numeric', 'min:0'],
            'settlement_date' => ['nullable', 'date'],
            'bank_account_id' => ['nullable', 'uuid', 'exists:acct.accounts,id'],
            'reference' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_ids.required' => 'Please select at least one invoice to settle.',
            'invoice_ids.min' => 'Please select at least one invoice to settle.',
        ];
    }
}
