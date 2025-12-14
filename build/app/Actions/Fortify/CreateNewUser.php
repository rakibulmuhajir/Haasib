<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $baseUsername = Str::slug($input['name']) ?: Str::slug(strtok($input['email'], '@'));
        $username = $this->uniqueUsername($baseUsername ?: Str::random(8));

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'username' => $username,
            'password' => $input['password'],
        ]);
    }

    private function uniqueUsername(string $base): string
    {
        $username = $base;
        $suffix = 1;

        while (User::where('username', $username)->exists()) {
            $username = "{$base}-{$suffix}";
            $suffix++;
        }

        return $username;
    }
}
