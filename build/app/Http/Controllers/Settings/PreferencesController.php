<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PreferencesUpdateRequest;
use App\Services\CommandBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class PreferencesController extends Controller
{
    public function update(PreferencesUpdateRequest $request, CommandBus $bus): RedirectResponse
    {
        try {
            $result = $bus->dispatch('user.preferences.update', $request->validated(), $request->user());

            return back()->with('success', $result['message']);
        } catch (\Throwable $exception) {
            Log::error('Could not update personal preferences.', ['exception' => $exception]);

            return back()->with('error', 'Could not save your preferences. Please try again.');
        }
    }
}
