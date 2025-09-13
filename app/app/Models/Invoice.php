<?php

namespace App\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'invoices';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'customer_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'currency_id',
        'subtotal',
        'total_tax',
        'total_amount',
        'amount_paid',
        'balance_due',
        'status',
        'notes',
        'terms',
        'metadata',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'draft',
        'subtotal' => 0,
        'total_tax' => 0,
        'total_amount' => 0,
        'amount_paid' => 0,
        'balance_due' => 0,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = $model->id ?: (string) Str::uuid();
        });

        static::creating(function ($invoice) {
            if (!$invoice->invoice_number) {
                $invoice->invoice_number = $invoice->generateInvoiceNumber();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function accountsReceivable(): HasMany
    {
        return $this->hasMany(AccountsReceivable::class);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('status', '!=', 'paid')
            ->where('balance_due', '>', 0);
    }

    public function scopeDueBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('due_date', [$startDate, $endDate]);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isOverdue(): bool
    {
        return $this->due_date < now() && $this->balance_due > 0 && !$this->isPaid();
    }

    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }
        
        return max(0, now()->diffInDays($this->due_date));
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'sent']);
    }

    public function canBeSent(): bool
    {
        return $this->status === 'draft' && $this->items()->count() > 0;
    }

    public function canBePosted(): bool
    {
        return $this->status === 'sent' && $this->items()->count() > 0;
    }

    public function canBeCancelled(): bool
    {
        return !in_array($this->status, ['paid', 'cancelled']);
    }

    public function generateInvoiceNumber(): string
    {
        $company = $this->company;
        $year = now()->year;
        $month = now()->format('m');
        
        $prefix = $company->settings['invoice_prefix'] ?? 'INV';
        $pattern = $company->settings['invoice_number_pattern'] ?? '{prefix}-{year}{month}-{sequence:4}';
        
        $latestInvoice = static::where('company_id', $company->id)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderByRaw('CAST(SUBSTRING(invoice_number FROM GREATEST(POSITION("-" IN invoice_number), POSITION(" " IN invoice_number)) + 1) AS UNSIGNED) DESC')
            ->first();
        
        $sequence = $latestInvoice ? ((int) preg_replace('/.*?(\d+)$/', '$1', $latestInvoice->invoice_number)) + 1 : 1;
        
        return str_replace(
            ['{prefix}', '{year}', '{month}', '{sequence:4}', '{sequence:5}', '{sequence:6}'],
            [$prefix, $year, $month, str_pad($sequence, 4, '0', STR_PAD_LEFT), str_pad($sequence, 5, '0', STR_PAD_LEFT), str_pad($sequence, 6, '0', STR_PAD_LEFT)],
            $pattern
        );
    }

    public function calculateTotals(): void
    {
        $items = $this->items;
        $subtotal = Money::of(0, $this->currency->code);
        $totalTax = Money::of(0, $this->currency->code);

        foreach ($items as $item) {
            $itemSubtotal = Money::of($item->quantity * $item->unit_price, $this->currency->code);
            $itemTax = Money::of($item->total_tax, $this->currency->code);
            
            $subtotal = $subtotal->plus($itemSubtotal);
            $totalTax = $totalTax->plus($itemTax);
        }

        $totalAmount = $subtotal->plus($totalTax);
        $balanceDue = $totalAmount->minus(Money::of($this->amount_paid, $this->currency->code));

        $this->subtotal = $subtotal->getAmount()->toFloat();
        $this->total_tax = $totalTax->getAmount()->toFloat();
        $this->total_amount = $totalAmount->getAmount()->toFloat();
        $this->balance_due = max(0, $balanceDue->getAmount()->toFloat());
    }

    public function updatePaymentStatus(): void
    {
        if ($this->balance_due <= 0) {
            $this->status = 'paid';
        } elseif ($this->amount_paid > 0) {
            $this->status = 'partial';
        } else {
            $this->status = $this->status === 'draft' ? 'draft' : 'sent';
        }
    }

    public function markAsSent(): void
    {
        if ($this->canBeSent()) {
            $this->status = 'sent';
            $this->sent_at = now();
            $this->save();
        }
    }

    public function markAsPosted(): void
    {
        if ($this->canBePosted()) {
            $this->status = 'posted';
            $this->posted_at = now();
            $this->save();
        }
    }

    public function markAsCancelled(?string $reason = null): void
    {
        if ($this->canBeCancelled()) {
            $this->status = 'cancelled';
            $this->cancelled_at = now();
            $this->metadata = array_merge($this->metadata ?? [], [
                'cancellation_reason' => $reason,
                'cancelled_at' => now()->toISOString(),
            ]);
            $this->save();
        }
    }

    public function applyPayment(Money $amount, ?Payment $payment = null): void
    {
        $newAmountPaid = Money::of($this->amount_paid, $this->currency->code)->plus($amount);
        $this->amount_paid = min($newAmountPaid->getAmount()->toFloat(), $this->total_amount);
        $this->calculateTotals();
        $this->updatePaymentStatus();
        $this->save();
    }

    public function getDisplayStatus(): string
    {
        return match($this->status) {
            'draft' => 'Draft',
            'sent' => 'Sent',
            'posted' => 'Posted',
            'partial' => 'Partial Payment',
            'paid' => 'Paid',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }
}