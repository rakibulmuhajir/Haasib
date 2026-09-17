<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StorePostCloseExpenseRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::DAILY_CLOSE_CREATE) && $this->validateRlsContext();
    }
    public function rules(): array
    {
        return ['account_id' => 'required|uuid', 'amount' => 'required|numeric|min:0.01', 'description' => 'required|string|max:255'];
    }
}
