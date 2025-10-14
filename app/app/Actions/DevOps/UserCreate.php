<?php

namespace App\Actions\DevOps;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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

        $user = DB::transaction(function () use ($data, $actor) {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'] ?? Str::random(12),
                'system_role' => $data['system_role'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);
        });

        return ['message' => 'User created', 'data' => ['email' => $user->email]];
    }
}
