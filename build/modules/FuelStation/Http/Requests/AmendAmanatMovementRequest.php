<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class AmendAmanatMovementRequest extends BaseFormRequest
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
        return [
            'business_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_account_id' => ['nullable', 'uuid'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
