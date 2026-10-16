<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;
use App\Modules\Accounting\Models\Jurisdiction;
use App\Modules\Accounting\Models\TaxRate;

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
            // A withholding rate may leave these two out: the jurisdiction falls back to the company's
            // and the code is made from the section (see TaxSettingsController::createTaxRate).
            'jurisdiction_id' => [Rule::requiredIf(fn () => $this->input('tax_type') !== 'withholding'), 'nullable', 'uuid', Rule::exists(Jurisdiction::class, 'id')],
            'code' => [
                Rule::requiredIf(fn () => $this->input('tax_type') !== 'withholding'),
                'nullable',
                'string',
                'max:50',
                Rule::unique(TaxRate::class)
                    ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_type' => ['required', Rule::in(['sales', 'purchase', 'withholding', 'both'])],
            // Withholding only: the section, the non-filer rate and which payments it is for.
            'section' => ['nullable', 'string', 'max:50'],
            'rate_non_filer' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'applies_to' => ['nullable', Rule::in(['payments_made', 'payments_received', 'both'])],
            'is_compound' => ['boolean'],
            'compound_priority' => ['integer', 'min:0'],
            'gl_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')],
            'recoverable_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
