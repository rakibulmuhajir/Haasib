<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class UpdateItemStatusRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::ITEM_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'is_active' => 'required|boolean',
        ];
    }
}
