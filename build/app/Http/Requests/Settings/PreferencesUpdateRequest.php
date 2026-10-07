<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class PreferencesUpdateRequest extends FormRequest
{
    // Personal preferences require authentication, not membership of a company.
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['show_value_trails' => ['required', 'boolean']];
    }
}
