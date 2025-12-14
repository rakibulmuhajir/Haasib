<?php

namespace App\Http\Requests\Onboarding;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreNumberingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::COMPANY_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'invoice_prefix' => 'required|string|max:20',
            'invoice_start_number' => 'required|integer|min:1',
            'bill_prefix' => 'required|string|max:20',
            'bill_start_number' => 'required|integer|min:1',
        ];
    }
}
