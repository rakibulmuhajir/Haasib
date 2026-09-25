<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Actions\OpeningBalance\SaveAction;
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

        foreach ([
            'banks.*.account_id',
            'credit_customers.*.customer_id',
            'employees.*.employee_id',
            'salaries_owed.*.employee_id',
            'amanat.*.customer_id',
            'suppliers.*.vendor_id',
            'partners.*.partner_id',
        ] as $field) {
            $rules[$field] = array_merge(Arr::wrap($rules[$field] ?? []), ['distinct']);
        }

        return $rules;
    }
}
