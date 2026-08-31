<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Services\LogoUploadService;
use Illuminate\Foundation\Http\FormRequest;

class StoreLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.LogoUploadService::MAX_KILOBYTES],
            // The logo this one replaces, so the file it points at can go.
            // Only a path this application wrote is ever deleted.
            'replacing' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.max' => 'That image is over '.LogoUploadService::MAX_KILOBYTES.' KB. Choose a smaller one.',
            'logo.mimes' => 'Logos must be a PNG, JPG or WebP.',
        ];
    }
}
