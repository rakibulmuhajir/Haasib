<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Invoice;
use Illuminate\Validation\Rule;

class SettleVendorCardRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYMENT_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['uuid', Rule::exists(Invoice::class, 'id')],
            'amount_received' => ['required', 'numeric', 'min:0'],
            'settlement_date' => ['nullable', 'date'],
            'bank_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')],
            'reference' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
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
