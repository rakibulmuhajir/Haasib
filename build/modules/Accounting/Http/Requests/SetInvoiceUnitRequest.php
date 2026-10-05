<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class SetInvoiceUnitRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::INVOICE_UPDATE) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return ['unit_id' => ['nullable', 'uuid']];
    }
}
