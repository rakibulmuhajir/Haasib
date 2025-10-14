<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Collection;

class UserSettingsService
{
    /**
     * Get all settings for a user
     */
    public function getUserSettings(User $user, ?string $group = null): Collection
    {
        $query = UserSetting::where('user_id', $user->id);

        if ($group) {
            $query->where('group', $group);
        }

        return $query->get();
    }

    /**
     * Get a specific setting value
     */
    public function getSetting(User $user, string $key, string $group = 'general', $default = null)
    {
        return UserSetting::getSetting($user, $key, $group, $default);
    }

    /**
     * Set a setting value
     */
    public function setSetting(User $user, string $key, $value, string $group = 'general'): UserSetting
    {
        return UserSetting::setSetting($user, $key, $value, $group);
    }

    /**
     * Set multiple settings at once
     */
    public function setMultipleSettings(User $user, array $settings, string $group = 'general'): array
    {
        $results = [];

        foreach ($settings as $key => $value) {
            $results[$key] = $this->setSetting($user, $key, $value, $group);
        }

        return $results;
    }

    /**
     * Remove a setting
     */
    public function removeSetting(User $user, string $key, string $group = 'general'): bool
    {
        return UserSetting::removeSetting($user, $key, $group);
    }

    /**
     * Get settings formatted for frontend
     */
    public function getSettingsForFrontend(User $user, ?string $group = null): array
    {
        $settings = $this->getUserSettings($user, $group);
        $formatted = [];

        foreach ($settings as $setting) {
            if (! isset($formatted[$setting->group])) {
                $formatted[$setting->group] = [];
            }
            $formatted[$setting->group][$setting->key] = $setting->value;
        }

        return $formatted;
    }

    /**
     * Get currency-related settings
     */
    public function getCurrencySettings(User $user): array
    {
        return $this->getSettingsForFrontend($user, 'currency');
    }

    /**
     * Set currency-related settings
     */
    public function setCurrencySettings(User $user, array $settings): array
    {
        return $this->setMultipleSettings($user, $settings, 'currency');
    }
}
