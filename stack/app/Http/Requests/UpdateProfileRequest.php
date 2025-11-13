<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Users can always update their own profile
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'username' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'timezone' => 'nullable|string|max:50',
            'language' => 'nullable|string|max:10',
            'date_format' => 'nullable|string|max:20',
            'currency' => 'nullable|string|max:3',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'name.max' => 'Name cannot exceed 255 characters',
            'email.required' => 'Email is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email is already taken',
            'username.required' => 'Username is required',
            'username.alpha_dash' => 'Username may only contain letters, numbers, dashes and underscores',
            'username.unique' => 'This username is already taken',
            'phone.max' => 'Phone number cannot exceed 20 characters',
            'address.max' => 'Address cannot exceed 500 characters',
            'city.max' => 'City cannot exceed 100 characters',
            'state.max' => 'State cannot exceed 100 characters',
            'country.max' => 'Country cannot exceed 100 characters',
            'postal_code.max' => 'Postal code cannot exceed 20 characters',
            'timezone.max' => 'Timezone cannot exceed 50 characters',
            'language.max' => 'Language cannot exceed 10 characters',
            'date_format.max' => 'Date format cannot exceed 20 characters',
            'currency.max' => 'Currency cannot exceed 3 characters',
        ];
    }
}