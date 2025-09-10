<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', 'in:owner,admin,accountant,viewer,member'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ];
    }
}
