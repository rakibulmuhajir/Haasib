<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fuel Station Settings - Universal configuration per company
 *
 * Stores all configurable options that make the fuel station module
 * adaptable to any gas station in Pakistan (PSO, Shell, etc.)
 */
class StationSettings extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.station_settings';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Supported fuel vendors in Pakistan
     */
    public const VENDORS = [
        'parco' => 'PARCO',
        'pso' => 'PSO (Pakistan State Oil)',
        'shell' => 'Shell Pakistan',
        'total' => 'Total PARCO',
        'caltex' => 'Caltex (Chevron)',
        'attock' => 'Attock Petroleum',
        'hascol' => 'Hascol Petroleum',
        'byco' => 'Byco Petroleum',
        'go' => 'GO Petroleum',
        'other' => 'Other / Independent',
    ];

    /**
     * Default payment channel templates
     */
    public const DEFAULT_PAYMENT_CHANNELS = [
        [
            'code' => 'cash',
            'label' => 'Cash',
            'type' => 'cash',
            'enabled' => true,
            'bank_account_id' => null,
            'clearing_account_id' => null,
        ],
        [
            'code' => 'bank_transfer',
            'label' => 'Bank Transfer',
            'type' => 'bank_transfer',
            'enabled' => true,
            'bank_account_id' => null, // Set during onboarding
            'clearing_account_id' => null,
        ],
        [
            'code' => 'card_pos',
            'label' => 'Card Swipe (POS)',
            'type' => 'card_pos',
            'enabled' => true,
            'bank_account_id' => null, // POS settlement bank
            'clearing_account_id' => null, // Card clearing account
        ],
        [
            'code' => 'fuel_card',
            'label' => 'Fuel Card', // Label changes based on vendor
            'type' => 'fuel_card',
            'enabled' => true,
            'bank_account_id' => null,
            'clearing_account_id' => null, // Fuel card clearing
        ],
        [
            'code' => 'easypaisa',
            'label' => 'Easypaisa',
            'type' => 'mobile_wallet',
            'enabled' => false,
            'bank_account_id' => null,
            'clearing_account_id' => null,
        ],
        [
            'code' => 'jazzcash',
            'label' => 'JazzCash',
            'type' => 'mobile_wallet',
            'enabled' => false,
            'bank_account_id' => null,
            'clearing_account_id' => null,
        ],
    ];

    protected $fillable = [
        'company_id',
        'fuel_vendor',
        'has_partners',
        'has_amanat',
        'has_lubricant_sales',
        'has_investors',
        'dual_meter_readings',
        'track_attendant_handovers',
        'payment_channels',
        'cash_account_id',
        'fuel_sales_account_id',
        'fuel_cogs_account_id',
        'fuel_inventory_account_id',
        'cash_over_short_account_id',
        'partner_drawings_account_id',
        'employee_advances_account_id',
        'operating_bank_account_id',
        'fuel_card_clearing_account_id',
        'card_pos_clearing_account_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'has_partners' => 'boolean',
        'has_amanat' => 'boolean',
        'has_lubricant_sales' => 'boolean',
        'has_investors' => 'boolean',
        'dual_meter_readings' => 'boolean',
        'track_attendant_handovers' => 'boolean',
        'payment_channels' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    public function fuelSalesAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'fuel_sales_account_id');
    }

    public function fuelCogsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'fuel_cogs_account_id');
    }

    public function fuelInventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'fuel_inventory_account_id');
    }

    public function operatingBankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'operating_bank_account_id');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get the vendor display name
     */
    public function getVendorNameAttribute(): string
    {
        return self::VENDORS[$this->fuel_vendor] ?? 'Unknown';
    }

    /**
     * Get the fuel card label based on vendor
     */
    public function getFuelCardLabelAttribute(): string
    {
        return match ($this->fuel_vendor) {
            'parco' => 'Vendor Card',
            'pso' => 'PSO Card',
            'shell' => 'Shell Card',
            'total' => 'Total Card',
            'caltex' => 'Caltex Card',
            'attock' => 'APL Card',
            'hascol' => 'Hascol Card',
            'byco' => 'Byco Card',
            'go' => 'GO Card',
            default => 'Fuel Card',
        };
    }

    /**
     * Get enabled payment channels only
     */
    public function getEnabledPaymentChannelsAttribute(): array
    {
        $channels = $this->payment_channels ?? [];

        return array_values(array_filter($channels, fn ($ch) => $ch['enabled'] ?? false));
    }

    /**
     * Get payment channel by code
     */
    public function getPaymentChannel(string $code): ?array
    {
        $channels = $this->payment_channels ?? [];

        foreach ($channels as $channel) {
            if (($channel['code'] ?? '') === $code) {
                return $channel;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Static Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get or create settings for a company with defaults
     */
    public static function forCompany(string $companyId): self
    {
        return self::firstOrCreate(
            ['company_id' => $companyId],
            [
                'fuel_vendor' => 'parco',
                'has_partners' => true,
                'has_amanat' => true,
                'has_lubricant_sales' => true,
                'has_investors' => false,
                'dual_meter_readings' => false,
                'track_attendant_handovers' => false,
                'payment_channels' => self::DEFAULT_PAYMENT_CHANNELS,
            ]
        );
    }

    /**
     * Get default payment channels with vendor-specific fuel card label
     */
    public static function getDefaultChannelsForVendor(string $vendor): array
    {
        $channels = self::DEFAULT_PAYMENT_CHANNELS;

        // Update fuel card label based on vendor
        $fuelCardLabel = match ($vendor) {
            'parco' => 'Vendor Card',
            'pso' => 'PSO Card',
            'shell' => 'Shell Card',
            'total' => 'Total Card',
            'caltex' => 'Caltex Card',
            'attock' => 'APL Card',
            'hascol' => 'Hascol Card',
            'byco' => 'Byco Card',
            'go' => 'GO Card',
            default => 'Fuel Card',
        };

        foreach ($channels as &$channel) {
            if ($channel['code'] === 'fuel_card') {
                $channel['label'] = $fuelCardLabel;
            }
        }

        return $channels;
    }

    /**
     * Build account mapping array for DailyCloseService
     */
    public function getAccountMappings(): array
    {
        return [
            'cash_on_hand' => $this->cash_account_id,
            'fuel_sales' => $this->fuel_sales_account_id,
            'fuel_cogs' => $this->fuel_cogs_account_id,
            'fuel_inventory' => $this->fuel_inventory_account_id,
            'cash_over_short' => $this->cash_over_short_account_id,
            'partner_drawings' => $this->partner_drawings_account_id,
            'employee_advances' => $this->employee_advances_account_id,
            'operating_bank' => $this->operating_bank_account_id,
            'fuel_card_clearing' => $this->fuel_card_clearing_account_id,
            'card_pos_clearing' => $this->card_pos_clearing_account_id,
        ];
    }
}
