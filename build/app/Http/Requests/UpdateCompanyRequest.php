<?php

namespace App\Http\Requests;

use App\Constants\Permissions;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');
        
        // User must be authenticated and be a member of the company
        if (!$this->user()) {
            return false;
        }
        
        // Check if user is a member of this company
        return $this->user()->companies()->where('companies.id', $company->id)->exists();
    }

    public function rules(): array
    {
        $companyId = $this->route('company')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('auth.companies', 'slug')->ignore($companyId),
            ],
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug must only contain lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'This company slug is already taken.',
        ];
    }
}