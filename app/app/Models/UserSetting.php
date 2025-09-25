<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    use HasFactory;

    protected $table = 'user_settings';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'group',
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include settings of a given group.
     */
    public function scopeGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Scope a query to only include settings with a given key.
     */
    public function scopeKey($query, $key)
    {
        return $query->where('key', $key);
    }

    /**
     * Get a setting value for a user
     */
    public static function getSetting(User $user, string $key, string $group = 'general', $default = null)
    {
        $setting = static::where('user_id', $user->id)
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value for a user
     */
    public static function setSetting(User $user, string $key, $value, string $group = 'general'): UserSetting
    {
        return static::updateOrCreate(
            [
                'user_id' => $user->id,
                'group' => $group,
                'key' => $key,
            ],
            [
                'value' => $value,
            ]
        );
    }

    /**
     * Remove a setting for a user
     */
    public static function removeSetting(User $user, string $key, string $group = 'general'): bool
    {
        return static::where('user_id', $user->id)
            ->where('group', $group)
            ->where('key', $key)
            ->delete();
    }

    /**
     * Get all settings for a user, optionally filtered by group
     */
    public static function getAllSettings(User $user, ?string $group = null): array
    {
        $query = static::where('user_id', $user->id);

        if ($group) {
            $query->where('group', $group);
        }

        $settings = [];
        foreach ($query->get() as $setting) {
            $settings["{$setting->group}.{$setting->key}"] = $setting->value;
        }

        return $settings;
    }
}
