<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Payroll\Models\DeductionType;
use App\Modules\Payroll\Models\Payslip;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * An owner's deduction on a draft payslip (leave, absence, damage, a fine). The advance recovery
 * type is never picked by hand: the posting service recomputes it from what is left.
 */
class AddPayslipDeductionRequest extends BaseFormRequest
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
            'deduction_type_id' => [
                'required',
                'uuid',
                Rule::exists(DeductionType::class, 'id')
                    ->where('company_id', $company->id)
                    ->where('is_active', true)
                    ->whereNot('code', 'SALARY_ADVANCE')
                    ->whereNull('deleted_at'),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $company = app(CurrentCompany::class)->get();

            $status = Payslip::query()
                ->where('company_id', $company->id)
                ->whereKey($this->route('payslip'))
                ->value('status');

            if ($status !== 'draft') {
                $validator->errors()->add('payslip', 'Only a draft payslip can take a deduction.');
            }
        });
    }
}
