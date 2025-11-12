<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentAllocation extends Model
{
    use HasFactory;

    protected $table = 'acct.payment_allocations';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'payment_id',
        'invoice_id',
        'amount',
        'allocation_date',
        'allocation_method',
        'allocation_strategy',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'allocation_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $dates = [
        'allocation_date',
        'created_at',
        'updated_at',
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
     * Get allocation method label.
     */
    public function getAllocationMethodLabelAttribute(): string
    {
        return match ($this->allocation_method) {
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
        return match ($this->status) {
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
        return number_format($this->discount_percent, 2).'%';
    }

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function ($allocation) {
            if (! $allocation->id) {
                $allocation->id = Str::uuid();
            }

            if (! $allocation->created_by_user_id) {
                $allocation->created_by_user_id = auth()->id();
            }

            if (! $allocation->allocation_date) {
                $allocation->allocation_date = now();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];
}
