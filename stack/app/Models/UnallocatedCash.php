<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class UnallocatedCash extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $fillable = [
        'payment_id',
        'customer_id',
        'company_id',
        'amount',
        'currency',
        'status',
        'allocated_amount',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocated_amount' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'available',
        'allocated_amount' => 0,
    ];

    /**
     * Status constants
     */
    const STATUS_AVAILABLE = 'available';
    const STATUS_PARTIALLY_ALLOCATED = 'partially_allocated';
    const STATUS_FULLY_ALLOCATED = 'fully_allocated';
    const STATUS_EXPIRED = 'expired';

    /**
     * Boot the model and apply company scope.
     */
    protected static function booted()
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (app()->has('current_company')) {
                $builder->where('company_id', app('current_company'));
            }
        });
    }

    /**
     * Get the payment that created this unallocated cash.
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the customer who owns this unallocated cash.
     */
    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }

    /**
     * Get the company that owns this unallocated cash.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get allocations made from this unallocated cash.
     */
    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'unallocated_cash_id');
    }

    /**
     * Scope a query to only include available unallocated cash.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    /**
     * Scope a query to only include unallocated cash for a specific customer.
     */
    public function scopeForCustomer(Builder $query, string $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope a query to only include unallocated cash in a specific currency.
     */
    public function scopeInCurrency(Builder $query, string $currency): Builder
    {
        return $query->where('currency', $currency);
    }

    /**
     * Get the remaining available amount.
     */
    public function getRemainingAmountAttribute(): float
    {
        return $this->amount - $this->allocated_amount;
    }

    /**
     * Check if the unallocated cash is fully allocated.
     */
    public function isFullyAllocated(): bool
    {
        return $this->remaining_amount <= 0;
    }

    /**
     * Check if the unallocated cash is partially allocated.
     */
    public function isPartiallyAllocated(): bool
    {
        return $this->allocated_amount > 0 && !$this->isFullyAllocated();
    }

    /**
     * Allocate a portion of this unallocated cash.
     */
    public function allocate(float $amount): bool
    {
        if ($amount <= 0 || $amount > $this->remaining_amount) {
            return false;
        }

        $this->allocated_amount += $amount;
        $this->updateStatus();
        $this->save();

        return true;
    }

    /**
     * Update the status based on allocation amount.
     */
    private function updateStatus(): void
    {
        if ($this->isFullyAllocated()) {
            $this->status = self::STATUS_FULLY_ALLOCATED;
        } elseif ($this->isPartiallyAllocated()) {
            $this->status = self::STATUS_PARTIALLY_ALLOCATED;
        } else {
            $this->status = self::STATUS_AVAILABLE;
        }
    }

    /**
     * Create unallocated cash from a payment.
     */
    public static function createFromPayment(Payment $payment, float $amount): self
    {
        return static::create([
            'payment_id' => $payment->id,
            'customer_id' => $payment->customer_id,
            'company_id' => $payment->company_id,
            'amount' => $amount,
            'currency' => $payment->currency,
            'status' => self::STATUS_AVAILABLE,
            'notes' => 'Unallocated cash from payment overage',
            'metadata' => [
                'payment_number' => $payment->payment_number,
                'payment_date' => $payment->payment_date,
                'payment_method' => $payment->payment_method,
                'source' => 'payment_overage',
            ],
        ]);
    }

    /**
     * Get total available unallocated cash for a customer.
     */
    public static function getTotalAvailableForCustomer(string $customerId, string $currency = null): float
    {
        $query = static::available()->forCustomer($customerId);
        
        if ($currency) {
            $query->inCurrency($currency);
        }

        return $query->sum('amount') - $query->sum('allocated_amount');
    }

    /**
     * Find available unallocated cash for allocation.
     */
    public static function findAvailableForAllocation(
        string $customerId, 
        float $amount, 
        string $currency
    ): \Illuminate\Support\Collection {
        return static::available()
            ->forCustomer($customerId)
            ->inCurrency($currency)
            ->whereRaw('(amount - allocated_amount) >= ?', [0.01]) // Has remaining amount
            ->orderBy('created_at', 'asc') // FIFO principle
            ->get();
    }
}