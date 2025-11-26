<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyCatalog extends Model
{
    protected $table = 'auth.currency_catalog';

    protected $primaryKey = 'currency_code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'currency_code',
        'currency_name',
        'currency_symbol',
        'decimal_places',
        'is_popular',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_popular' => 'boolean',
        ];
    }

    /**
     * Scope to popular currencies only.
     */
    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    /**
     * Scope to all currencies ordered by popularity then name.
     */
    public function scopeOrdered($query)
    {
        return $query->orderByDesc('is_popular')->orderBy('currency_name');
    }

    /**
     * Search currencies by code or name.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('currency_code', 'ILIKE', "%{$search}%")
              ->orWhere('currency_name', 'ILIKE', "%{$search}%");
        });
    }

    /**
     * Get display name with code.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->currency_name} ({$this->currency_code})";
    }

    /**
     * Get display name with symbol.
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
        return $this->currency_symbol . number_format($amount, $this->decimal_places);
    }

    /**
     * Get popular currencies for quick selection.
     */
    public static function getPopularCurrencies(): array
    {
        return static::popular()
            ->ordered()
            ->get()
            ->map(function ($currency) {
                return [
                    'code' => $currency->currency_code,
                    'name' => $currency->currency_name,
                    'symbol' => $currency->currency_symbol,
                    'display_name' => $currency->display_name,
                    'decimal_places' => $currency->decimal_places,
                ];
            })
            ->toArray();
    }

    /**
     * Search currencies for selection.
     */
    public static function searchCurrencies(string $search = '', int $limit = 50): array
    {
        $query = static::ordered();

        if (!empty($search)) {
            $query->search($search);
        }

        return $query->limit($limit)
            ->get()
            ->map(function ($currency) {
                return [
                    'code' => $currency->currency_code,
                    'name' => $currency->currency_name,
                    'symbol' => $currency->currency_symbol,
                    'display_name' => $currency->display_name,
                    'decimal_places' => $currency->decimal_places,
                    'is_popular' => $currency->is_popular,
                ];
            })
            ->toArray();
    }

    /**
     * Get currency information by code.
     */
    public static function getCurrencyInfo(string $currencyCode): ?array
    {
        $currency = static::find(strtoupper($currencyCode));

        if (!$currency) {
            return null;
        }

        return [
            'code' => $currency->currency_code,
            'name' => $currency->currency_name,
            'symbol' => $currency->currency_symbol,
            'display_name' => $currency->display_name,
            'decimal_places' => $currency->decimal_places,
            'is_popular' => $currency->is_popular,
        ];
    }
}