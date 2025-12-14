<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

class CreateTaxRegistrationRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $isUpdate = (bool) $this->route('id');
        $canCreate = $this->hasCompanyPermission(Permissions::TAX_REGISTRATION_CREATE);
        $canUpdate = $this->hasCompanyPermission(Permissions::TAX_REGISTRATION_UPDATE);

        return ($isUpdate ? $canUpdate : $canCreate) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->get()?->id;
        $registrationId = $this->route('id');

        return [
            'jurisdiction_id' => ['required', 'uuid', 'exists:tax.jurisdictions,id'],
            'registration_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('tax.company_tax_registrations')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($registrationId),
            ],
            'registration_type' => ['required', Rule::in(['vat', 'gst', 'sales_tax', 'withholding', 'other'])],
            'registered_name' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
