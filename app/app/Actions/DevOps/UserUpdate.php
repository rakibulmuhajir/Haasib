<?php

namespace App\Actions\DevOps;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserUpdate
{
    public function handle(array $p, User $actor): array
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $data = Validator::make($p, [
            'email' => 'required|email', // target user
            'name' => 'nullable|string',
            'new_email' => 'nullable|email|unique:users,email',
            'password' => 'nullable|string|min:6',
            'password_confirm' => 'nullable|string|same:password',
        ])->validate();

        $user = User::where('email', $data['email'])->firstOrFail();

        DB::transaction(function () use (&$user, $data) {
            $updates = [];
            if (! empty($data['name'])) {
                $updates['name'] = $data['name'];
            }
            if (! empty($data['new_email'])) {
                $updates['email'] = $data['new_email'];
            }
            if (! empty($data['password'])) {
                $updates['password'] = $data['password'];
            }
            if ($updates) {
                $user->update($updates);
            }
        });

        return ['message' => 'User updated', 'data' => ['email' => $user->email]];
    }
}
