<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

class MarkPayslipPaidRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYSLIP_PAY)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $company = app(CurrentCompany::class)->get();

        return [
            'payment_method' => ['nullable', 'string', Rule::in(['bank_transfer', 'check', 'cheque', 'cash'])],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('acct.accounts', 'id')
                    ->where('company_id', $company->id)
                    ->whereIn('subtype', ['bank', 'cash'])
                    ->where('is_active', true),
            ],
        ];
    }
}
