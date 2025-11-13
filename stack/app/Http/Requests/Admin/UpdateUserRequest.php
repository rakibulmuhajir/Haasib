<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class UpdateUserRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');
        
        // Cannot modify yourself
        if ($user && $user->id === $this->user()->id) {
            return false;
        }

        return $this->hasCompanyPermission('admin.users.update') && 
               $this->canManageUser($user);
    }

    public function rules(): array
    {
        $user = $this->route('user');
        
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[\pL\s\-\'\.]+$/u'
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)->where(function ($query) {
                    return $query->whereNull('deleted_at');
                })
            ],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:255',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('users')->ignore($user->id)->where(function ($query) {
                    return $query->whereNull('deleted_at');
                })
            ],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
            ],
            'system_role' => [
                'required',
                'string',
                Rule::in(['super_admin', 'admin', 'user', 'guest'])
            ],
            'is_active' => 'boolean',
            'companies' => 'array',
            'companies.*.company_id' => [
                'required',
                'uuid',
                'exists:companies,id'
            ],
            'companies.*.role' => [
                'required',
                'string',
                Rule::in(['owner', 'admin', 'member', 'viewer'])
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'email.required' => 'Email address is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email address is already registered',
            'username.required' => 'Username is required',
            'username.unique' => 'This username is already taken',
            'password.min' => 'Password must be at least 8 characters if provided',
            'password.confirmed' => 'Password confirmation does not match',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character',
            'system_role.required' => 'System role is required',
            'system_role.in' => 'Invalid system role selected',
            'companies.*.company_id.required' => 'Company selection is required',
            'companies.*.company_id.exists' => 'Selected company does not exist',
            'companies.*.role.required' => 'Company role is required',
            'companies.*.role.in' => 'Invalid company role selected',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = $this->route('user');
            $currentUser = $this->user();

            // Prevent modifying super_admin role unless current user is also super_admin
            if (!$currentUser->hasRole('super_admin') && 
                $this->input('system_role') === 'super_admin') {
                $validator->errors()->add('system_role', 
                    'Only super administrators can modify super administrator role');
            }

            // Prevent deactivating yourself (extra safety check)
            if ($user && $user->id === $currentUser->id && 
                !$this->boolean('is_active', $user->is_active)) {
                $validator->errors()->add('is_active', 
                    'You cannot deactivate your own account');
            }

            // Validate company assignments
            $companies = $this->input('companies', []);
            if (empty($companies) && !$currentUser->hasRole('super_admin')) {
                $validator->errors()->add('companies', 
                    'Users must be assigned to at least one company');
            }

            // Prevent duplicate company assignments
            $companyIds = collect($companies)->pluck('company_id');
            if ($companyIds->count() !== $companyIds->unique()->count()) {
                $validator->errors()->add('companies', 
                    'Duplicate company assignments are not allowed');
            }

            // Validate that user can assign the requested company roles
            foreach ($companies as $companyData) {
                if ($companyData['role'] === 'owner' && !$currentUser->hasRole('super_admin')) {
                    $validator->errors()->add('company_role', 
                        'Only super administrators can assign owner role');
                }
            }

            // Prevent removing user from all companies if they're not a super_admin
            if (empty($companies) && $user && !$user->hasRole('super_admin')) {
                $validator->errors()->add('companies', 
                    'Non-super administrators must belong to at least one company');
            }
        });
    }

    private function canManageUser($user): bool
    {
        if (!$user) {
            return false;
        }

        $currentUser = $this->user();

        // Super admin can manage anyone
        if ($currentUser->hasRole('super_admin')) {
            return true;
        }

        // Admin can manage users and guests, but not other admins
        if ($currentUser->hasRole('admin')) {
            return in_array($user->system_role, ['user', 'guest']);
        }

        return false;
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        // Log failed validation attempts for security
        Log::warning('User update validation failed', [
            'user_id' => $this->user()->id,
            'target_user_id' => $this->route('user')?->id,
            'ip' => $this->ip(),
            'errors' => $validator->errors()->toArray(),
        ]);

        parent::failedValidation($validator);
    }
}