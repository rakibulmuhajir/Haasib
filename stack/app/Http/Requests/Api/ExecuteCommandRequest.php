<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'command_name' => 'required|string|max:255',
            'parameters' => 'array',
            'parameters.*' => 'string|int|bool|array',
        ];
    }

    public function messages(): array
    {
        return [
            'command_name.required' => 'Command name is required',
            'command_name.string' => 'Command name must be a string',
            'command_name.max' => 'Command name must not exceed 255 characters',
            'parameters.array' => 'Parameters must be an array',
            'parameters.*.*' => 'Parameter values must be string, integer, boolean, or array',
        ];
    }
}
