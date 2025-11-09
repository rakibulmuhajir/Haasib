<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
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
    protected $table = 'auth.modules';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'display_name',
        'description',
        'version',
        'category',
        'icon',
        'is_core',
        'is_active',
        'dependencies',
        'permissions',
        'settings_schema',
        'migration_path',
        'route_path',
        'provider_class',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
            'is_active' => 'boolean',
            'dependencies' => 'array',
            'permissions' => 'array',
            'settings_schema' => 'array',
        ];
    }

    /**
     * Get the companies that have this module enabled.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'auth.company_modules')
            ->withPivot('is_active', 'enabled_at', 'enabled_by_user_id', 'disabled_at', 'disabled_by_user_id', 'settings')
            ->withTimestamps();
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function getSettingSchema(): array
    {
        return $this->settings_schema ?? [];
    }

    /**
     * Get the company module pivots.
     */
    public function companyModules(): HasMany
    {
        return $this->hasMany(CompanyModule::class);
    }

    public function getEnabledCompaniesCount(): int
    {
        return $this->companyModules()
            ->where('is_active', true)
            ->count();
    }

    public function isEnabledForCompany(string $companyId): bool
    {
        return $this->companyModules()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Scope a query to only include active modules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include core modules.
     */
    public function scopeCore($query)
    {
        return $query->where('is_core', true);
    }

    /**
     * Scope a query to only include modules in a specific category.
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Check if the module is a core module.
     */
    public function isCoreModule(): bool
    {
        return $this->is_core;
    }

    /**
     * Check if the module has a specific dependency.
     */
    public function hasDependency(string $dependency): bool
    {
        return in_array($dependency, $this->dependencies ?? []);
    }

    /**
     * Get all dependencies for this module.
     */
    public function getDependencies(): array
    {
        return $this->dependencies ?? [];
    }

    /**
     * Check if the module requires specific permissions.
     */
    public function requiresPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Get the provider class for the module.
     */
    public function getProviderClass(): ?string
    {
        return $this->provider_class;
    }

    /**
     * Check if the module is registered in the application.
     */
    public function isRegistered(): bool
    {
        return config("modules.{$this->name}") !== null;
    }

    /**
     * Get the module's configuration.
     */
    public function getConfig(): array
    {
        return config("modules.{$this->name}", []);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\ModuleFactory::new();
    }
}
