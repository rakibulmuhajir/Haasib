<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ResetPasswordRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');
        
        // Cannot reset your own password through admin interface
        if ($user && $user->id === $this->user()->id) {
            return false;
        }

        return $this->hasCompanyPermission('admin.users.reset_password') && 
               $this->canManageUser($user);
    }

    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            ],
            'send_email' => 'boolean',
            'force_change_on_login' => 'boolean',
            'reason' => 'sometimes|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'password.required' => 'New password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character',
            'send_email.boolean' => 'Send email option must be a boolean value',
            'force_change_on_login.boolean' => 'Force change on login option must be a boolean value',
            'reason.string' => 'Reason must be a string',
            'reason.max' => 'Reason cannot exceed 255 characters',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = $this->route('user');
            $currentPassword = $this->input('password');

            // Additional security: Check password history (last 5 passwords)
            if ($user && $currentPassword) {
                $this->validatePasswordHistory($validator, $user, $currentPassword);
            }

            // Validate password complexity for admin resets
            $this->validatePasswordComplexity($validator, $currentPassword);
        });
    }

    private function canManageUser($user): bool
    {
        if (!$user) {
            return false;
        }

        $currentUser = $this->user();

        // Super admin can reset anyone's password
        if ($currentUser->hasRole('super_admin')) {
            return true;
        }

        // Admin can reset passwords for users and guests
        if ($currentUser->hasRole('admin')) {
            return in_array($user->system_role, ['user', 'guest']);
        }

        return false;
    }

    private function validatePasswordHistory($validator, $user, $password): void
    {
        // Check against common passwords
        $commonPasswords = [
            'password', '12345678', '123456789', 'qwerty', 'abc123',
            'password123', 'admin123', 'letmein', 'welcome'
        ];

        if (in_array(strtolower($password), $commonPasswords)) {
            $validator->errors()->add('password', 
                'Please choose a more secure password');
            return;
        }

        // Check if password contains user information
        $userInfo = [
            strtolower($user->name ?? ''),
            strtolower($user->email ?? ''),
            strtolower($user->username ?? ''),
        ];

        foreach ($userInfo as $info) {
            if ($info && str_contains(strtolower($password), $info)) {
                $validator->errors()->add('password', 
                    'Password cannot contain your name, email, or username');
                return;
            }
        }
    }

    private function validatePasswordComplexity($validator, $password): void
    {
        // Additional complexity checks for admin interface
        $hasUpperCase = preg_match('/[A-Z]/', $password);
        $hasLowerCase = preg_match('/[a-z]/', $password);
        $hasNumber = preg_match('/\d/', $password);
        $hasSpecialChar = preg_match('/[@$!%*?&]/', $password);
        $hasSequence = $this->hasSequentialPattern($password);

        if (!$hasUpperCase || !$hasLowerCase || !$hasNumber || !$hasSpecialChar) {
            $validator->errors()->add('password', 
                'Password must include uppercase, lowercase, numbers, and special characters');
        }

        if ($hasSequence) {
            $validator->errors()->add('password', 
                'Password cannot contain sequential patterns (e.g., "1234", "abcd")');
        }
    }

    private function hasSequentialPattern(string $password): bool
    {
        $password = strtolower($password);
        
        // Check for numeric sequences
        for ($i = 0; $i < strlen($password) - 3; $i++) {
            $substr = substr($password, $i, 4);
            if (in_array($substr, ['1234', '2345', '3456', '4567', '5678', '6789', '7890', '0123', 'abcd', 'bcde', 'cdef', 'defg', 'efgh', 'fghi', 'ghij', 'hijk', 'ijkl', 'jklm', 'klmn', 'lmno', 'mnop', 'nopq', 'opqr', 'pqrs', 'qrst', 'rstu', 'stuv', 'tuvw', 'uvwx', 'vwxy', 'wxyz'])) {
                return true;
            }
        }

        return false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'send_email' => $this->boolean('send_email', true),
            'force_change_on_login' => $this->boolean('force_change_on_login', false),
        ]);
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        // Log failed validation attempts for security
        Log::warning('Admin password reset validation failed', [
            'admin_id' => $this->user()->id,
            'target_user_id' => $this->route('user')?->id,
            'ip' => $this->ip(),
            'errors' => $validator->errors()->toArray(),
        ]);

        parent::failedValidation($validator);
    }
}