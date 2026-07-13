<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use Illuminate\Validation\Rule;

class UpdateAgentVoucherAccessRequest extends UmrahFormRequest
{
    protected function permission(): string { return Permissions::COMPANY_MANAGE_USERS; }
    public function rules(): array
    {
        return [
            'can_create_voucher' => ['required', 'boolean'],
            'can_approve_voucher' => ['required', 'boolean'],
            'can_edit_voucher' => ['required', 'boolean'],
            'voucher_cutoff_hours' => ['required', Rule::in([2, 6, 12, 18, 24, 48])],
        ];
    }
}
