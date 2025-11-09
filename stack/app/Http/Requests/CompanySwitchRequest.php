<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanySwitchRequest extends FormRequest
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
                'exists:pgsql.auth.companies,id',
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_id' => $this->input('company_id') ?: $this->input('company'),
        ]);
    }
}
