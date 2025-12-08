<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

class CreateTaxGroupRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $isUpdate = (bool) $this->route('id');
        $canCreate = $this->hasCompanyPermission(Permissions::TAX_GROUP_CREATE);
        $canUpdate = $this->hasCompanyPermission(Permissions::TAX_GROUP_UPDATE);

        return ($isUpdate ? $canUpdate : $canCreate) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->get()?->id;
        $taxGroupId = $this->route('id');

        return [
            'jurisdiction_id' => ['required', 'uuid', 'exists:tax.jurisdictions,id'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tax.tax_groups')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at'))
                    ->ignore($taxGroupId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
            'components' => ['nullable', 'array'],
            'components.*.tax_rate_id' => ['required_with:components', 'uuid', 'exists:tax.tax_rates,id'],
            'components.*.priority' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
