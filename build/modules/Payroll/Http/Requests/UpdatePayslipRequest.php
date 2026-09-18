<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Payroll\Models\DeductionType;
use App\Modules\Payroll\Models\EarningType;
use App\Modules\Payroll\Models\Payslip;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

/**
 * Editing a draft payslip. The employee, the period and the currency are fixed
 * once the payslip has a number -- changing them would be a different payslip,
 * not an edit of this one -- so only the lines, the notes and the conversion
 * rate are accepted here.
 *
 * Salary advance recovery lines are not accepted either: PayrollPostingService
 * owns them, deleting and recomputing them on every save, so a client-sent one
 * would be discarded a moment later.
 */
class UpdatePayslipRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYSLIP_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $company = app(CurrentCompany::class)->get();

        $payslipCurrency = Payslip::query()
            ->where('company_id', $company->id)
            ->whereKey($this->route('payslip'))
            ->value('currency');

        $isForeign = $payslipCurrency !== null && $payslipCurrency !== $company->base_currency;

        return [
            'exchange_rate' => [
                $isForeign ? 'required' : 'nullable',
                'numeric',
                'min:0.00000001',
                'decimal:0,8',
                Rule::prohibitedIf(! $isForeign),
            ],
            'notes' => 'nullable|string',
            'lines' => 'array',
            'lines.*.line_type' => 'required|in:earning,deduction,employer',
            'lines.*.earning_type_id' => ['nullable', 'uuid', Rule::exists(EarningType::class, 'id')->where('company_id', $company->id)],
            'lines.*.deduction_type_id' => ['nullable', 'uuid', Rule::exists(DeductionType::class, 'id')->where('company_id', $company->id)],
            'lines.*.description' => 'nullable|string|max:255',
            'lines.*.quantity' => 'required|numeric|min:0',
            'lines.*.rate' => 'required|numeric|min:0',
            'lines.*.amount' => 'required|numeric|min:0',
            'lines.*.sort_order' => 'integer|min:0',
        ];
    }
}
