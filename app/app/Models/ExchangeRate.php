<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $table = 'exchange_rates';

    protected $primaryKey = 'exchange_rate_id';

    protected $fillable = [
        'base_currency_id',
        'target_currency_id',
        'rate',
        'effective_date',
        'source',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:10',
        'effective_date' => 'date',
        'source' => 'string',
        'is_active' => 'boolean',
    ];

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function targetCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'target_currency_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc');
    }

    public function scopeForCurrencyPair($query, $baseCurrencyId, $targetCurrencyId)
    {
        return $query->where('base_currency_id', $baseCurrencyId)
            ->where('target_currency_id', $targetCurrencyId);
    }
}
