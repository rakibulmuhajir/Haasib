<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentReversal extends Model
{
    use HasFactory;

    protected $table = 'invoicing.payment_reversals';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'payment_id',
        'company_id',
        'reason',
        'reversed_amount',
        'reversal_method',
        'initiated_by_user_id',
        'initiated_at',
        'settled_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'reversed_amount' => 'decimal:2',
        'initiated_at' => 'datetime',
        'settled_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $dates = [
        'initiated_at',
        'settled_at',
        'created_at',
        'updated_at',
    ];

    // Constants for status
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_REJECTED = 'rejected';

    // Constants for reversal methods
    const METHOD_VOID = 'void';
    const METHOD_REFUND = 'refund';
    const METHOD_CHARGEBACK = 'chargeback';

    /**
     * Get the payment that was reversed.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    /**
     * Get the company that owns this reversal.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the user who initiated this reversal.
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    /**
     * Get reversal method label.
     */
    public function getReversalMethodLabelAttribute(): string
    {
        return match($this->reversal_method) {
            self::METHOD_VOID => 'Void',
            self::METHOD_REFUND => 'Refund',
            self::METHOD_CHARGEBACK => 'Chargeback',
            default => $this->reversal_method,
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_REJECTED => 'Rejected',
            default => $this->status,
        };
    }

    /**
     * Scope reversals by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope reversals by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope pending reversals.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope completed reversals.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope rejected reversals.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope reversals by method.
     */
    public function scopeByMethod($query, $method)
    {
        return $query->where('reversal_method', $method);
    }

    /**
     * Scope reversals by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate = null)
    {
        $query->where('initiated_at', '>=', $startDate);
        
        if ($endDate) {
            $query->where('initiated_at', '<=', $endDate);
        }
        
        return $query;
    }

    /**
     * Check if the reversal is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the reversal is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the reversal is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Mark the reversal as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'settled_at' => now(),
        ]);
    }

    /**
     * Mark the reversal as rejected.
     */
    public function markAsRejected(string $rejectionReason = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'settled_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], [
                'rejection_reason' => $rejectionReason,
            ]),
        ]);
    }

    /**
     * Get the duration from initiation to settlement.
     */
    public function getProcessingDurationAttribute(): ?int
    {
        if (!$this->initiated_at || !$this->settled_at) {
            return null;
        }

        return $this->initiated_at->diffInSeconds($this->settled_at);
    }

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function ($reversal) {
            if (!$reversal->id) {
                $reversal->id = Str::uuid();
            }
            
            if (!$reversal->initiated_at) {
                $reversal->initiated_at = now();
            }
            
            if (!$reversal->initiated_by_user_id) {
                $reversal->initiated_by_user_id = auth()->id();
            }
        });

        static::created(function ($reversal) {
            // Update payment status to reversed
            $reversal->payment->update(['status' => Payment::STATUS_REVERSED]);
        });
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];
}