<?php

namespace App\Http\Requests;

use App\Constants\Permissions;
use Illuminate\Validation\Rule;

class UpdatePartnerRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::JOURNAL_CREATE) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'cnic' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'profit_share_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'drawing_limit_period' => ['required', Rule::in(['none', 'monthly', 'yearly'])],
            'drawing_limit_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
