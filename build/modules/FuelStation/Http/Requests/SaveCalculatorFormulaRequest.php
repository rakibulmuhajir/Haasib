<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\FuelStation\Http\Requests\Rules\CalculatorFormulaRule;

/** Save a new formula, or (PUT) replace one of your own. */
class SaveCalculatorFormulaRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::REPORT_VIEW) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'formula' => ['required', 'array', new CalculatorFormulaRule],
            'is_shared' => ['sometimes', 'boolean'],
        ];
    }
}
