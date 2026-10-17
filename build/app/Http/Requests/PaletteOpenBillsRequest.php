<?php

namespace App\Http\Requests;

class PaletteOpenBillsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->hasActiveCompanyAccess();
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'uuid'],
        ];
    }
}
