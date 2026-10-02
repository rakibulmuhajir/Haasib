<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

class SavePayrollSettingsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYROLL_SETTINGS_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $company = app(CurrentCompany::class)->get();

        return [
            'payment_recording' => ['required', 'string', Rule::in(['on_entry', 'on_approval'])],
            'payment_account_id' => [
                'nullable',
                'uuid',
                Rule::exists(Account::class, 'id')
                    ->where('company_id', $company->id)
                    ->whereIn('subtype', ['bank', 'cash'])
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
