<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Currency extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'currencies';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'name',
        'symbol',
        'symbol_position',
        'decimal_places',
        'thousands_separator',
        'decimal_separator',
        'is_active',
        'exchange_rate',
        'last_updated_at',
        'metadata',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'is_active' => 'boolean',
        'exchange_rate' => 'decimal:6',
        'last_updated_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'symbol_position' => 'before',
        'decimal_places' => 2,
        'thousands_separator' => ',',
        'decimal_separator' => '.',
        'is_active' => true,
        'exchange_rate' => 1.0,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = $model->id ?: (string) Str::uuid();
        });
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'base_currency', 'code');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function accountsReceivable(): HasMany
    {
        return $this->hasMany(AccountsReceivable::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function formatAmount(float $amount): string
    {
        $formattedNumber = number_format(
            $amount,
            $this->decimal_places,
            $this->decimal_separator,
            $this->thousands_separator
        );

        if ($this->symbol_position === 'before') {
            return $this->symbol.$formattedNumber;
        }

        return $formattedNumber.$this->symbol;
    }

    public function parseAmount(string $formattedAmount): float
    {
        $cleanAmount = str_replace([$this->thousands_separator, $this->symbol], ['', ''], $formattedAmount);
        $cleanAmount = str_replace($this->decimal_separator, '.', $cleanAmount);

        return (float) $cleanAmount;
    }

    public function isBaseCurrency(): bool
    {
        return $this->code === 'USD';
    }

    public function getExchangeRate(): float
    {
        return $this->exchange_rate ?? 1.0;
    }

    public function updateExchangeRate(float $rate): void
    {
        $this->exchange_rate = $rate;
        $this->last_updated_at = now();
        $this->save();
    }

    public function convertToBase(float $amount): float
    {
        if ($this->isBaseCurrency()) {
            return $amount;
        }

        return $amount * $this->exchange_rate;
    }

    public function convertFromBase(float $amount): float
    {
        if ($this->isBaseCurrency()) {
            return $amount;
        }

        return $amount / $this->exchange_rate;
    }

    public function convertTo(Currency $targetCurrency, float $amount): float
    {
        if ($this->id === $targetCurrency->id) {
            return $amount;
        }

        $baseAmount = $this->convertToBase($amount);

        return $targetCurrency->convertFromBase($baseAmount);
    }

    public function isFiat(): bool
    {
        return in_array($this->code, ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY', 'INR', 'PKR', 'AED']);
    }

    public function getCurrencyType(): string
    {
        if ($this->isFiat()) {
            return 'fiat';
        }

        if (in_array($this->code, ['BTC', 'ETH', 'LTC'])) {
            return 'cryptocurrency';
        }

        return 'other';
    }

    public function getDisplayName(): string
    {
        return "{$this->name} ({$this->code})";
    }

    public function getDisplaySymbol(): string
    {
        return $this->symbol ?: $this->code;
    }

    public function isValidAmount(float $amount): bool
    {
        $minAmount = 0;
        $maxAmount = 999999999999.99;

        return $amount >= $minAmount && $amount <= $maxAmount;
    }

    public function roundAmount(float $amount): float
    {
        $factor = pow(10, $this->decimal_places);

        return round($amount * $factor) / $factor;
    }

    public static function getDefaultCurrency(): self
    {
        return static::where('code', 'USD')->first() ?? static::first();
    }

    public static function getActiveCurrencies()
    {
        return static::active()->orderBy('name')->get();
    }

    public static function getFiatCurrencies()
    {
        return static::active()->whereIn('code', ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY', 'INR', 'PKR', 'AED'])->get();
    }

    public static function getCryptocurrencies()
    {
        return static::active()->whereIn('code', ['BTC', 'ETH', 'LTC'])->get();
    }
}
