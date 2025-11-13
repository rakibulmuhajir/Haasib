<?php

namespace App\Models\Acct;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditLogging;
use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Payment extends Model
{
    use HasFactory, HasUuids, BelongsToCompany, SoftDeletes, AuditLogging;

    protected $table = 'acct.payments';

    protected $fillable = [
        'company_id',
        'payment_number',
        'customer_id',
        'payment_type',
        'payment_method',
        'amount',
        'currency',
        'payment_date',
        'received_by',
        'status',
        'reference_number',
        'description',
        'notes',
        'payment_details',
        'bank_account_id',
        'processing_fee',
        'net_amount',
        'processed_at',
        'processed_by',
        'failure_reason',
        'parent_payment_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'payment_date' => 'date',
        'processed_at' => 'datetime',
        'payment_details' => 'array',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'failure_reason',
        'deleted_at',
    ];

    protected $appends = [
        'total_allocated',
        'remaining_amount',
        'is_fully_allocated',
        'is_partially_allocated',
        'is_unallocated',
        'allocation_status',
        'payment_method_label',
        'status_label',
        'can_be_reversed',
        'is_reversed',
    ];

    // UUID Configuration
    protected $keyType = 'string';
    public $incrementing = false;

    // === RELATIONSHIPS ===

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function parentPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'parent_payment_id');
    }

    public function childPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'parent_payment_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class, 'payment_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(PaymentReversal::class, 'payment_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class, 'batch_id');
    }

    // === CONSTANTS ===

    // Payment Types
    const TYPE_PAYMENT = 'payment';
    const TYPE_REFUND = 'refund';
    const TYPE_CREDIT_APPLICATION = 'credit_application';

    // Payment Methods
    const METHOD_CASH = 'cash';
    const METHOD_CHECK = 'check';
    const METHOD_CREDIT_CARD = 'credit_card';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_ONLINE = 'online';
    const METHOD_OTHER = 'other';

    // Statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_VOID = 'void';
    const STATUS_REFUNDED = 'refunded';

  // === SCOPES ===

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentMethod(Builder $query, string $method): Builder
    {
        return $query->where('payment_method', $method);
    }

    public function scopeByCustomer(Builder $query, string $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByDateRange(Builder $query, $startDate, $endDate = null): Builder
    {
        $query->where('payment_date', '>=', $startDate);

        if ($endDate) {
            $query->where('payment_date', '<=', $endDate);
        }

        return $query;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeReversible(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_FAILED]);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    // === BUSINESS LOGIC METHODS ===

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_COMPLETED]);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function canBeReversed(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED]) &&
               !$this->reversal()->exists() &&
               !$this->childPayments()->exists();
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REFUNDED || $this->reversal()->exists();
    }

    public function canBeAllocated(): bool
    {
        return $this->isCompleted() && $this->remaining_amount > 0;
    }

    public function getTotalAllocatedAttribute(): float
    {
        if ($this->relationLoaded('allocations')) {
            return (float) $this->allocations->sum('allocated_amount');
        }

        return (float) $this->allocations()->sum('allocated_amount');
    }

    public function getRemainingAmountAttribute(): float
    {
        return $this->net_amount - $this->total_allocated;
    }

    public function getIsFullyAllocatedAttribute(): bool
    {
        $epsilon = 0.01;
        return $this->remaining_amount <= $epsilon;
    }

    public function getIsPartiallyAllocatedAttribute(): bool
    {
        $epsilon = 0.01;
        return $this->total_allocated > $epsilon && !$this->is_fully_allocated;
    }

    public function getIsUnallocatedAttribute(): bool
    {
        $epsilon = 0.01;
        return $this->total_allocated <= $epsilon;
    }

    public function getAllocationStatusAttribute(): string
    {
        if ($this->is_fully_allocated) {
            return 'fully_allocated';
        } elseif ($this->is_partially_allocated) {
            return 'partially_allocated';
        }
        return 'unallocated';
    }

    public function getCanBeReversedAttribute(): bool
    {
        return $this->canBeReversed();
    }

    public function getIsReversedAttribute(): bool
    {
        return $this->isReversed();
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            self::METHOD_CASH => 'Cash',
            self::METHOD_CHECK => 'Check',
            self::METHOD_CREDIT_CARD => 'Credit Card',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_ONLINE => 'Online Payment',
            self::METHOD_OTHER => 'Other',
            default => ucfirst($this->payment_method),
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_VOID => 'Void',
            self::STATUS_REFUNDED => 'Refunded',
            default => ucfirst($this->status),
        };
    }

    public function markAsCompleted(): bool
    {
        $this->status = self::STATUS_COMPLETED;
        $this->processed_at = now();
        
        if (Auth::check()) {
            $this->processed_by = Auth::id();
        }

        return $this->save();
    }

    public function markAsFailed(string $reason): bool
    {
        $this->status = self::STATUS_FAILED;
        $this->failure_reason = $reason;
        $this->processed_at = now();
        
        if (Auth::check()) {
            $this->processed_by = Auth::id();
        }

        return $this->save();
    }

    public function void(): bool
    {
        $this->status = self::STATUS_VOID;
        return $this->save();
    }

    // === MUTATORS & ACCESSORS ===

    public function getPaymentNumberAttribute(): string
    {
        if (!isset($this->attributes['payment_number'])) {
            $this->attributes['payment_number'] = $this->generatePaymentNumber();
            $this->save();
        }

        return $this->attributes['payment_number'];
    }

    private function generatePaymentNumber(): string
    {
        $maxNumber = static::where('company_id', $this->company_id)
            ->whereNotNull('payment_number')
            ->max('payment_number');

        $nextNumber = (int)str_replace('PAY-', '', $maxNumber ?? 'PAY-000000') + 1;

        return 'PAY-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

  // === EVENTS ===

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (!$payment->payment_number) {
                $payment->payment_number = $payment->generatePaymentNumber();
            }

            if (Auth::check()) {
                $payment->received_by = Auth::id();
            }

            // Ensure net_amount is calculated if not set
            if (!isset($payment->net_amount)) {
                $payment->net_amount = $payment->amount - ($payment->processing_fee ?? 0);
            }
        });

        static::updating(function (Payment $payment) {
            // Prevent status changes for void payments
            if ($payment->isDirty('status') && $payment->getOriginal('status') === self::STATUS_VOID) {
                throw new \InvalidArgumentException('Cannot change status of a voided payment');
            }

            // Update net_amount if amount or processing_fee changed
            if ($payment->isDirty(['amount', 'processing_fee'])) {
                $payment->net_amount = $payment->amount - ($payment->processing_fee ?? 0);
            }
        });

        static::deleting(function (Payment $payment) {
            if ($payment->allocations()->count() > 0) {
                throw new \InvalidArgumentException('Cannot delete payment with existing allocations');
            }
        });
    }

    // === QUERY SCOPES ===

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeUnallocated(Builder $query): Builder
    {
        return $query->whereDoesntHave('allocations')
                    ->orWhereHas('allocations', function (Builder $q) {
                        $q->havingRaw('SUM(allocated_amount) < payments.net_amount');
                    });
    }

    public function scopePartiallyAllocated(Builder $query): Builder
    {
        return $query->whereHas('allocations', function (Builder $q) {
            $q->havingRaw('SUM(allocated_amount) > 0 AND SUM(allocated_amount) < payments.net_amount');
        });
    }

    public function scopeFullyAllocated(Builder $query): Builder
    {
        return $query->whereHas('allocations', function (Builder $q) {
            $q->havingRaw('SUM(allocated_amount) >= payments.net_amount');
        });
    }

    public function scopeWithAllocations(Builder $query): Builder
    {
        return $query->withCount(['allocations as allocations_count'])
                    ->withSum('allocations as total_allocated');
    }
}
