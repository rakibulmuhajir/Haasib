<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.payments';
    protected $primaryKey = 'payment_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'payment_id',
        'company_id',
        'payment_number',
        'payment_type',
        'entity_type',
        'entity_id',
        'bank_account_id',
        'payment_method',
        'payment_date',
        'amount',
        'currency_id',
        'exchange_rate',
        'reference_number',
        'check_number',
        'bank_txn_id',
        'status',
        'reconciled',
        'reconciled_date',
        'notes',
        'metadata',
        'idempotency_key',
        'created_by',
        'updated_by',
        'reconciled_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'reconciled_date' => 'datetime',
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:10',
        'reconciled' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'payment_type' => 'customer_payment',
        'entity_type' => 'customer',
        'status' => 'pending',
        'reconciled' => false,
        'exchange_rate' => 1.0,
    ];

    /**
     * Boot the model and set up RLS context.
     */
    protected static function boot()
    {
        parent::boot();

        // Set company context for all queries
        static::addGlobalScope('company', function ($builder) {
            if (app()->runningInConsole() && !app()->environment('testing')) {
                // In console commands, we need to set the context explicitly
                $builder->whereRaw('company_id = current_setting(\'app.current_company\', true)::uuid');
            }
        });
    }

    /**
     * Get the company that owns the payment.
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    /**
     * Get the allocations for this payment.
     */
    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id', 'payment_id')
            ->where('status', 'active'); // Only active allocations
    }

    /**
     * Get all allocations including inactive ones.
     */
    public function allAllocations()
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id', 'payment_id');
    }

    /**
     * Get the currency for this payment.
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'id');
    }

    /**
     * Get the entity (customer/vendor) that made this payment.
     */
    public function entity()
    {
        if ($this->entity_type === 'customer') {
            return $this->belongsTo(Customer::class, 'entity_id', 'customer_id');
        } elseif ($this->entity_type === 'vendor') {
            return $this->belongsTo(Vendor::class, 'entity_id', 'vendor_id');
        }
        
        return null;
    }

    /**
     * Get the user who created this payment.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * Get the total allocated amount.
     */
    public function getTotalAllocatedAttribute(): float
    {
        return $this->allocations()->sum('allocated_amount') ?? 0;
    }

    /**
     * Get the remaining unallocated amount.
     */
    public function getRemainingAmountAttribute(): float
    {
        return $this->amount - $this->total_allocated;
    }

    /**
     * Check if payment is fully allocated.
     */
    public function getIsFullyAllocatedAttribute(): bool
    {
        return $this->remaining_amount <= 0;
    }

    /**
     * Get the allocation summary.
     */
    public function getAllocationSummaryAttribute(): array
    {
        $allocations = $this->allocations()->with(['invoice'])->get();
        
        return [
            'total_allocated' => $this->total_allocated,
            'remaining_amount' => $this->remaining_amount,
            'is_fully_allocated' => $this->is_fully_allocated,
            'allocation_count' => $allocations->count(),
            'allocations' => $allocations->map(function ($allocation) {
                return [
                    'allocation_id' => $allocation->allocation_id,
                    'invoice_id' => $allocation->invoice_id,
                    'invoice_number' => $allocation->invoice?->invoice_number,
                    'allocated_amount' => $allocation->allocated_amount,
                    'allocation_date' => $allocation->allocation_date,
                    'allocation_method' => $allocation->metadata['allocation_method'] ?? 'manual',
                    'notes' => $allocation->notes,
                ];
            })->toArray(),
        ];
    }

    /**
     * Scope payments by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope payments by payment method.
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope payments by date range.
     */
    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    /**
     * Scope payments that need allocation.
     */
    public function scopeNeedingAllocation($query)
    {
        return $query->where('status', 'pending')
            ->whereRaw('amount > COALESCE((
                SELECT COALESCE(SUM(allocated_amount), 0)
                FROM acct.payment_allocations pa
                WHERE pa.payment_id = acct.payments.payment_id
                AND pa.status = \'active\'
            ), 0)');
    }

    /**
     * Scope reconciled payments.
     */
    public function scopeReconciled($query, bool $reconciled = true)
    {
        return $query->where('reconciled', $reconciled);
    }

    /**
     * Get invoices that can be allocated to this payment.
     */
    public function getAllocatableInvoicesAttribute()
    {
        if ($this->entity_type !== 'customer') {
            return collect();
        }

        return Invoice::where('customer_id', $this->entity_id)
            ->where('company_id', $this->company_id)
            ->where('status', 'posted')
            ->whereRaw('balance_due > 0')
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Set company context for this payment.
     */
    public function setCompanyContext(string $companyId): void
    {
        DB::statement("SET app.current_company = ?", [$companyId]);
    }

    /**
     * Get the formatted payment number.
     */
    public function getFormattedPaymentNumberAttribute(): string
    {
        return $this->payment_number;
    }

    /**
     * Check if payment can be allocated.
     */
    public function canBeAllocated(): bool
    {
        return in_array($this->status, ['pending']) && 
               $this->remaining_amount > 0;
    }

    /**
     * Check if payment can be reversed.
     */
    public function canBeReversed(): bool
    {
        return in_array($this->status, ['pending', 'completed']);
    }

    /**
     * Get payment method label.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match($this->payment_method) {
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'card' => 'Credit/Debit Card',
            'cheque' => 'Cheque',
            'other' => 'Other',
            default => ucfirst($this->payment_method),
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }
}