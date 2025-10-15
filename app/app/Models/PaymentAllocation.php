<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentAllocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.payment_allocations';
    protected $primaryKey = 'allocation_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'allocation_id',
        'payment_id',
        'invoice_id',
        'allocated_amount',
        'status',
        'allocation_date',
        'notes',
        'metadata',
        'idempotency_key',
    ];

    protected $casts = [
        'allocation_date' => 'datetime',
        'allocated_amount' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Boot the model and set up RLS context.
     */
    protected static function boot()
    {
        parent::boot();

        // RLS is enforced at the database level through payment relationship
        // No need for additional global scopes here
    }

    /**
     * Get the payment for this allocation.
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'payment_id');
    }

    /**
     * Get the invoice for this allocation.
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id');
    }

    /**
     * Get the company through the payment relationship.
     */
    public function company()
    {
        return $this->hasOneThrough(Company::class, Payment::class, 'payment_id', 'id', 'payment_id', 'company_id');
    }

    /**
     * Scope allocations by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope active allocations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope allocations by date range.
     */
    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('allocation_date', [$startDate, $endDate]);
    }

    /**
     * Scope allocations by allocation method.
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->whereJsonContains('metadata->allocation_method', $method);
    }

    /**
     * Scope allocations that can be reversed.
     */
    public function scopeReversible($query)
    {
        return $query->where('status', 'active')
            ->whereDoesntHave('reversals');
    }

    /**
     * Check if allocation is active.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if allocation is reversed.
     */
    public function getIsReversedAttribute(): bool
    {
        return $this->status === 'void';
    }

    /**
     * Get the allocation method.
     */
    public function getAllocationMethodAttribute(): string
    {
        return $this->metadata['allocation_method'] ?? 'manual';
    }

    /**
     * Get the user who created this allocation.
     */
    public function getCreatorAttribute(): ?User
    {
        $createdById = $this->metadata['created_by'] ?? null;
        
        if ($createdById) {
            return User::find($createdById);
        }
        
        return null;
    }

    /**
     * Get formatted allocated amount.
     */
    public function getFormattedAllocatedAmountAttribute(): string
    {
        return number_format($this->allocated_amount, 2);
    }

    /**
     * Reverse this allocation.
     */
    public function reverse(string $reason, ?User $reversedBy = null): self
    {
        if ($this->status !== 'active') {
            throw new \InvalidArgumentException('Cannot reverse non-active allocation');
        }

        $this->status = 'void';
        $this->metadata = array_merge($this->metadata ?? [], [
            'reversed_at' => now()->toISOString(),
            'reversed_by' => $reversedBy?->user_id,
            'reversal_reason' => $reason,
        ]);
        
        $this->save();

        return $this;
    }

    /**
     * Check if allocation can be reversed.
     */
    public function canBeReversed(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get the invoice balance at time of allocation.
     */
    public function getInvoiceBalanceAtAllocationAttribute(): float
    {
        // This would typically be stored in metadata at allocation time
        // For now, return current invoice balance
        return $this->invoice?->balance_due ?? 0;
    }

    /**
     * Get allocation summary for API responses.
     */
    public function getSummaryAttribute(): array
    {
        return [
            'allocation_id' => $this->allocation_id,
            'payment_id' => $this->payment_id,
            'payment_number' => $this->payment?->payment_number,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice?->invoice_number,
            'allocated_amount' => $this->allocated_amount,
            'allocation_method' => $this->allocation_method,
            'allocation_date' => $this->allocation_date?->format('Y-m-d H:i:s'),
            'notes' => $this->notes,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'is_reversed' => $this->is_reversed,
            'creator' => $this->creator?->name,
        ];
    }

    /**
     * Relationship to payment reversals (if implemented).
     */
    public function reversals()
    {
        // This would relate to a payment_reversals table if implemented
        // For now, return an empty relationship
        return $this->morphMany(PaymentReversal::class, 'reversible');
    }
}