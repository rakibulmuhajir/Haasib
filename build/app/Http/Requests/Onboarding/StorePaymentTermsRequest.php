<?php

namespace App\Http\Requests\Onboarding;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StorePaymentTermsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::COMPANY_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'default_customer_payment_terms' => 'required|integer|min:0|max:365',
            'default_vendor_payment_terms' => 'required|integer|min:0|max:365',
        ];
    }
}
