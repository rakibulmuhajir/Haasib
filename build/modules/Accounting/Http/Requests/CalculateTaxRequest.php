<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use App\Modules\Accounting\Models\TaxRate;
use App\Modules\Accounting\Models\TaxGroup;
use App\Modules\Accounting\Models\Jurisdiction;

class CalculateTaxRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::TAX_CALCULATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $amountRule = $this->routeIs('tax.api.calculate') ? ['required', 'numeric', 'min:0'] : ['nullable', 'numeric', 'min:0'];

        return [
            'amount' => $amountRule,
            'tax_rate_id' => ['nullable', 'uuid', Rule::exists(TaxRate::class, 'id'), 'required_without:tax_group_id'],
            'tax_group_id' => ['nullable', 'uuid', Rule::exists(TaxGroup::class, 'id'), 'required_without:tax_rate_id'],
            'tax_type' => ['nullable', Rule::in(['sales', 'purchase', 'withholding', 'both'])],
            'jurisdiction_id' => ['nullable', 'uuid', Rule::exists(Jurisdiction::class, 'id')],
        ];
    }
}
