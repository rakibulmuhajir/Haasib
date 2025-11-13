<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CompanySwitchRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        // User must be authenticated and have access to the target company
        if (! $this->user()) {
            return false;
        }

        $targetCompanyId = $this->input('company_id');

        // Check if user is assigned to the target company
        return $this->user()->companies()
            ->where('companies.id', $targetCompanyId)
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function rules(): array
    {
        return [
            'company_id' => [
                'required',
                'uuid',
                Rule::exists('companies', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'Company ID is required',
            'company_id.uuid' => 'Invalid company ID format',
            'company_id.exists' => 'Selected company does not exist',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $companyId = $this->input('company_id');

            // Validate that the company is active
            if (! $this->isCompanyActive($companyId)) {
                $validator->errors()->add('company_id',
                    'Cannot switch to an inactive company');
            }

            // Validate user permissions for company switching
            if (! $this->validateUserCompanyAccess($companyId)) {
                $validator->errors()->add('company_id',
                    'You do not have permission to access this company');
            }

            // Log company switch attempt for audit trail
            $this->logCompanySwitchAttempt($companyId);
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_id' => $this->input('company_id') ?: $this->input('company'),
        ]);
    }

    private function isCompanyActive(string $companyId): bool
    {
        return \App\Models\Company::where('id', $companyId)
            ->where('is_active', true)
            ->exists();
    }

    private function validateUserCompanyAccess(string $companyId): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        // Check if user has an active assignment to this company
        return $user->companies()
            ->where('companies.id', $companyId)
            ->wherePivot('is_active', true)
            ->exists();
    }

    private function logCompanySwitchAttempt(string $companyId): void
    {
        $user = $this->user();

        \Log::info('Company switch attempt', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'from_company_id' => $user->currentCompany()?->id,
            'to_company_id' => $companyId,
            'ip_address' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get the target company model
     */
    public function getTargetCompany(): ?\App\Models\Company
    {
        $companyId = $this->input('company_id');

        return \App\Models\Company::find($companyId);
    }

    /**
     * Check if switching to the same company
     */
    public function isSwitchingToSameCompany(): bool
    {
        $currentCompanyId = $this->user()?->currentCompany()?->id;
        $targetCompanyId = $this->input('company_id');

        return $currentCompanyId === $targetCompanyId;
    }
}
