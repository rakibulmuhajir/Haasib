<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Modules\Accounting\Models\Invoice;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fuel Sale Metadata - Fuel-specific invoice/sale data.
 *
 * Links 1:1 to acct.invoices, keeping the core accounting module clean.
 * Contains fuel station specific fields like sale type, pump reference,
 * and attendant transit status.
 */
class SaleMetadata extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.sale_metadata';
    protected $keyType = 'string';
    public $incrementing = false;

    public const TYPE_RETAIL = 'retail';
    public const TYPE_BULK = 'bulk';
    public const TYPE_CREDIT = 'credit';
    public const TYPE_AMANAT = 'amanat';
    public const TYPE_INVESTOR = 'investor';
    public const TYPE_PARCO_CARD = 'parco_card';

    public const DISCOUNT_BULK = 'bulk_discount';
    public const DISCOUNT_INVESTOR = 'investor_commission';

    protected $fillable = [
        'company_id',
        'invoice_id',
        'sale_type',
        'pump_id',
        'attendant_transit',
        'discount_reason',
    ];

    protected $casts = [
        'company_id' => 'string',
        'invoice_id' => 'string',
        'pump_id' => 'string',
        'attendant_transit' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function pump(): BelongsTo
    {
        return $this->belongsTo(Pump::class);
    }

    /**
     * Check if this is a bulk sale (with discount).
     */
    public function isBulkSale(): bool
    {
        return $this->sale_type === self::TYPE_BULK;
    }

    /**
     * Check if this is a Parco card sale (goes to clearing account).
     */
    public function isParcoCardSale(): bool
    {
        return $this->sale_type === self::TYPE_PARCO_CARD;
    }

    /**
     * Check if this is an investor sale (with commission).
     */
    public function isInvestorSale(): bool
    {
        return $this->sale_type === self::TYPE_INVESTOR;
    }

    /**
     * Check if this is an amanat (trust deposit) purchase.
     */
    public function isAmanatPurchase(): bool
    {
        return $this->sale_type === self::TYPE_AMANAT;
    }

    /**
     * Check if cash is still with attendant (not yet handed over).
     */
    public function isInAttendantTransit(): bool
    {
        return $this->attendant_transit;
    }

    /**
     * Mark as no longer in attendant transit (cash handed over).
     */
    public function markHandedOver(): void
    {
        $this->attendant_transit = false;
        $this->save();
    }

    public static function getSaleTypes(): array
    {
        return [
            self::TYPE_RETAIL,
            self::TYPE_BULK,
            self::TYPE_CREDIT,
            self::TYPE_AMANAT,
            self::TYPE_INVESTOR,
            self::TYPE_PARCO_CARD,
        ];
    }

    public static function getDiscountReasons(): array
    {
        return [
            self::DISCOUNT_BULK,
            self::DISCOUNT_INVESTOR,
        ];
    }
}
