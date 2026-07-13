<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends UmrahFormRequest
{
    public function authorize(): bool
    {
        $changesLogin = $this->filled('login_username') || $this->filled('password');

        return parent::authorize()
            && (! $changesLogin || $this->hasCompanyPermission(Permissions::COMPANY_MANAGE_USERS));
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('login_username')) {
            $this->merge(['login_username' => Str::lower((string) $this->input('login_username'))]);
        }
    }

    protected function permission(): string
    {
        return Permissions::UMRAH_AGENT_UPDATE;
    }

    public function rules(): array
    {
        return [
            'agent_number' => [
                'nullable',
                'string',
                'max:50',
                $this->uniqueForCompany(Agent::class, 'agent_number', 'This agent number is already used.', (string) $this->route('agent')),
            ],
            'login_username' => [
                'nullable',
                'required_with:password',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique(User::class, 'username')->ignore(Agent::withTrashed()->find($this->route('agent'))?->user_id),
            ],
            'password' => ['nullable', 'string', 'min:8'],
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
