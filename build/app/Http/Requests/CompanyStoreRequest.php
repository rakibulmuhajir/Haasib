<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CompanyStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isGodMode() ?? false;
    }

    public function rules(): array
    {
        $countryCodes = array_keys(config('countries', []));

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $country = (string) $this->input('country');

                    if ($country !== '' && DB::table('auth.companies')
                        ->where('name', (string) $value)
                        ->where('country', $country)
                        ->exists()) {
                        $fail('A company with this name already exists in the selected country.');
                    }
                },
            ],
            'industry' => ['nullable', 'string', 'max:255'],
            'industry_code' => ['required', 'string', 'exists:acct.industry_coa_packs,code'],
            'country' => ['required', 'string', 'size:2', Rule::in($countryCodes)],
            'country_id' => ['nullable', 'uuid'],
            'base_currency' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! DB::table('public.currencies')
                        ->where('code', strtoupper((string) $value))
                        ->where('is_active', true)
                        ->exists()) {
                        $fail('Please select a supported active currency.');
                    }
                },
            ],
            'timezone' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', 'string', 'max:10'],
            'locale' => ['nullable', 'string', 'max:10'],
            'settings' => ['nullable', 'array'],
            'owner_user_id' => [
                'required',
                'uuid',
                Rule::exists('auth.users', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'country.required' => 'Please select your country.',
            'country.in' => 'Please select a valid country from the list.',
            'industry_code.required' => 'Please select your industry.',
        ];
    }
}
