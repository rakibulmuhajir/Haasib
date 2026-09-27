<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class SetAmanatBorrowingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::DAILY_CLOSE_CREATE) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return ['allow_amanat_borrowing' => ['required', 'boolean']];
    }
}
