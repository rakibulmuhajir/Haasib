<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class ViewProductProfitabilityRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::REPORT_VIEW) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'start_date' => ['sometimes', 'nullable', 'string', 'max:40'],
            'end_date' => ['sometimes', 'nullable', 'string', 'max:40'],
            'group_by' => ['sometimes', 'string', 'in:day,week,month'],
            'product' => ['sometimes', 'string', 'max:100'],
            'category_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
