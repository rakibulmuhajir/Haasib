<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;

class ReopenReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please provide a reason for reopening this reconciliation.',
            'reason.min' => 'The reason must be at least 5 characters long.',
            'reason.max' => 'The reason must not exceed 1000 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'reason' => 'reopening reason',
        ];
    }
}
