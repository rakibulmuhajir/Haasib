<?php

namespace App\Actions\Settings;

use App\Contracts\PaletteAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdatePreferencesAction implements PaletteAction
{
    public function rules(): array
    {
        return ['show_value_trails' => ['required', 'boolean']];
    }

    public function permission(): ?string
    {
        // This changes only the caller's personal preferences, never company data.
        return null;
    }

    public function handle(array $params): array
    {
        $userId = Auth::id();
        if (! $userId) {
            throw new AuthorizationException('Sign in to update your preferences.');
        }

        DB::transaction(function () use ($userId, $params) {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $user->settings = array_replace($user->settings ?? [], [
                'show_value_trails' => (bool) $params['show_value_trails'],
            ]);
            $user->save();
        });

        return ['message' => 'Preferences saved.'];
    }
}
