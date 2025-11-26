<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
     * Get the configured currencies for this company.
     */
    public function currencies(): HasMany
    {
        return $this->hasMany(CompanyCurrency::class);
    }

    /**
     * Get the base currency for this company.
     */
    public function baseCurrency(): HasOne
    {
        return $this->hasOne(CompanyCurrency::class)->where('is_base_currency', true);
    }


    /**
     * Get active currencies only.
     */
    public function activeCurrencies(): HasMany
    {
        return $this->currencies()->where('is_active', true);
    }


    /**
     * Get exchange rates for this company.
     */
    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'company_id');
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
     * Get company's base currency code.
     */
    public function getBaseCurrencyCode(): string
    {
        $baseCurrency = $this->relationLoaded('baseCurrency') 
            ? $this->baseCurrency 
            : $this->baseCurrency()->first();
            
        return $baseCurrency?->currency_code ?? 'USD';
    }

    /**
     * Get company's base currency.
     */
    public function getBaseCurrency(): ?CompanyCurrency
    {
        return $this->relationLoaded('baseCurrency') 
            ? $this->baseCurrency 
            : $this->baseCurrency()->first();
    }

    /**
     * Add a currency to the company.
     */
    public function addCurrency(
        string $currencyCode,
        float $defaultExchangeRate = 1.0,
        bool $isBaseCurrency = false,
        bool $isActive = true
    ): CompanyCurrency {
        // Get currency info from catalog
        $catalogInfo = CurrencyCatalog::getCurrencyInfo($currencyCode);
        
        if (!$catalogInfo) {
            throw new \InvalidArgumentException("Currency {$currencyCode} not found in catalog");
        }

        // Check if currency already exists
        $existing = $this->currencies()
            ->where('currency_code', strtoupper($currencyCode))
            ->first();
            
        if ($existing) {
            throw new \RuntimeException("Currency {$currencyCode} already exists for this company");
        }

        // If this is a base currency, ensure no other base currency exists
        if ($isBaseCurrency) {
            $existingBase = $this->baseCurrency;
            if ($existingBase) {
                throw new \RuntimeException("Company already has a base currency: {$existingBase->currency_code}");
            }
        }

        return $this->currencies()->create([
            'currency_code' => strtoupper($currencyCode),
            'currency_name' => $catalogInfo['name'],
            'currency_symbol' => $catalogInfo['symbol'],
            'is_base_currency' => $isBaseCurrency,
            'default_exchange_rate' => $defaultExchangeRate,
            'is_active' => $isActive,
        ]);
    }

    /**
     * Remove a currency from the company.
     */
    public function removeCurrency(string $currencyCode): bool
    {
        $currency = $this->currencies()
            ->where('currency_code', strtoupper($currencyCode))
            ->first();

        if (!$currency) {
            return false;
        }

        if ($currency->is_base_currency) {
            throw new \RuntimeException('Cannot remove base currency');
        }

        return $currency->delete();
    }

    /**
     * Set base currency for the company.
     */
    public function setBaseCurrency(string $currencyCode): CompanyCurrency
    {
        $currency = $this->currencies()
            ->where('currency_code', strtoupper($currencyCode))
            ->first();

        if (!$currency) {
            $currency = $this->addCurrency($currencyCode, 1.0, true, true);
        } else {
            // Remove base status from current base currency
            $currentBase = $this->baseCurrency;
            if ($currentBase && $currentBase->id !== $currency->id) {
                $currentBase->update(['is_base_currency' => false]);
            }

            // Set new base currency
            $currency->update([
                'is_base_currency' => true,
                'default_exchange_rate' => 1.0,
                'is_active' => true,
            ]);
        }

        return $currency;
    }

    /**
     * Get available currencies for selection.
     */
    public function getAvailableCurrencies(): array
    {
        return $this->activeCurrencies()
            ->orderByDesc('is_base_currency')
            ->orderBy('currency_name')
            ->get()
            ->map(function ($currency) {
                return [
                    'code' => $currency->currency_code,
                    'name' => $currency->currency_name,
                    'symbol' => $currency->currency_symbol,
                    'display_name' => $currency->display_name,
                    'is_base' => $currency->is_base_currency,
                    'default_rate' => $currency->default_exchange_rate,
                ];
            })
            ->toArray();
    }

    /**
     * Convert amount between currencies.
     */
    public function convertCurrency(
        float $amount,
        string $fromCurrency,
        string $toCurrency,
        ?\DateTime $asOfDate = null
    ): ?float {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        return ExchangeRate::convertAmount(
            $this->id,
            $amount,
            $fromCurrency,
            $toCurrency,
            $asOfDate
        );
    }

    /**
     * Get latest exchange rate between currencies.
     */
    public function getExchangeRate(
        string $fromCurrency,
        string $toCurrency,
        ?\DateTime $asOfDate = null
    ): ?float {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        return ExchangeRate::getDbRate(
            $this->id,
            $fromCurrency,
            $toCurrency,
            $asOfDate
        );
    }

    /**
     * Set exchange rate between currencies.
     */
    public function setExchangeRate(
        string $fromCurrency,
        string $toCurrency,
        float $rate,
        ?\DateTime $effectiveDate = null,
        string $source = 'manual',
        ?string $notes = null,
        ?string $userId = null
    ): ExchangeRate {
        return ExchangeRate::setRate(
            $this->id,
            $fromCurrency,
            $toCurrency,
            $rate,
            $effectiveDate,
            $source,
            $notes,
            $userId
        );
    }

    /**
     * Check if multi-currency feature is enabled.
     */
    public function isMultiCurrencyEnabled(): bool
    {
        return $this->getSetting('features.multi_currency.enabled', false);
    }

    /**
     * Enable multi-currency feature.
     */
    public function enableMultiCurrency(): void
    {
        $this->setSetting('features.multi_currency.enabled', true);
    }

    /**
     * Disable multi-currency feature.
     */
    public function disableMultiCurrency(): void
    {
        $this->setSetting('features.multi_currency.enabled', false);
    }

    /**
     * Check if company supports multiple currencies.
     */
    public function hasMultipleCurrencies(): bool
    {
        return $this->isMultiCurrencyEnabled() && $this->activeCurrencies()->count() > 1;
    }

    /**
     * Check if company has a specific currency.
     */
    public function hasCurrency(string $currencyCode): bool
    {
        return $this->activeCurrencies()
            ->where('currency_code', strtoupper($currencyCode))
            ->exists();
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
