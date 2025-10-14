<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'role' => 'required|in:owner,admin,accountant,viewer',
            'message' => 'nullable|string|max:500',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'The email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'role.required' => 'The role is required.',
            'role.in' => 'The role must be one of: owner, admin, accountant, or viewer.',
            'expires_in_days.min' => 'The expiry period must be at least 1 day.',
            'expires_in_days.max' => 'The expiry period cannot exceed 30 days.',
        ];
    }
}
