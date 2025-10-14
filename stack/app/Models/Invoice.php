<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'invoicing.invoices';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_number',
        'order_number',
        'issue_date',
        'due_date',
        'status',
        'currency',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'balance_due',
        'notes',
        'terms',
        'payment_status',
        'sent_at',
        'paid_at',
        'overdue_at',
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
            'issue_date' => 'date',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'overdue_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'company_id' => 'string',
            'customer_id' => 'string',
            'created_by_user_id' => 'string',
        ];
    }

    /**
     * The attributes that should be appended to the model.
     *
     * @var list<string>
     */
    protected $appends = [
        'is_overdue',
        'days_overdue',
    ];

    /**
     * Get the company that owns the invoice.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the customer for the invoice.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who created the invoice.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the line items for the invoice.
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }

    /**
     * Get the payments for the invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the payment allocations for this invoice.
     */
    public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Get the active payment allocations (not reversed).
     */
    public function activePaymentAllocations()
    {
        return $this->paymentAllocations()->active();
    }

    /**
     * Get the total allocated amount from payments.
     */
    public function getTotalAllocatedAttribute(): float
    {
        return $this->activePaymentAllocations()->sum('allocated_amount');
    }

    /**
     * Get the payments that have been allocated to this invoice.
     */
    public function allocatedPayments()
    {
        return $this->belongsToMany(Payment::class, 'invoicing.payment_allocations', 'invoice_id', 'payment_id')
            ->withPivot(['allocated_amount', 'allocation_date', 'allocation_method', 'allocation_strategy', 'notes'])
            ->wherePivotNull('reversed_at')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include invoices with a specific status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include overdue invoices.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled');
    }

    /**
     * Scope a query to only include draft invoices.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include sent invoices.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope a query to only include paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Check if the invoice is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date->isPast() &&
            ! in_array($this->status, ['paid', 'cancelled']);
    }

    /**
     * Get the number of days the invoice is overdue.
     */
    public function getDaysOverdueAttribute(): int
    {
        if (! $this->is_overdue) {
            return 0;
        }

        return $this->due_date->diffInDays(now());
    }

    /**
     * Calculate and update the invoice totals.
     */
    public function calculateTotals(): void
    {
        $subtotal = $this->lineItems()->sum('total');
        $taxAmount = $this->lineItems()->sum('tax_amount');
        $discountAmount = $this->lineItems()->sum('discount_amount');
        $totalAmount = $subtotal + $taxAmount - $discountAmount;
        $allocatedAmount = $this->total_allocated;

        $this->subtotal = $subtotal;
        $this->tax_amount = $taxAmount;
        $this->discount_amount = $discountAmount;
        $this->total_amount = $totalAmount;
        $this->balance_due = $totalAmount - $allocatedAmount;

        // Update payment status based on balance
        if ($this->balance_due <= 0) {
            $this->payment_status = 'paid';
            $this->status = 'paid';
            $this->paid_at = now();
        } elseif ($allocatedAmount > 0) {
            $this->payment_status = 'partially_paid';
        } else {
            $this->payment_status = 'unpaid';
        }

        $this->save();
    }

    /**
     * Mark the invoice as sent.
     */
    public function markAsSent(): void
    {
        if ($this->status === 'draft') {
            $this->status = 'sent';
            $this->sent_at = now();
            $this->save();
        }
    }

    /**
     * Mark the invoice as paid.
     */
    public function markAsPaid(): void
    {
        if ($this->balance_due <= 0) {
            $this->status = 'paid';
            $this->payment_status = 'paid';
            $this->paid_at = now();
            $this->save();
        } else {
            $this->payment_status = 'partial';
            $this->save();
        }
    }

    /**
     * Cancel the invoice.
     */
    public function cancel(): void
    {
        $this->status = 'cancelled';
        $this->save();
    }

    /**
     * Generate a unique invoice number.
     */
    public static function generateInvoiceNumber(string $companyId): string
    {
        $prefix = 'INV';
        $year = now()->format('Y');
        $sequence = static::where('company_id', $companyId)
            ->whereYear('created_at', $year)
            ->withTrashed()
            ->count() + 1;

        return "{$prefix}-{$year}-{$sequence}";
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\InvoiceFactory::new();
    }
}
