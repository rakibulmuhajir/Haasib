<?php

namespace App\Actions\User;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeactivateUser
{
    public function handle(array $payload, User $actor): array
    {
        $validated = Validator::make($payload, [
            'user' => 'required|string',
        ])->validate();

        $user = $this->findUser($validated['user']);

        abort_unless($actor->isSuperAdmin(), 403, 'Only SuperAdmins can deactivate users');

        // Prevent deactivating yourself
        if ($user->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot deactivate your own account',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user' => 'User is already inactive',
            ]);
        }

        $user->deactivate();

        Log::info('User deactivated', [
            'action' => 'user.deactivate',
            'user_id' => $user->id,
            'user_email' => $user->email,
            'deactivated_by' => $actor->id,
            'deactivated_by_email' => $actor->email,
            'timestamp' => now()->toDateTimeString(),
        ]);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => false,
        ];
    }

    private function findUser(string $identifier): User
    {
        // Check if it's a UUID
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $identifier)) {
            $user = User::find($identifier);
        } else {
            // Try email first, then fall back to name
            $user = User::where('email', $identifier)->first()
                ?? User::where('name', $identifier)->first();
        }

        if (! $user) {
            abort(404, 'User not found');
        }

        return $user;
    }
}
