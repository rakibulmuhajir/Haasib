<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Amanat Transaction - Trust deposit movements.
 *
 * Tech Debt Note:
 * Amanat exists in many Pakistani businesses (not just fuel stations).
 * Better long-term design:
 * - Core: acct.deposits (customer_id, balance, type='amanat')
 * - Core: acct.deposit_movements (deposit/withdraw/apply-to-invoice)
 * - Fuel module: just adds rule to allow applying deposits to fuel invoices
 *
 * Keeping fuel.amanat_transactions is acceptable for MVP.
 */
class AmanatTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.amanat_transactions';
    protected $keyType = 'string';
    public $incrementing = false;

    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_WITHDRAWAL = 'withdrawal';
    public const TYPE_FUEL_PURCHASE = 'fuel_purchase';

    protected $fillable = [
        'company_id',
        'customer_id',
        'transaction_type',
        'amount',
        'fuel_item_id',
        'fuel_quantity',
        'reference',
        'notes',
        'recorded_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'customer_id' => 'string',
        'amount' => 'decimal:2',
        'fuel_item_id' => 'string',
        'fuel_quantity' => 'decimal:2',
        'recorded_by_user_id' => 'string',
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

    public function fuelItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'fuel_item_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * Check if this is a deposit transaction.
     */
    public function isDeposit(): bool
    {
        return $this->transaction_type === self::TYPE_DEPOSIT;
    }

    /**
     * Check if this is a withdrawal transaction.
     */
    public function isWithdrawal(): bool
    {
        return $this->transaction_type === self::TYPE_WITHDRAWAL;
    }

    /**
     * Check if this is a fuel purchase transaction.
     */
    public function isFuelPurchase(): bool
    {
        return $this->transaction_type === self::TYPE_FUEL_PURCHASE;
    }

    /**
     * Get the signed amount (positive for deposits, negative for others).
     */
    public function getSignedAmountAttribute(): float
    {
        return $this->isDeposit() ? $this->amount : -$this->amount;
    }

    public static function getTransactionTypes(): array
    {
        return [
            self::TYPE_DEPOSIT,
            self::TYPE_WITHDRAWAL,
            self::TYPE_FUEL_PURCHASE,
        ];
    }
}
