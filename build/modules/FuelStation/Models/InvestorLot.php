<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Investor Lot - Lot model for investment tracking.
 *
 * Locked entitlement_rate prevents rate-change disputes:
 * - Investor deposits 100,000 PKR when petrol purchase rate = 250 PKR/liter
 * - units_entitled = 100,000 / 250 = 400 liters (LOCKED)
 * - Rate changes next week to 260 PKR/liter
 * - Without lots: recalculating would give 384 liters → dispute
 * - With lots: 400 liters stays fixed, commission deterministic
 */
class InvestorLot extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.investor_lots';
    protected $keyType = 'string';
    public $incrementing = false;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DEPLETED = 'depleted';
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'company_id',
        'investor_id',
        'deposit_date',
        'investment_amount',
        'entitlement_rate',
        'commission_rate',
        'units_entitled',
        'units_remaining',
        'commission_earned',
        'status',
        'journal_entry_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'investor_id' => 'string',
        'deposit_date' => 'date',
        'investment_amount' => 'decimal:2',
        'entitlement_rate' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'units_entitled' => 'decimal:2',
        'units_remaining' => 'decimal:2',
        'commission_earned' => 'decimal:2',
        'journal_entry_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (InvestorLot $lot) {
            // Calculate units_entitled from investment / entitlement_rate
            if ($lot->investment_amount && $lot->entitlement_rate && !$lot->units_entitled) {
                $lot->units_entitled = $lot->investment_amount / $lot->entitlement_rate;
                $lot->units_remaining = $lot->units_entitled;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Consume units from this lot (for investor sales).
     *
     * @param float $units Units to consume
     * @return float Commission earned for these units
     */
    public function consumeUnits(float $units): float
    {
        $actualUnits = min($units, $this->units_remaining);
        $commission = $actualUnits * $this->commission_rate;

        $this->units_remaining -= $actualUnits;
        $this->commission_earned += $commission;

        if ($this->units_remaining <= 0) {
            $this->status = self::STATUS_DEPLETED;
        }

        $this->save();

        return $commission;
    }

    /**
     * Get the percentage of lot consumed.
     */
    public function getConsumptionPercentageAttribute(): float
    {
        if ($this->units_entitled <= 0) {
            return 0;
        }

        return (($this->units_entitled - $this->units_remaining) / $this->units_entitled) * 100;
    }

    /**
     * Check if this lot has remaining units.
     */
    public function hasRemainingUnits(): bool
    {
        return $this->units_remaining > 0;
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_DEPLETED,
            self::STATUS_WITHDRAWN,
        ];
    }
}
