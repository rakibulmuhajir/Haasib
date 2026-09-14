<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class DestroyPumpRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PUMP_DELETE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [];
    }
}
