<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Payroll\Models\Payslip;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('payment_account_id') || $validator->errors()->has('payment_account_id')) {
                return;
            }
            $company = app(CurrentCompany::class)->get();
            $payslip = Payslip::query()->where('company_id', $company->id)->find($this->route('payslip'));
            $account = Account::query()->where('company_id', $company->id)->find($this->input('payment_account_id'));
            if ($payslip && $account?->currency && ! in_array($account->currency, [$payslip->currency, $payslip->base_currency], true)) {
                $validator->errors()->add('payment_account_id', 'Use an account in the payslip currency or company base currency.');
            }
        }];
    }
}
