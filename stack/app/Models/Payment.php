<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.payments';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Guarded attributes.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'reconciled' => 'boolean',
        'reconciled_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected $dates = [
        'payment_date',
        'reconciled_date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // Constants for status
    const STATUS_PENDING = 'pending';

    const STATUS_COMPLETED = 'completed';

    const STATUS_FAILED = 'failed';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_REVERSED = 'reversed';

    // Constants for payment methods
    const METHOD_CASH = 'cash';

    const METHOD_BANK_TRANSFER = 'bank_transfer';

    const METHOD_CARD = 'card';

    const METHOD_CHEQUE = 'cheque';

    const METHOD_OTHER = 'other';

    /**
     * Get the allocations for this payment.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id')
            ->whereNull('reversed_at');
    }

    /**
     * Get all allocations including reversed ones.
     */
    public function allAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    /**
     * Get the company that owns this payment.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the customer that made this payment.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the user who created this payment.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the batch this payment belongs to.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class, 'batch_id');
    }

    /**
     * Get the reversal record for this payment.
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(PaymentReversal::class, 'payment_id');
    }

    /**
     * Polymorphic owner of the payment.
     */
    public function paymentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the total allocated amount.
     */
    public function getTotalAllocatedAttribute(): float
    {
        return $this->allocations()->sum('allocated_amount');
    }

    /**
     * Get the remaining amount.
     */
    public function getRemainingAmountAttribute(): float
    {
        return $this->amount - $this->total_allocated;
    }

    /**
     * Check if the payment is fully allocated.
     */
    public function getIsFullyAllocatedAttribute(): bool
    {
        return $this->remaining_amount <= 0;
    }

    /**
     * Get payment method label.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            self::METHOD_CASH => 'Cash',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_CARD => 'Card',
            self::METHOD_CHEQUE => 'Cheque',
            self::METHOD_OTHER => 'Other',
            default => $this->payment_method,
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REVERSED => 'Reversed',
            default => $this->status,
        };
    }

    /**
     * Scope payments by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope payments by customer.
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope payments by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope payments by payment method.
     */
    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope payments by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate = null)
    {
        $query->where('payment_date', '>=', $startDate);

        if ($endDate) {
            $query->where('payment_date', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope unreconciled payments.
     */
    public function scopeUnreconciled($query)
    {
        return $query->where('reconciled', false);
    }

    /**
     * Scope reconciled payments.
     */
    public function scopeReconciled($query)
    {
        return $query->where('reconciled', true);
    }

    /**
     * Scope payments that can be reversed.
     */
    public function scopeReversible($query)
    {
        return $query->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_FAILED]);
    }

    /**
     * Check if the payment can be reversed.
     */
    public function canBeReversed(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED]) &&
               ! $this->reversal()->exists();
    }

    /**
     * Check if the payment has been reversed.
     */
    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED || $this->reversal()->exists();
    }

    /**
     * Get allocation summary.
     */
    public function getAllocationSummaryAttribute(): array
    {
        $allocations = $this->allocations;
        $totalAllocated = $allocations->sum('allocated_amount');

        return [
            'total_allocated' => $totalAllocated,
            'remaining_amount' => $this->amount - $totalAllocated,
            'is_fully_allocated' => $totalAllocated >= $this->amount,
            'allocation_count' => $allocations->count(),
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function ($payment) {
            if (! $payment->id) {
                $payment->id = Str::uuid();
            }

            if (! $payment->created_by_user_id) {
                $payment->created_by_user_id = auth()->id();
            }
        });

        static::updating(function ($payment) {
            // Prevent status changes for reversed payments
            if ($payment->isDirty('status') && $payment->getOriginal('status') === self::STATUS_REVERSED) {
                throw new \InvalidArgumentException('Cannot change status of a reversed payment');
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];
}
