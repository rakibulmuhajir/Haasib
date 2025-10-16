<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentAllocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.payment_allocations';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'payment_id',
        'invoice_id',
        'allocated_amount',
        'original_amount',
        'discount_amount',
        'discount_percent',
        'allocation_date',
        'allocation_method',
        'allocation_strategy',
        'notes',
        'reversed_at',
        'reversal_reason',
        'reversed_by_user_id',
        'status',
        'created_by_user_id',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'allocation_date' => 'date',
        'reversed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $dates = [
        'allocation_date',
        'reversed_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // Constants for status
    const STATUS_ACTIVE = 'active';
    const STATUS_REVERSED = 'reversed';

    // Constants for allocation methods
    const METHOD_MANUAL = 'manual';
    const METHOD_AUTOMATIC = 'automatic';

    /**
     * Get the payment for this allocation.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    /**
     * Get the invoice for this allocation.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Get the company that owns this allocation.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the user who created this allocation.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the user who reversed this allocation.
     */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    /**
     * Get allocation method label.
     */
    public function getAllocationMethodLabelAttribute(): string
    {
        return match($this->allocation_method) {
            self::METHOD_MANUAL => 'Manual',
            self::METHOD_AUTOMATIC => 'Automatic',
            default => $this->allocation_method,
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_REVERSED => 'Reversed',
            default => $this->status,
        };
    }

    /**
     * Scope allocations by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope allocations by payment.
     */
    public function scopeForPayment($query, $paymentId)
    {
        return $query->where('payment_id', $paymentId);
    }

    /**
     * Scope allocations by invoice.
     */
    public function scopeForInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    /**
     * Scope active allocations (not reversed).
     */
    public function scopeActive($query)
    {
        return $query->whereNull('reversed_at')->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope reversed allocations.
     */
    public function scopeReversed($query)
    {
        return $query->whereNotNull('reversed_at')->orWhere('status', self::STATUS_REVERSED);
    }

    /**
     * Scope allocations by method.
     */
    public function scopeByMethod($query, $method)
    {
        return $query->where('allocation_method', $method);
    }

    /**
     * Scope allocations by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate = null)
    {
        $query->where('allocation_date', '>=', $startDate);
        
        if ($endDate) {
            $query->where('allocation_date', '<=', $endDate);
        }
        
        return $query;
    }

    /**
     * Check if the allocation is active.
     */
    public function isActive(): bool
    {
        return is_null($this->reversed_at) && $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the allocation has been reversed.
     */
    public function isReversed(): bool
    {
        return !is_null($this->reversed_at) || $this->status === self::STATUS_REVERSED;
    }

    /**
     * Check if the allocation can be reversed.
     */
    public function canBeReversed(): bool
    {
        return $this->isActive();
    }

    /**
     * Reverse the allocation.
     */
    public function reverse(string $reason, ?string $reverserUserId = null): void
    {
        $this->update([
            'reversed_at' => now(),
            'reversal_reason' => $reason,
            'reversed_by_user_id' => $reverserUserId ?? auth()->id(),
            'status' => self::STATUS_REVERSED,
        ]);
    }

    /**
     * Get the effective amount (after discount).
     */
    public function getEffectiveAmountAttribute(): float
    {
        return $this->allocated_amount - ($this->discount_amount ?? 0);
    }

    /**
     * Get the discount percentage as a formatted string.
     */
    public function getDiscountPercentFormattedAttribute(): string
    {
        return number_format($this->discount_percent, 2) . '%';
    }

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function ($allocation) {
            if (!$allocation->id) {
                $allocation->id = Str::uuid();
            }
            
            if (!$allocation->created_by_user_id) {
                $allocation->created_by_user_id = auth()->id();
            }
            
            if (!$allocation->status) {
                $allocation->status = self::STATUS_ACTIVE;
            }
            
            if (!$allocation->allocation_date) {
                $allocation->allocation_date = now();
            }
        });

        static::updating(function ($allocation) {
            // Prevent changes to reversed allocations
            if ($allocation->isReversed() && $allocation->isDirty('allocated_amount')) {
                throw new \InvalidArgumentException('Cannot modify a reversed allocation');
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];
}
