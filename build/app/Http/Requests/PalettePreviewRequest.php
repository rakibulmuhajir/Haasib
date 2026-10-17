<?php

namespace App\Http\Requests;

class PalettePreviewRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        // Permissions proper are the command's own, checked when the preview runs it.
        return $this->user() !== null && $this->hasActiveCompanyAccess();
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_-]*(\.[a-z][a-z0-9_-]*)+$/'],
            'params' => ['nullable', 'array'],
        ];
    }
}
