<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentAllocation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'invoicing.payment_allocations';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'payment_id',
        'invoice_id',
        'allocated_amount',
        'allocation_date',
        'allocation_method',
        'allocation_strategy',
        'notes',
        'reversed_at',
        'reversal_reason',
        'reversed_by_user_id',
        'created_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allocation_date' => 'datetime',
            'allocated_amount' => 'decimal:2',
            'reversed_at' => 'datetime',
            'company_id' => 'string',
            'payment_id' => 'string',
            'invoice_id' => 'string',
            'created_by_user_id' => 'string',
            'reversed_by_user_id' => 'string',
        ];
    }

    /**
     * Get the company that owns the allocation.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the payment for the allocation.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the invoice for the allocation.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the user who created the allocation.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the user who reversed the allocation.
     */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    /**
     * Get the user who reversed the allocation (alias for reversedByUser).
     */
    public function reversedByUser(): BelongsTo
    {
        return $this->reverser();
    }

    /**
     * Check if the allocation is reversed.
     */
    public function getIsReversedAttribute(): bool
    {
        return !is_null($this->reversed_at);
    }

    /**
     * Scope a query to only include active allocations.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('reversed_at');
    }

    /**
     * Scope a query to only include reversed allocations.
     */
    public function scopeReversed($query)
    {
        return $query->whereNotNull('reversed_at');
    }

    /**
     * Scope a query to only include allocations with a specific method.
     */
    public function scopeWithMethod($query, string $method)
    {
        return $query->where('allocation_method', $method);
    }

    /**
     * Scope a query to only include allocations with a specific strategy.
     */
    public function scopeWithStrategy($query, string $strategy)
    {
        return $query->where('allocation_strategy', $strategy);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('allocation_date', [$startDate, $endDate]);
    }

    /**
     * Check if the allocation is active (not reversed).
     */
    public function getIsActiveAttribute(): bool
    {
        return is_null($this->reversed_at);
    }

    /**
     * Reverse the allocation.
     */
    public function reverse(string $reason, ?User $user = null): void
    {
        $this->reversed_at = now();
        $this->reversal_reason = $reason;
        $this->reversed_by_user_id = $user?->id ?? auth()->id();
        $this->save();

        // Update invoice balance
        $this->invoice->calculateTotals();

        // Log the reversal
        activity()
            ->causedBy($user ?? auth()->user())
            ->performedOn($this)
            ->withProperties([
                'reason' => $reason,
                'amount' => $this->allocated_amount,
                'payment_id' => $this->payment_id,
                'invoice_id' => $this->invoice_id,
            ])
            ->log('payment_allocation_reversed');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\PaymentAllocationFactory::new();
    }
}