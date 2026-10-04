<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\FuelStation\Http\Requests\Rules\CalculatorFormulaRule;

class EvaluateCalculatorRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::REPORT_VIEW) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'formula' => ['required', 'array', new CalculatorFormulaRule],
        ];
    }
}
