<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');
        $user = $this->user();

        // User must have permission to update the company
        if (! $user->hasPermissionTo('companies.update')) {
            return false;
        }

        // User must belong to the company or be super admin
        if ($user->system_role === 'superadmin') {
            return true;
        }

        return $company->users()->where('user_id', $user->id)->exists();
    }

    public function rules(): array
    {
        $company = $this->route('company');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'min:2',
                Rule::unique('auth.companies', 'name')->ignore($company->id),
                // No HTML tags allowed
                'regex:/^[^<>&]*$/',
            ],
            'industry' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::in(['hospitality', 'retail', 'professional_services', 'technology', 'healthcare', 'education', 'manufacturing', 'other']),
            ],
            'country' => [
                'sometimes',
                'required',
                'string',
                'size:2',
                'exists:countries,code',
            ],
            'base_currency' => [
                'sometimes',
                'required',
                'string',
                'size:3',
                'exists:currencies,code',
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
                'exists:currencies,code',
            ],
            'timezone' => [
                'nullable',
                'string',
                'max:50',
                'timezone:all',
            ],
            'language' => [
                'nullable',
                'string',
                'max:10',
                'exists:languages,code',
            ],
            'locale' => [
                'nullable',
                'string',
                'max:10',
                'regex:/^[a-z]{2}_[A-Z]{2}$/',
            ],
            'settings' => [
                'nullable',
                'array',
            ],
            'settings.*' => [
                'string',
            ],
            'is_active' => [
                'sometimes',
                'required',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Company name is required.',
            'name.min' => 'Company name must be at least 2 characters long.',
            'name.unique' => 'A company with this name already exists.',
            'name.regex' => 'Company name contains invalid characters.',
            'industry.required' => 'Industry is required.',
            'industry.in' => 'Invalid industry selected.',
            'country.required' => 'Country is required.',
            'country.exists' => 'Invalid country selected.',
            'base_currency.required' => 'Base currency is required.',
            'base_currency.exists' => 'Invalid currency selected.',
            'timezone.timezone' => 'Invalid timezone selected.',
            'language.exists' => 'Invalid language selected.',
            'locale.regex' => 'Locale must be in format: en_US',
            'is_active.required' => 'Status is required.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name') && ! $this->has('slug')) {
            $this->merge([
                'slug' => $this->generateSlug($this->input('name')),
            ]);
        }

        if ($this->has('settings')) {
            $this->merge([
                'settings' => $this->input('settings', []),
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->validateBusinessLogic($validator);
        });
    }

    /**
     * Validate business logic constraints.
     */
    protected function validateBusinessLogic($validator)
    {
        $company = $this->route('company');
        $user = $this->user();

        // Edge Case 1: Prevent deactivating company with active users
        if ($this->has('is_active') && ! $this->boolean('is_active')) {
            $activeUsersCount = $company->users()->where('is_active', true)->count();
            if ($activeUsersCount > 1) { // More than just the current user
                $validator->errors()->add('is_active',
                    'Cannot deactivate company with active users. Please deactivate users first or transfer ownership.'
                );
            }
        }

        // Edge Case 2: Prevent currency change if company has transactions
        if ($this->has('base_currency') && $this->base_currency !== $company->base_currency) {
            // Check if company has any financial transactions
            $hasTransactions = $company->invoices()->exists() || $company->payments()->exists();
            if ($hasTransactions) {
                $validator->errors()->add('base_currency',
                    'Cannot change currency after financial transactions have been created.'
                );
            }
        }

        // Edge Case 3: Validate timezone changes don't affect fiscal periods
        if ($this->has('timezone') && $this->timezone !== $company->timezone) {
            // Check if company has fiscal years
            if ($company->fiscalYears()->exists()) {
                $validator->errors()->add('timezone',
                    'Cannot change timezone after fiscal years have been created.'
                );
            }
        }

        // Edge Case 4: Prevent settings that would exceed system limits
        if ($this->has('settings.limits.max_users')) {
            $maxUsers = $this->input('settings.limits.max_users');
            $currentUsers = $company->users()->count();
            if ($maxUsers < $currentUsers) {
                $validator->errors()->add('settings.limits.max_users',
                    "Maximum users limit ({$maxUsers}) cannot be less than current users ({$currentUsers})."
                );
            }
        }

        // Edge Case 5: Validate storage format
        if ($this->has('settings.limits.max_storage')) {
            $storage = $this->input('settings.limits.max_storage');
            if (! preg_match('/^\d+[KMGT]?B$/', $storage)) {
                $validator->errors()->add('settings.limits.max_storage',
                    'Storage limit must be in format like 1GB, 500MB, 2TB, etc.'
                );
            }
        }

        // Edge Case 6: Cannot remove creator from company ownership
        if ($this->has('settings') && isset($this->settings['ownership_transfer'])) {
            if ($company->created_by_user_id === $user->id) {
                // User is trying to transfer ownership without providing new owner
                if (! isset($this->settings['new_owner_id']) || ! $this->settings['new_owner_id']) {
                    $validator->errors()->add('settings.ownership_transfer',
                        'New owner ID must be provided when transferring ownership.'
                    );
                }
            }
        }
    }

    /**
     * Get the validated data with defaults applied.
     */
    public function validated(): array
    {
        $data = parent::validated();

        // Apply default values for optional fields
        if (isset($data['settings'])) {
            $data['settings'] = array_merge([
                'features' => [],
                'preferences' => [],
                'limits' => [],
            ], $data['settings']);
        }

        return $data;
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }
}
