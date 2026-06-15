<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

class StoreSalaryAdvanceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYSLIP_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $company = app(CurrentCompany::class)->get();

        return [
            'employee_id' => [
                'required',
                'uuid',
                Rule::exists('pay.employees', 'id')
                    ->where('company_id', $company->id)
                    ->where('is_active', true)
                    ->where('employment_status', 'active'),
            ],
            'advance_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'bank_transfer', 'cheque'])],
            'bank_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('acct.accounts', 'id')
                    ->where('company_id', $company->id)
                    ->whereIn('subtype', ['bank', 'cash'])
                    ->where('is_active', true),
            ],
            'reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
