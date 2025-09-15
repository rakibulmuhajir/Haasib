<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanySecondaryCurrency extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'company_secondary_currencies';

    protected $fillable = [
        'company_id',
        'currency_id',
        'exchange_rate_id',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function exchangeRate(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class, 'exchange_rate_id', 'exchange_rate_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
