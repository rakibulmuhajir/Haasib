<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateLogin
{
    /**
     * Provide a Fortify-compatible authenticator that supports username or email.
     */
    public function __invoke(Request $request): ?User
    {
        $login = trim((string) $request->input('login', ''));

        if ($login === '') {
            throw ValidationException::withMessages([
                'login' => __('Please enter your email address or username.'),
            ]);
        }

        $user = User::where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            throw ValidationException::withMessages([
                'login' => __('Invalid email/username or password. Please check your credentials and try again.'),
            ]);
        }

        if ($user->is_active === false) {
            throw ValidationException::withMessages([
                'login' => __('Your account is inactive. Please contact support for assistance.'),
            ]);
        }
        return $user;
    }
}
