<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Actions\OpeningBalance\SaveAction;
use App\Modules\Accounting\Extensions\OpeningBalanceSections;
use Illuminate\Support\Arr;

class StoreOpeningBalancesRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::OPENING_BALANCE_MANAGE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $rules = app(SaveAction::class)->rules();

        $fields = [
            'banks.*.account_id',
            'credit_customers.*.customer_id',
            'employees.*.employee_id',
            'salaries_owed.*.employee_id',
            'suppliers.*.vendor_id',
            'partners.*.partner_id',
        ];
        $company = CompanyContext::getCompany();
        foreach ($company ? app(OpeningBalanceSections::class)->availableFor($company) : [] as $key => $section) {
            $fields[] = "{$key}.*.{$section->party()}_id";
        }

        foreach ($fields as $field) {
            $rules[$field] = array_merge(Arr::wrap($rules[$field] ?? []), ['distinct']);
        }

        return $rules;
    }
}
