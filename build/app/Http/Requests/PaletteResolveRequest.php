<?php

namespace App\Http\Requests;

class PaletteResolveRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->hasActiveCompanyAccess();
    }

    public function rules(): array
    {
        return [
            'command' => ['required', 'string', 'max:100'],
            'slots' => ['array'],
            'slots.*.text' => ['nullable', 'string', 'max:500'],
            'slots.*.value' => ['nullable', 'string', 'max:500'],
        ];
    }
}
