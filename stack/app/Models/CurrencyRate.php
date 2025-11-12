<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    use HasFactory;

    protected $table = 'currency_rates';

    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'valid_from',
        'valid_until',
        'provider',
        'notes',
    ];

    protected $casts = [
        'rate' => 'decimal:15',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope rates by currency pair.
     */
    public function scopeForPair($query, string $fromCurrency, string $toCurrency)
    {
        return $query->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency);
    }

    /**
     * Scope rates valid for a specific date.
     */
    public function scopeValidFor($query, $date)
    {
        return $query->where('valid_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->where('valid_until', '>=', $date)
                    ->orWhereNull('valid_until');
            });
    }

    /**
     * Scope current rates.
     */
    public function scopeCurrent($query)
    {
        return $query->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->where('valid_until', '>=', now())
                    ->orWhereNull('valid_until');
            });
    }

    /**
     * Get formatted rate.
     */
    public function getFormattedRateAttribute(): string
    {
        return number_format($this->rate, 6);
    }

    /**
     * Check if rate is currently valid.
     */
    public function isCurrentlyValid(): bool
    {
        $now = now();

        return $this->valid_from <= $now &&
               (! $this->valid_until || $this->valid_until >= $now);
    }

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function ($rate) {
            if (empty($rate->valid_from)) {
                $rate->valid_from = now();
            }
        });
    }
}
