<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends UmrahFormRequest
{
    public function authorize(): bool
    {
        return parent::authorize()
            && (! ($this->filled('login_username') || $this->filled('password')) || $this->hasCompanyPermission(Permissions::COMPANY_MANAGE_USERS));
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('login_username')) {
            $this->merge(['login_username' => Str::lower((string) $this->input('login_username'))]);
        }
    }

    protected function permission(): string
    {
        return Permissions::UMRAH_AGENT_CREATE;
    }

    public function rules(): array
    {
        return [
            'agent_number' => [
                'nullable',
                'string',
                'max:50',
                $this->uniqueForCompany(Agent::class, 'agent_number', 'This agent number is already used.'),
            ],
            'login_username' => ['nullable', 'required_with:password', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique(User::class, 'username')],
            'password' => ['nullable', 'required_with:login_username', 'string', 'min:8'],
            'can_create_voucher' => ['nullable', 'boolean'],
            'can_approve_voucher' => ['nullable', 'boolean'],
            'can_edit_group' => ['nullable', 'boolean'],
            'can_edit_voucher' => ['nullable', 'boolean'],
            'voucher_cutoff_hours' => ['nullable', Rule::in([2, 6, 12, 18, 24, 48])],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', Rule::in(array_keys(Agent::COUNTRIES))],
            'logo_url' => ['nullable', 'url:http,https', 'max:500'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
