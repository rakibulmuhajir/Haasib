<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

class UpdateTaxRateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::TAX_RATE_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->get()?->id;
        $taxRateId = $this->route('id');

        return [
            'jurisdiction_id' => ['sometimes', 'uuid', 'exists:tax.jurisdictions,id'],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('tax.tax_rates')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at'))
                    ->ignore($taxRateId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'tax_type' => ['sometimes', Rule::in(['sales', 'purchase', 'withholding', 'both'])],
            'is_compound' => ['sometimes', 'boolean'],
            'compound_priority' => ['sometimes', 'integer', 'min:0'],
            'gl_account_id' => ['nullable', 'uuid', 'exists:acct.accounts,id'],
            'recoverable_account_id' => ['nullable', 'uuid', 'exists:acct.accounts,id'],
            'effective_from' => ['sometimes', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
