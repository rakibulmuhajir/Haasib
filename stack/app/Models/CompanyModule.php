<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CompanyModule extends Pivot
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'auth.company_modules';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'module_id',
        'is_active',
        'settings',
        'enabled_by_user_id',
        'enabled_at',
        'disabled_by_user_id',
        'disabled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
            'enabled_by_user_id' => 'string',
            'disabled_by_user_id' => 'string',
            'settings' => 'array',
        ];
    }

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Get the company that owns the pivot.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the module that owns the pivot.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who enabled the module.
     */
    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by_user_id');
    }

    /**
     * Get the user who disabled the module.
     */
    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by_user_id');
    }

    /**
     * Enable the module for the company.
     */
    public function enable(?User $user = null): void
    {
        $this->is_active = true;
        $this->enabled_at = now();
        $this->enabled_by_user_id = $user?->id;
        $this->disabled_at = null;
        $this->disabled_by_user_id = null;
        $this->save();
    }

    /**
     * Disable the module for the company.
     */
    public function disable(?User $user = null): void
    {
        $this->is_active = false;
        $this->disabled_at = now();
        $this->disabled_by_user_id = $user?->id;
        $this->save();
    }

    /**
     * Get a module setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set a module setting value.
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Check if the module has been enabled for at least the given number of days.
     */
    public function isEnabledForDays(int $days): bool
    {
        if (! $this->is_active || ! $this->enabled_at) {
            return false;
        }

        return $this->enabled_at->diffInDays(now()) >= $days;
    }

    /**
     * Scope a query to only include enabled modules (alias for active).
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\CompanyModuleFactory::new();
    }
}
