<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The database connection that should be used by the model.
     */
    protected $connection = 'pgsql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'auth.companies';

    /**
     * The attributes that are not mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'created_at', 'updated_at', 'is_active'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exchange_rate_id' => 'integer',
            'settings' => 'array',
            'industry' => 'string',
            'country_id' => 'string',
            'currency_id' => 'string',
            'created_by_user_id' => 'string',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the users that belong to the company.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'auth.company_user')
            ->withPivot('role', 'is_active', 'joined_at', 'left_at')
            ->withTimestamps();
    }

    /**
     * Get the invitations for this company.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'company_id');
    }

    /**
     * Get the modules enabled for this company.
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'auth.company_modules')
            ->withPivot('is_active', 'enabled_at', 'enabled_by_user_id', 'disabled_at', 'disabled_by_user_id', 'settings')
            ->withTimestamps();
    }

    /**
     * Get audit entries for this company.
     */
    public function auditEntries(): HasMany
    {
        return $this->hasMany(AuditEntry::class);
    }

    /**
     * Get the creator of the company.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scope a query to only include active companies.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if a module is enabled for this company (with caching).
     */
    public function isModuleEnabled(string $moduleName): bool
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "company:{$this->id}:module:{$moduleName}:enabled",
            300, // 5 minutes
            function () use ($moduleName) {
                return $this->modules()
                    ->where(function ($query) use ($moduleName) {
                        $query->where('modules.key', $moduleName)
                            ->orWhere('modules.name', $moduleName);
                    })
                    ->wherePivot('is_active', true)
                    ->exists();
            }
        );
    }

    /**
     * Enable a module for this company.
     */
    public function enableModule(string $moduleName, ?User $user = null): void
    {
        $module = Module::where('key', $moduleName)
            ->orWhere('name', $moduleName)
            ->firstOrFail();

        $this->modules()->syncWithoutDetaching([
            $module->id => [
                'is_active' => true,
                'enabled_at' => now(),
                'enabled_by_user_id' => $user?->id,
                'settings' => json_encode([]),
                'disabled_at' => null,
                'disabled_by_user_id' => null,
            ],
        ]);

        // Clear related cache
        \Illuminate\Support\Facades\Cache::forget("company:{$this->id}:module:{$moduleName}:enabled");
    }

    /**
     * Disable a module for this company.
     */
    public function disableModule(string $moduleName, ?User $user = null): void
    {
        $module = Module::where('key', $moduleName)
            ->orWhere('name', $moduleName)
            ->firstOrFail();

        $this->modules()->updateExistingPivot($module->id, [
            'is_active' => false,
            'disabled_at' => now(),
            'disabled_by_user_id' => $user?->id,
        ]);

        // Clear related cache
        \Illuminate\Support\Facades\Cache::forget("company:{$this->id}:module:{$moduleName}:enabled");
    }

    /**
     * Determine if the company has a module enabled by key or name.
     */
    public function hasModuleEnabled(string $moduleKey): bool
    {
        return $this->modules()
            ->where(function ($query) use ($moduleKey) {
                $query->where('modules.key', $moduleKey)
                    ->orWhere('modules.name', $moduleKey);
            })
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * Count active modules.
     */
    public function getActiveModulesCount(): int
    {
        return $this->modules()
            ->wherePivot('is_active', true)
            ->count();
    }

    /**
     * Get company setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set company setting value.
     */
    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($company) {
            if (empty($company->slug)) {
                $company->slug = \Illuminate\Support\Str::slug($company->name);
                
                // Ensure unique slug
                $originalSlug = $company->slug;
                $counter = 1;
                
                while (static::where('slug', $company->slug)->exists()) {
                    $company->slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\CompanyFactory::new();
    }
}
