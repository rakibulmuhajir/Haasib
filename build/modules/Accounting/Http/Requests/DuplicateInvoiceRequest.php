<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class DuplicateInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::INVOICE_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'customer' => ['nullable', 'string', 'max:255'],
            'draft' => ['nullable', 'boolean'],
        ];
    }
}
