<?php

namespace App\Actions\DevOps;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UserDelete
{
    public function handle(array $p, User $actor): array
    {
        abort_unless($actor->isSuperAdmin(), 403);

        $data = Validator::make($p, [
            'email' => 'required|email',
        ])->validate();

        DB::transaction(function () use ($data) {
            $user = User::where('email', $data['email'])->firstOrFail();
            $user->delete();
        });

        return ['message' => 'User deleted', 'data' => ['email' => $data['email']]];
    }
}
