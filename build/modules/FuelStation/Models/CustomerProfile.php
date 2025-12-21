<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Modules\Accounting\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fuel Customer Profile - Fuel-specific customer data.
 *
 * Links 1:1 to acct.customers, keeping the core accounting module clean.
 * Contains fuel station specific fields like customer type flags,
 * amanat balance, and Pakistani ID.
 */
class CustomerProfile extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.customer_profiles';
    protected $keyType = 'string';
    public $incrementing = false;

    public const RELATIONSHIP_OWNER = 'owner';
    public const RELATIONSHIP_EMPLOYEE = 'employee';
    public const RELATIONSHIP_EXTERNAL = 'external';

    protected $fillable = [
        'company_id',
        'customer_id',
        'is_credit_customer',
        'is_amanat_holder',
        'is_investor',
        'relationship',
        'cnic',
        'amanat_balance',
    ];

    protected $casts = [
        'company_id' => 'string',
        'customer_id' => 'string',
        'is_credit_customer' => 'boolean',
        'is_amanat_holder' => 'boolean',
        'is_investor' => 'boolean',
        'amanat_balance' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Check if this customer has any fuel-related flags set.
     */
    public function hasAnyFuelRole(): bool
    {
        return $this->is_credit_customer || $this->is_amanat_holder || $this->is_investor;
    }

    /**
     * Get or create a fuel profile for a customer.
     */
    public static function getOrCreateForCustomer(string $companyId, string $customerId): self
    {
        return static::firstOrCreate(
            ['company_id' => $companyId, 'customer_id' => $customerId],
            [
                'is_credit_customer' => false,
                'is_amanat_holder' => false,
                'is_investor' => false,
                'amanat_balance' => 0,
            ]
        );
    }

    /**
     * Adjust amanat balance (positive for deposits, negative for withdrawals/purchases).
     */
    public function adjustAmanatBalance(float $amount): void
    {
        $this->amanat_balance += $amount;
        $this->save();
    }

    public static function getRelationships(): array
    {
        return [
            self::RELATIONSHIP_OWNER,
            self::RELATIONSHIP_EMPLOYEE,
            self::RELATIONSHIP_EXTERNAL,
        ];
    }
}
