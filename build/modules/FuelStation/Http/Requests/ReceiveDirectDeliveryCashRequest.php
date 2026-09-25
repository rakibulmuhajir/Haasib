<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class ReceiveDirectDeliveryCashRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::DAILY_CLOSE_CREATE)
            && $this->hasCompanyPermission(Permissions::PAYMENT_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return ['date' => 'required|date|before_or_equal:today'];
    }
}
