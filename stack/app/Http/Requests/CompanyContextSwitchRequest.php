<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyContextSwitchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => [
                'required',
                'uuid',
                'exists:auth.companies,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'Company ID is required.',
            'company_id.uuid' => 'Invalid company ID format.',
            'company_id.exists' => 'Selected company does not exist.',
        ];
    }

    public function validateCompanyAccess(): void
    {
        $user = $this->user();
        $companyId = $this->input('company_id');

        if (! $user) {
            abort(403, 'Authentication required');
        }

        // Check if user has access to this company
        $hasAccess = $user->companies()
            ->where('company_user.company_id', $companyId)
            ->where('company_user.is_active', true)
            ->exists();

        if (! $hasAccess) {
            abort(403, 'You do not have access to this company');
        }
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_id' => $this->input('company_id') ?: $this->input('company'),
        ]);
    }
}