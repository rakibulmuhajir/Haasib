<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class SetInvoiceDiscountBlockRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::CREDIT_NOTE_CREATE) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'blocked' => ['required', 'boolean'],
            'reason' => ['required_if_accepted:blocked', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['reason.required_if_accepted' => 'Say why this invoice gets no discount.'];
    }
}
