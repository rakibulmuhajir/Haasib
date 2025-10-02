<?php

namespace App\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentAllocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.payment_allocations';

    protected $primaryKey = 'allocation_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'payment_id',
        'invoice_id',
        'allocated_amount',
        'status',
        'allocation_date',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'allocated_amount' => 0,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($allocation) {
            if (empty($allocation->allocation_id)) {
                $allocation->allocation_id = (string) \Illuminate\Support\Str::uuid();
            }
        });

        static::created(function ($allocation) {
            $allocation->updateInvoicePayments();
        });

        static::updated(function ($allocation) {
            if ($allocation->isDirty('allocated_amount')) {
                $allocation->updateInvoicePayments();
            }
        });

        static::deleted(function ($allocation) {
            $allocation->updateInvoicePayments();
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id');
    }

    public function scopeForPayment($query, $paymentId)
    {
        return $query->where('payment_id', $paymentId);
    }

    public function scopeForInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVoided($query)
    {
        return $query->where('status', 'void');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('allocation_date', [$startDate, $endDate]);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function getAmount(): Money
    {
        return Money::of($this->allocated_amount, $this->payment->currency->code);
    }

    public function getAmountInInvoiceCurrency(): Money
    {
        $paymentCurrency = $this->payment->currency->code;
        $invoiceCurrency = $this->invoice->currency->code;

        if ($paymentCurrency === $invoiceCurrency) {
            return $this->getAmount();
        }

        $exchangeRate = $this->payment->exchange_rate;

        return $this->getAmount()->multipliedBy($exchangeRate);
    }

    public function canBeVoided(): bool
    {
        return $this->isActive() && $this->payment->canBeVoided();
    }

    public function canBeRefunded(): bool
    {
        return $this->isActive() && $this->payment->canBeRefunded();
    }

    public function void(?string $reason = null): void
    {
        if (! $this->canBeVoided()) {
            throw new \InvalidArgumentException('Allocation cannot be voided');
        }

        $this->status = 'void';
        $this->metadata = array_merge($this->metadata ?? [], [
            'voided_at' => now()->toISOString(),
            'void_reason' => $reason,
        ]);
        $this->save();
    }

    public function refund(Money $amount, ?string $reason = null): self
    {
        if (! $this->canBeRefunded()) {
            throw new \InvalidArgumentException('Allocation cannot be refunded');
        }

        if ($amount->isGreaterThan($this->getAmount())) {
            throw new \InvalidArgumentException('Refund amount exceeds allocation amount');
        }

        $refund = new self([
            'payment_id' => $this->payment_id,
            'invoice_id' => $this->invoice_id,
            'allocated_amount' => $amount->getAmount()->toFloat(),
            'status' => 'refunded',
            'allocation_date' => now()->toDateString(),
            'notes' => $reason,
            'metadata' => [
                'original_allocation_id' => $this->id,
                'refunded_at' => now()->toISOString(),
                'refund_reason' => $reason,
            ],
        ]);

        $refund->save();

        $this->metadata = array_merge($this->metadata ?? [], [
            'partially_refunded' => true,
            'refunded_amount' => $amount->getAmount()->toFloat(),
            'refunded_at' => now()->toISOString(),
        ]);

        if ($amount->isEqualTo($this->getAmount())) {
            $this->status = 'refunded';
        }

        $this->save();

        return $refund;
    }

    public function updateInvoicePayments(): void
    {
        $invoice = $this->invoice;
        if (! $invoice) {
            return;
        }

        $totalPaid = $invoice->paymentAllocations()
            ->where('status', 'active')
            ->sum('allocated_amount');

        $invoice->paid_amount = $totalPaid;
        $invoice->calculateTotals();
        $invoice->updatePaymentStatus();
        $invoice->save();
    }

    public function getOriginalAllocation(): ?self
    {
        if (! isset($this->metadata['original_allocation_id'])) {
            return null;
        }

        return static::find($this->metadata['original_allocation_id']);
    }

    public function getRefunds(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('metadata->original_allocation_id', $this->id)->get();
    }

    public function getTotalRefundedAmount(): Money
    {
        $refunds = $this->getRefunds();
        $totalRefunded = Money::of(0, $this->payment->currency->code);

        foreach ($refunds as $refund) {
            $totalRefunded = $totalRefunded->plus($refund->getAmount());
        }

        return $totalRefunded;
    }

    public function getRemainingAmount(): Money
    {
        $refundedAmount = $this->getTotalRefundedAmount();

        return $this->getAmount()->minus($refundedAmount);
    }

    public function getAgeInDays(): int
    {
        return now()->diffInDays($this->created_at);
    }

    public function getDisplayStatus(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'void' => 'Voided',
            'refunded' => 'Refunded',
            default => ucfirst($this->status),
        };
    }

    public function getDisplayAmount(): string
    {
        return number_format($this->allocated_amount, 2).' '.$this->payment->currency->code;
    }

    public function getDisplayAllocationDate(): string
    {
        return $this->allocation_date->format('Y-m-d');
    }

    public function getAllocationType(): string
    {
        if ($this->isRefunded()) {
            return 'Refund';
        }
        if ($this->isVoid()) {
            return 'Void';
        }

        return 'Payment';
    }

    public function validateAllocation(): bool
    {
        if (! $this->payment || ! $this->invoice) {
            throw new \InvalidArgumentException('Payment and invoice must be set');
        }

        $payment = $this->payment;
        $invoice = $this->invoice;

        if ($payment->customer_id !== $invoice->customer_id) {
            throw new \InvalidArgumentException('Payment and invoice must belong to the same customer');
        }

        if ($payment->company_id !== $invoice->company_id) {
            throw new \InvalidArgumentException('Payment and invoice must belong to the same company');
        }

        if ($payment->isCompleted() !== true) {
            throw new \InvalidArgumentException('Payment must be completed to be allocated');
        }

        if (! in_array($invoice->status, ['sent', 'posted', 'partial'])) {
            throw new \InvalidArgumentException('Invoice must be in sent, posted, or partial status to receive payments');
        }

        if ($this->allocated_amount <= 0) {
            throw new \InvalidArgumentException('Allocation amount must be positive');
        }

        $currentAllocations = $payment->allocations()
            ->where('status', 'active')
            ->where('id', '!=', $this->id ?? null)
            ->sum('allocated_amount');

        $totalAllocated = $currentAllocations + $this->allocated_amount;

        if ($totalAllocated > $payment->amount) {
            $overAllocationAmount = $totalAllocated - $payment->amount;
            throw new \InvalidArgumentException("Payment allocation exceeds available amount by {$overAllocationAmount}");
        }

        $invoiceAllocations = $invoice->paymentAllocations()
            ->where('status', 'active')
            ->where('id', '!=', $this->id ?? null)
            ->sum('allocated_amount');

        $totalInvoiceAllocated = $invoiceAllocations + $this->allocated_amount;

        if ($totalInvoiceAllocated > $invoice->total_amount) {
            $overAllocationAmount = $totalInvoiceAllocated - $invoice->total_amount;
            throw new \InvalidArgumentException("Invoice allocation exceeds total invoice amount by {$overAllocationAmount}");
        }

        if ($totalInvoiceAllocated > $invoice->balance_due) {
            $overAllocationAmount = $totalInvoiceAllocated - $invoice->balance_due;
            throw new \InvalidArgumentException("Invoice allocation exceeds balance due by {$overAllocationAmount}");
        }

        return true;
    }

    public function validateAllocationAmount(float $amount): bool
    {
        if (! $this->payment || ! $this->invoice) {
            throw new \InvalidArgumentException('Payment and invoice must be set');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Allocation amount must be positive');
        }

        $payment = $this->payment;
        $invoice = $this->invoice;

        $unallocatedPaymentAmount = $payment->getUnallocatedAmount()->getAmount()->toFloat();
        if ($amount > $unallocatedPaymentAmount) {
            throw new \InvalidArgumentException("Allocation amount ({$amount}) exceeds unallocated payment amount ({$unallocatedPaymentAmount})");
        }

        $remainingInvoiceBalance = $invoice->balance_due;
        if ($amount > $remainingInvoiceBalance) {
            throw new \InvalidArgumentException("Allocation amount ({$amount}) exceeds remaining invoice balance ({$remainingInvoiceBalance})");
        }

        return true;
    }

    public function getMaximumAllocationAmount(): float
    {
        if (! $this->payment || ! $this->invoice) {
            return 0;
        }

        $unallocatedPaymentAmount = $this->payment->getUnallocatedAmount()->getAmount()->toFloat();
        $remainingInvoiceBalance = $this->invoice->balance_due;

        return min($unallocatedPaymentAmount, $remainingInvoiceBalance);
    }

    public function canBeFullyAllocated(): bool
    {
        return $this->getMaximumAllocationAmount() > 0;
    }

    public function getAllocationWarnings(): array
    {
        $warnings = [];

        if (! $this->payment || ! $this->invoice) {
            $warnings[] = 'Payment or invoice not properly linked';

            return $warnings;
        }

        $payment = $this->payment;
        $invoice = $this->invoice;

        if ($payment->status !== 'completed') {
            $warnings[] = 'Payment is not completed';
        }

        if ($invoice->isOverdue()) {
            $warnings[] = 'Invoice is overdue';
        }

        if ($payment->currency->code !== $invoice->currency->code) {
            $warnings[] = 'Payment and invoice use different currencies';
        }

        return $warnings;
    }

    public function getAllocationSummary(): array
    {
        return [
            'id' => $this->id,
            'payment_reference' => $this->payment->payment_reference,
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => $this->getDisplayAmount(),
            'status' => $this->getDisplayStatus(),
            'allocation_date' => $this->getDisplayAllocationDate(),
            'allocation_type' => $this->getAllocationType(),
            'age_in_days' => $this->getAgeInDays(),
            'notes' => $this->notes,
            'is_valid' => $this->validateAllocation(),
        ];
    }
}
