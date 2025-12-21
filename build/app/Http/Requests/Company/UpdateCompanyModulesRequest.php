<?php

namespace App\Http\Requests\Company;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class UpdateCompanyModulesRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::COMPANY_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'inventory' => ['required', 'boolean'],
        ];
    }
}

