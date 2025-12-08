<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

class CreateTaxRateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::TAX_RATE_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->get()?->id;

        return [
            'jurisdiction_id' => ['required', 'uuid', 'exists:tax.jurisdictions,id'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tax.tax_rates')
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_type' => ['required', Rule::in(['sales', 'purchase', 'withholding', 'both'])],
            'is_compound' => ['boolean'],
            'compound_priority' => ['integer', 'min:0'],
            'gl_account_id' => ['nullable', 'uuid', 'exists:acct.accounts,id'],
            'recoverable_account_id' => ['nullable', 'uuid', 'exists:acct.accounts,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
