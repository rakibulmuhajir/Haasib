<?php

namespace App\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payments';

    protected $primaryKey = 'payment_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
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
        'created_by',
        'updated_by',
        'reconciled_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'payment_date' => 'date',
        'reconciled' => 'boolean',
        'reconciled_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'payment_type' => 'customer_payment',
        'entity_type' => 'customer',
        'status' => 'pending',
        'exchange_rate' => 1.0,
        'reconciled' => false,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->payment_id)) {
                $payment->payment_id = (string) \Illuminate\Support\Str::uuid();
            }
            if (! $payment->payment_number) {
                $payment->payment_number = $payment->generatePaymentNumber();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'payment_id';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        // This relationship doesn't work directly due to data type mismatch
        // Use getCustomerAttribute() method instead
        return $this->belongsTo(Customer::class);
    }

    public function getCustomerAttribute()
    {
        // Get customer through the first invoice's customer
        return $this->invoices->first()?->customer;
    }

    public function getCustomerIdAttribute()
    {
        // Get customer ID through the first invoice's customer
        return $this->invoices->first()?->customer_id;
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id', 'payment_id');
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'payment_allocations', 'payment_id', 'invoice_id')
            ->withPivot('allocated_amount as amount', 'created_at', 'updated_at');
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isPartiallyAllocated(): bool
    {
        return $this->getAllocatedAmount()->isGreaterThan(Money::of(0, $this->currency->code)) &&
               $this->getAllocatedAmount()->isLessThan($this->getAmount());
    }

    public function isFullyAllocated(): bool
    {
        return $this->getAllocatedAmount()->isEqualTo($this->getAmount());
    }

    public function isUnallocated(): bool
    {
        return $this->getAllocatedAmount()->isZero();
    }

    public function getAmount(): Money
    {
        return Money::of($this->amount, $this->currency->code);
    }

    public function getAllocatedAmount(): Money
    {
        $allocated = $this->allocations()->sum('allocated_amount');

        return Money::of($allocated, $this->currency->code);
    }

    public function getUnallocatedAmount(): Money
    {
        return $this->getAmount()->minus($this->getAllocatedAmount());
    }

    public function getAmountInCompanyCurrency(): Money
    {
        $company = $this->company;
        if ($this->currency->code === $company->base_currency) {
            return $this->getAmount();
        }

        return $this->getAmount()->multipliedBy($this->exchange_rate);
    }

    public function canBeAllocated(): bool
    {
        return $this->isCompleted() && ! $this->isFullyAllocated();
    }

    public function canBeVoided(): bool
    {
        return ! $this->isCancelled() && ! $this->isFailed();
    }

    public function canBeRefunded(): bool
    {
        return $this->isCompleted() && $this->getAllocatedAmount()->isGreaterThan(Money::of(0, $this->currency->code));
    }

    public function generatePaymentNumber(): string
    {
        $company = $this->company;
        $year = now()->year;
        $month = now()->format('m');
        $day = now()->format('d');

        $prefix = $company->settings['payment_prefix'] ?? 'PAY';
        $pattern = $company->settings['payment_number_pattern'] ?? '{prefix}-{year}{month}{day}-{sequence:4}';

        // For seeder purposes, use a simple sequential number
        $latestPayment = static::where('company_id', $company->id)
            ->whereDate('created_at', today())
            ->orderBy('payment_number', 'desc')
            ->first();

        // Extract sequence number from payment_number instead of using UUID
        $sequence = 1;
        if ($latestPayment && $latestPayment->payment_number) {
            // Extract the sequence number from the payment_number
            // Assuming format like PAY-20241226-0001
            $parts = explode('-', $latestPayment->payment_number);
            $lastSequence = end($parts);
            if (is_numeric($lastSequence)) {
                $sequence = (int) $lastSequence + 1;
            }
        }

        return str_replace(
            ['{prefix}', '{year}', '{month}', '{day}', '{sequence:4}', '{sequence:5}', '{sequence:6}'],
            [$prefix, $year, $month, $day, str_pad($sequence, 4, '0', STR_PAD_LEFT), str_pad($sequence, 5, '0', STR_PAD_LEFT), str_pad($sequence, 6, '0', STR_PAD_LEFT)],
            $pattern
        );
    }

    public function markAsCompleted(?string $processorReference = null): void
    {
        $this->status = 'completed';
        $this->metadata = array_merge($this->metadata ?? [], [
            'completed_at' => now()->toISOString(),
            'processor_reference' => $processorReference,
        ]);
        $this->save();
    }

    public function markAsFailed(?string $reason = null): void
    {
        $this->status = 'failed';
        $this->metadata = array_merge($this->metadata ?? [], [
            'failed_at' => now()->toISOString(),
            'failure_reason' => $reason,
        ]);
        $this->save();
    }

    public function markAsCancelled(?string $reason = null): void
    {
        $this->status = 'cancelled';
        $this->metadata = array_merge($this->metadata ?? [], [
            'cancelled_at' => now()->toISOString(),
            'cancellation_reason' => $reason,
        ]);
        $this->save();
    }

    public function allocateToInvoice(Invoice $invoice, Money $amount, ?string $notes = null): PaymentAllocation
    {
        if (! $this->canBeAllocated()) {
            throw new \InvalidArgumentException('Payment cannot be allocated');
        }

        $unallocatedAmount = $this->getUnallocatedAmount();
        if ($amount->isGreaterThan($unallocatedAmount)) {
            throw new \InvalidArgumentException('Allocation amount exceeds unallocated amount');
        }

        $invoiceBalance = Money::of($invoice->balance_due, $invoice->currency->code);
        if ($amount->isGreaterThan($invoiceBalance)) {
            throw new \InvalidArgumentException('Allocation amount exceeds invoice balance due');
        }

        return PaymentAllocation::create([
            'payment_id' => $this->getKey(),
            'invoice_id' => $invoice->getKey(),
            'allocated_amount' => $amount->getAmount()->toFloat(),
            'notes' => $notes,
        ]);
    }

    public function autoAllocate(): array
    {
        if (! $this->canBeAllocated()) {
            return [];
        }

        $allocations = [];
        $unallocatedAmount = $this->getUnallocatedAmount();

        $outstandingInvoices = Invoice::where('customer_id', $this->customer_id)
            ->where('company_id', $this->company_id)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0)
            ->orderBy('due_date')
            ->get();

        foreach ($outstandingInvoices as $invoice) {
            if ($unallocatedAmount->isZero()) {
                break;
            }

            $invoiceBalance = Money::of($invoice->balance_due, $invoice->currency->code);
            $allocationAmount = $unallocatedAmount->isLessThan($invoiceBalance) ? $unallocatedAmount : $invoiceBalance;

            if ($allocationAmount->isGreaterThan(Money::of(0, $this->currency->code))) {
                $allocation = $this->allocateToInvoice($invoice, $allocationAmount);
                $allocations[] = $allocation;
                $unallocatedAmount = $unallocatedAmount->minus($allocationAmount);
            }
        }

        return $allocations;
    }

    public function voidAllocations(?string $reason = null): void
    {
        foreach ($this->allocations as $allocation) {
            $allocation->void($reason);
        }
    }

    public function refund(Money $amount, ?string $reason = null): array
    {
        if (! $this->canBeRefunded()) {
            throw new \InvalidArgumentException('Payment cannot be refunded');
        }

        $totalAllocated = $this->getAllocatedAmount();
        if ($amount->isGreaterThan($totalAllocated)) {
            throw new \InvalidArgumentException('Refund amount exceeds allocated amount');
        }

        $refundedAmount = Money::of(0, $this->currency->code);
        $refunds = [];

        $allocations = $this->allocations()
            ->orderBy('created_at')
            ->get();

        foreach ($allocations as $allocation) {
            if ($refundedAmount->isGreaterThanOrEqualTo($amount)) {
                break;
            }

            $allocationAmount = Money::of($allocation->allocated_amount, $this->currency->code);
            $remainingToRefund = $amount->minus($refundedAmount);
            $refundAllocationAmount = $allocationAmount->isLessThan($remainingToRefund) ? $allocationAmount : $remainingToRefund;

            if ($refundAllocationAmount->isGreaterThan(Money::of(0, $this->currency->code))) {
                $refund = $allocation->refund($refundAllocationAmount, $reason);
                $refunds[] = $refund;
                $refundedAmount = $refundedAmount->plus($refundAllocationAmount);
            }
        }

        return $refunds;
    }

    public function getPaymentMethodName(): string
    {
        $methods = [
            'cash' => 'Cash',
            'check' => 'Check',
            'bank_transfer' => 'Bank Transfer',
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
            'paypal' => 'PayPal',
            'stripe' => 'Stripe',
            'other' => 'Other',
        ];

        return $methods[$this->payment_method] ?? ucfirst(str_replace('_', ' ', $this->payment_method));
    }

    public function getDisplayStatus(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    public function getDisplayAmount(): string
    {
        return number_format($this->amount, 2).' '.$this->currency->code;
    }

    public function getDisplayAllocatedAmount(): string
    {
        return number_format($this->getAllocatedAmount()->getAmount()->toFloat(), 2).' '.$this->currency->code;
    }

    public function getDisplayUnallocatedAmount(): string
    {
        return number_format($this->getUnallocatedAmount()->getAmount()->toFloat(), 2).' '.$this->currency->code;
    }
}
