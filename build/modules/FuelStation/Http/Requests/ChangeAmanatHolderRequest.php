<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class ChangeAmanatHolderRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::AMANAT_UPDATE) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return static::ruleSet();
    }

    public static function ruleSet(): array
    {
        return ['customer_id' => ['required', 'uuid']];
    }
}
