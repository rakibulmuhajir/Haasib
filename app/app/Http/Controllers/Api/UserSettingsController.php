<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserSettingsRequest;
use App\Models\User;
use App\Services\UserSettingsService;
use Illuminate\Http\Request;

class UserSettingsController extends Controller
{
    public function __construct(
        private UserSettingsService $settingsService
    ) {}

    /**
     * Get user settings
     */
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $settings = $this->settingsService->getSettingsForFrontend($user);

        return response()->json([
            'data' => $settings,
        ]);
    }

    /**
     * Get settings for a specific group
     */
    public function show(Request $request, string $group)
    {
        /** @var User $user */
        $user = $request->user();

        $settings = $this->settingsService->getSettingsForFrontend($user, $group);

        return response()->json([
            'data' => $settings[$group] ?? [],
        ]);
    }

    /**
     * Update user settings
     */
    public function update(UpdateUserSettingsRequest $request)
    {
        /** @var User $user */
        $user = $request->user();

        $group = $request->input('group', 'general');
        $settings = $request->input('settings', []);

        $updatedSettings = $this->settingsService->setMultipleSettings($user, $settings, $group);

        return response()->json([
            'message' => 'Settings updated successfully',
            'data' => $updatedSettings,
        ]);
    }

    /**
     * Update a specific setting
     */
    public function updateSetting(UpdateUserSettingsRequest $request, string $group, string $key)
    {
        /** @var User $user */
        $user = $request->user();

        $value = $request->input('value');

        $setting = $this->settingsService->setSetting($user, $key, $value, $group);

        return response()->json([
            'message' => 'Setting updated successfully',
            'data' => [
                'group' => $group,
                'key' => $key,
                'value' => $setting->value,
            ],
        ]);
    }
}
