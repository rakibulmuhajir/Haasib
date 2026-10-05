<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class SetInvoiceReferenceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::INVOICE_UPDATE) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'slip_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
