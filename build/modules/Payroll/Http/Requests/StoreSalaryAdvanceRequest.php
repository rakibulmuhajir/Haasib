<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Payroll\Models\Employee;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
                Rule::exists(Employee::class, 'id')
                    ->where('company_id', $company->id)
                    ->where('is_active', true)
                    ->where('employment_status', 'active'),
            ],
            'advance_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'bank_transfer', 'cheque'])],
            'bank_account_id' => [
                'nullable',
                'uuid',
                Rule::exists(Account::class, 'id')
                    ->where('company_id', $company->id)
                    ->whereIn('subtype', ['bank', 'cash'])
                    ->where('is_active', true),
            ],
            'reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('bank_account_id') || $validator->errors()->has('bank_account_id')) {
                return;
            }

            $company = app(CurrentCompany::class)->get();
            $currency = Account::where('company_id', $company->id)
                ->whereKey($this->input('bank_account_id'))
                ->value('currency');

            if ($currency && $currency !== $company->base_currency) {
                $validator->errors()->add('bank_account_id', 'Use a cash or bank account in the company base currency.');
            }
        }];
    }
}
