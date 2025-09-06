<?php

namespace App\Actions\DevOps;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserCreate
{
    public function handle(array $p, User $actor): array
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $data = Validator::make($p, [
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'nullable|string',
            'system_role' => 'nullable|in:superadmin',
        ])->validate();

        $user = DB::transaction(function () use ($data) {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'] ?? Str::random(12),
                'system_role' => $data['system_role'] ?? null,
            ]);
        });

        return ['message' => 'User created', 'data' => ['email' => $user->email]];
    }
}
