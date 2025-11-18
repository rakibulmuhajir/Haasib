<?php

namespace App\Http\Requests\Invoices;

use App\Http\Requests\BaseFormRequest;

class ViewInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('invoices.view');
    }

    public function rules(): array
    {
        return [];
    }
}
