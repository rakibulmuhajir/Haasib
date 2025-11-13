<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CreateUserRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('admin.users.create') && 
               $this->user()->hasRole('super_admin');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[\pL\s\-\'\.]+$/u' // Allow letters, spaces, hyphens, apostrophes, and dots
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->where(function ($query) {
                    return $query->whereNull('deleted_at');
                })
            ],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:255',
                'regex:/^[a-zA-Z0-9_]+$/', // Alphanumeric and underscores only
                Rule::unique('users')->where(function ($query) {
                    return $query->whereNull('deleted_at');
                })
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/' // Strong password
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
            'name.min' => 'Name must be at least 2 characters',
            'name.max' => 'Name cannot exceed 255 characters',
            'name.regex' => 'Name can only contain letters, spaces, hyphens, and apostrophes',
            'email.required' => 'Email address is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email address is already registered',
            'username.required' => 'Username is required',
            'username.min' => 'Username must be at least 3 characters',
            'username.max' => 'Username cannot exceed 255 characters',
            'username.regex' => 'Username can only contain letters, numbers, and underscores',
            'username.unique' => 'This username is already taken',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character',
            'system_role.required' => 'System role is required',
            'system_role.in' => 'Invalid system role selected',
            'companies.required' => 'At least one company assignment is required',
            'companies.*.company_id.required' => 'Company selection is required',
            'companies.*.company_id.exists' => 'Selected company does not exist',
            'companies.*.role.required' => 'Company role is required',
            'companies.*.role.in' => 'Invalid company role selected',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Prevent creating another super_admin unless current user is also super_admin
            if (!$this->user()->hasRole('super_admin') && 
                $this->input('system_role') === 'super_admin') {
                $validator->errors()->add('system_role', 
                    'Only super administrators can create other super administrators');
            }

            // Validate company assignments
            $companies = $this->input('companies', []);
            if (empty($companies) && !$this->user()->hasRole('super_admin')) {
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
                if ($companyData['role'] === 'owner' && !$this->user()->hasRole('super_admin')) {
                    $validator->errors()->add('company_role', 
                        'Only super administrators can assign owner role');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        // Log failed validation attempts for security
        Log::warning('User creation validation failed', [
            'user_id' => $this->user()->id,
            'ip' => $this->ip(),
            'errors' => $validator->errors()->toArray(),
        ]);

        parent::failedValidation($validator);
    }
}