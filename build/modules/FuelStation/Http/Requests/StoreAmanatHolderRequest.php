<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreAmanatHolderRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::AMANAT_DEPOSIT)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'cnic' => ['nullable', 'string', 'max:50'],
            'relationship' => ['nullable', 'in:owner,employee,external'],
        ];
    }
}
