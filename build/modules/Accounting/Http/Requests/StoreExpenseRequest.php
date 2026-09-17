<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::EXPENSE_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(\App\Services\CurrentCompany::class)->get()?->id;

        return [
            'date' => ['required', 'date'],
            'account_id' => ['required', 'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('type', 'expense')->where('is_active', true))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_from_account_id' => ['required', 'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereIn('subtype', ['cash', 'bank'])->where('is_active', true))],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
