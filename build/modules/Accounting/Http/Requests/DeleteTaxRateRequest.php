<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\TaxRate;
use Illuminate\Validation\Rule;

class DeleteTaxRateRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['id' => $this->route('id')]);
    }

    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::TAX_RATE_DELETE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'uuid', Rule::exists(TaxRate::class, 'id')],
        ];
    }
}
