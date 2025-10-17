<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;

class LockReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    public function rules(): array
    {
        return [
            // No additional fields needed for locking
            // All validation is handled by the action class
        ];
    }

    public function messages(): array
    {
        return [
            // Custom messages if needed in the future
        ];
    }
}
