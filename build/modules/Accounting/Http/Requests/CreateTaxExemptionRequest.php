<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

class CreateTaxExemptionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $isUpdate = (bool) $this->route('id');
        $canCreate = $this->hasCompanyPermission(Permissions::TAX_EXEMPTION_CREATE);
        $canUpdate = $this->hasCompanyPermission(Permissions::TAX_EXEMPTION_UPDATE);

        return ($isUpdate ? $canUpdate : $canCreate) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->get()?->id;
        $exemptionId = $this->route('id');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tax.tax_exemptions')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at'))
                    ->ignore($exemptionId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'exemption_type' => ['required', Rule::in(['full', 'partial', 'rate_override'])],
            'override_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:exemption_type,rate_override'],
            'requires_certificate' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
