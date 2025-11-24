<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyCurrency extends Model
{
    use BelongsToCompany, HasFactory, HasUuids;

    protected $table = 'auth.company_currencies';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'currency_code',
        'currency_name',
        'currency_symbol',
        'is_base_currency',
        'default_exchange_rate',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'string',
            'is_base_currency' => 'boolean',
            'default_exchange_rate' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Company relationship.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Exchange rates from this currency.
     */
    public function exchangeRatesFrom(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency_code', 'currency_code')
                    ->where('company_id', $this->company_id);
    }

    /**
     * Exchange rates to this currency.
     */
    public function exchangeRatesTo(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'to_currency_code', 'currency_code')
                    ->where('company_id', $this->company_id);
    }

    /**
     * Scope to only active currencies.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to base currency only.
     */
    public function scopeBaseCurrency($query)
    {
        return $query->where('is_base_currency', true);
    }

    /**
     * Scope to non-base currencies.
     */
    public function scopeForeignCurrencies($query)
    {
        return $query->where('is_base_currency', false);
    }

    /**
     * Get formatted currency display.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->currency_name} ({$this->currency_code})";
    }

    /**
     * Get formatted currency with symbol.
     */
    public function getDisplayWithSymbolAttribute(): string
    {
        return "{$this->currency_name} ({$this->currency_symbol})";
    }

    /**
     * Format an amount in this currency.
     */
    public function formatAmount(float $amount): string
    {
        $decimals = $this->currency_code === 'JPY' ? 0 : 2;
        
        return $this->currency_symbol . number_format($amount, $decimals);
    }

    /**
     * Check if this is the company's base currency.
     */
    public function isBaseCurrency(): bool
    {
        return $this->is_base_currency;
    }

    /**
     * Activate this currency for the company.
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Deactivate this currency for the company.
     * Cannot deactivate base currency.
     */
    public function deactivate(): void
    {
        if ($this->is_base_currency) {
            throw new \RuntimeException('Cannot deactivate base currency');
        }

        $this->update(['is_active' => false]);
    }

    /**
     * Update the default exchange rate.
     */
    public function updateDefaultExchangeRate(float $rate): void
    {
        if ($rate <= 0) {
            throw new \InvalidArgumentException('Exchange rate must be positive');
        }

        $this->update(['default_exchange_rate' => $rate]);
    }

    /**
     * Get the latest exchange rate to another currency.
     */
    public function getExchangeRateTo(string $toCurrencyCode, ?\DateTime $asOfDate = null): ?float
    {
        $asOfDate = $asOfDate ?? now();

        $exchangeRate = ExchangeRate::where('company_id', $this->company_id)
            ->where('from_currency_code', $this->currency_code)
            ->where('to_currency_code', $toCurrencyCode)
            ->where('effective_date', '<=', $asOfDate)
            ->orderByDesc('effective_date')
            ->orderByDesc('created_at')
            ->first();

        return $exchangeRate?->rate;
    }

    /**
     * Convert amount to another currency.
     */
    public function convertTo(float $amount, string $toCurrencyCode, ?\DateTime $asOfDate = null): ?float
    {
        if ($this->currency_code === $toCurrencyCode) {
            return $amount;
        }

        $rate = $this->getExchangeRateTo($toCurrencyCode, $asOfDate);
        
        if ($rate === null) {
            return null;
        }

        return $amount * $rate;
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Prevent deletion of base currency
        static::deleting(function ($currency) {
            if ($currency->is_base_currency) {
                throw new \RuntimeException('Cannot delete base currency');
            }
        });

        // Ensure currency code is uppercase
        static::creating(function ($currency) {
            $currency->currency_code = strtoupper($currency->currency_code);
        });

        static::updating(function ($currency) {
            $currency->currency_code = strtoupper($currency->currency_code);
        });
    }
}