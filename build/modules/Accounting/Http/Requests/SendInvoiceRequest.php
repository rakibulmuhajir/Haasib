<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class SendInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::INVOICE_SEND)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'email' => ['nullable', 'boolean'],
            'to' => ['nullable', 'email'],
        ];
    }
}
