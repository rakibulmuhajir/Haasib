<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GetSuggestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'input' => 'required|string|min:2|max:255',
            'context' => 'array',
            'context.page' => 'string|max:100',
            'context.recent_actions' => 'array',
            'context.recent_actions.*' => 'string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'input.required' => 'Input text is required for suggestions',
            'input.string' => 'Input must be a string',
            'input.min' => 'Input must be at least 2 characters long',
            'input.max' => 'Input must not exceed 255 characters',
            'context.array' => 'Context must be an array',
            'context.page.string' => 'Context page must be a string',
            'context.page.max' => 'Context page must not exceed 100 characters',
            'context.recent_actions.array' => 'Recent actions must be an array',
            'context.recent_actions.*.string' => 'Recent action must be a string',
            'context.recent_actions.*.max' => 'Recent action must not exceed 255 characters',
        ];
    }
}
