<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class PostVendorCreditRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::VENDOR_CREDIT_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [];
    }
}
