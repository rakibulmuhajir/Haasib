<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'group' => ['sometimes', 'string', 'in:general,currency,notifications,appearance,security'],
            'settings' => ['sometimes', 'array'],
            'settings.*' => ['nullable'],
            'value' => ['sometimes', 'nullable'],
        ];
    }

    /**
     * Get the custom error messages for the request.
     */
    public function messages(): array
    {
        return [
            'group.in' => 'The selected group is invalid.',
            'settings.array' => 'Settings must be an array.',
        ];
    }
}
