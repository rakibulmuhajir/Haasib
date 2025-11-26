<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ExchangeRate extends Model
{
    use BelongsToCompany, HasFactory, HasUuids;

    protected $table = 'auth.exchange_rates';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'from_currency_code',
        'to_currency_code',
        'rate',
        'effective_date',
        'source',
        'notes',
        'created_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'string',
            'rate' => 'decimal:6',
            'effective_date' => 'date',
            'created_by_user_id' => 'string',
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
     * Creator relationship.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * From currency relationship.
     */
    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(CompanyCurrency::class, 'from_currency_code', 'currency_code')
                    ->where('company_id', $this->company_id);
    }

    /**
     * To currency relationship.
     */
    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(CompanyCurrency::class, 'to_currency_code', 'currency_code')
                    ->where('company_id', $this->company_id);
    }

    /**
     * Scope to specific currency pair.
     */
    public function scopeForCurrencyPair($query, string $fromCode, string $toCode)
    {
        return $query->where('from_currency_code', $fromCode)
                     ->where('to_currency_code', $toCode);
    }

    /**
     * Scope to effective rates as of a specific date.
     */
    public function scopeEffectiveAsOf($query, \DateTime $date)
    {
        return $query->where('effective_date', '<=', $date);
    }

    /**
     * Scope to latest rates.
     */
    public function scopeLatest($query)
    {
        return $query->orderByDesc('effective_date')->orderByDesc('created_at');
    }

    /**
     * Scope by source.
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Get the inverse rate.
     */
    public function getInverseRateAttribute(): float
    {
        return $this->rate > 0 ? 1.0 / $this->rate : 0.0;
    }

    /**
     * Get formatted rate display.
     */
    public function getFormattedRateAttribute(): string
    {
        return "1 {$this->from_currency_code} = {$this->rate} {$this->to_currency_code}";
    }

    /**
     * Convert an amount using this rate.
     */
    public function convertAmount(float $amount): float
    {
        return $amount * $this->rate;
    }

    /**
     * Check if this rate is current (effective today).
     */
    public function isCurrent(): bool
    {
        return $this->effective_date->isToday() || $this->effective_date->isPast();
    }

    /**
     * Check if this rate is from the future.
     */
    public function isFuture(): bool
    {
        return $this->effective_date->isFuture();
    }

    /**
     * Get the age of this rate in days.
     */
    public function getAgeInDays(): int
    {
        return now()->diffInDays($this->effective_date);
    }

    /**
     * Check if this rate is stale (older than specified days).
     */
    public function isStale(int $maxDays = 7): bool
    {
        return $this->getAgeInDays() > $maxDays;
    }

    /**
     * Static method to get the latest rate for a currency pair.
     */
    public static function getLatestRate(
        string $companyId,
        string $fromCode,
        string $toCode,
        ?\DateTime $asOfDate = null
    ): ?static {
        $asOfDate = $asOfDate ?? now();

        return static::where('company_id', $companyId)
            ->forCurrencyPair($fromCode, $toCode)
            ->effectiveAsOf($asOfDate)
            ->latest()
            ->first();
    }

    /**
     * Static method to convert amount between currencies.
     */
    public static function convertAmount(
        string $companyId,
        float $amount,
        string $fromCode,
        string $toCode,
        ?\DateTime $asOfDate = null
    ): ?float {
        if ($fromCode === $toCode) {
            return $amount;
        }

        $rate = static::getLatestRate($companyId, $fromCode, $toCode, $asOfDate);
        
        if (!$rate) {
            // Try inverse rate
            $inverseRate = static::getLatestRate($companyId, $toCode, $fromCode, $asOfDate);
            if ($inverseRate) {
                return $amount / $inverseRate->rate;
            }
            return null;
        }

        return $rate->convertAmount($amount);
    }

    /**
     * Create or update exchange rate.
     */
    public static function setRate(
        string $companyId,
        string $fromCode,
        string $toCode,
        float $rate,
        ?\DateTime $effectiveDate = null,
        string $source = 'manual',
        ?string $notes = null,
        ?string $createdByUserId = null
    ): static {
        $effectiveDate = $effectiveDate ?? now();

        return static::updateOrCreate(
            [
                'company_id' => $companyId,
                'from_currency_code' => strtoupper($fromCode),
                'to_currency_code' => strtoupper($toCode),
                'effective_date' => $effectiveDate,
            ],
            [
                'rate' => $rate,
                'source' => $source,
                'notes' => $notes,
                'created_by_user_id' => $createdByUserId,
            ]
        );
    }

    /**
     * Use database function to get rate with fallback logic.
     */
    public static function getDbRate(
        string $companyId,
        string $fromCode,
        string $toCode,
        ?\DateTime $asOfDate = null
    ): ?float {
        $asOfDate = $asOfDate ?? now();

        $result = DB::selectOne(
            'SELECT auth.get_latest_exchange_rate(?, ?, ?, ?) as rate',
            [$companyId, strtoupper($fromCode), strtoupper($toCode), $asOfDate->format('Y-m-d')]
        );

        return $result ? (float) $result->rate : null;
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Ensure currency codes are uppercase
        static::creating(function ($rate) {
            $rate->from_currency_code = strtoupper($rate->from_currency_code);
            $rate->to_currency_code = strtoupper($rate->to_currency_code);
        });

        static::updating(function ($rate) {
            $rate->from_currency_code = strtoupper($rate->from_currency_code);
            $rate->to_currency_code = strtoupper($rate->to_currency_code);
        });
    }
}