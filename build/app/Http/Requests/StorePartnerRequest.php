<?php

namespace App\Http\Requests;

use App\Constants\Permissions;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

class StorePartnerRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::JOURNAL_CREATE) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CurrentCompany::class)->get()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'cnic' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'profit_share_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'drawing_limit_period' => ['required', Rule::in(['none', 'monthly', 'yearly'])],
            'drawing_limit_amount' => ['nullable', 'numeric', 'min:0'],
            'initial_investment' => ['nullable', 'numeric', 'min:0'],
            // Where the first investment was paid into; needed only when there is one.
            'account_id' => [
                'nullable', 'uuid',
                Rule::exists('acct.accounts', 'id')->where('company_id', $companyId)->whereIn('subtype', ['cash', 'bank'])->whereNull('deleted_at'),
            ],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->sometimes('account_id', 'required', fn ($input) => (float) ($input->initial_investment ?? 0) > 0);
    }
}
