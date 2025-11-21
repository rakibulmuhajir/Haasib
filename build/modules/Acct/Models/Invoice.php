<?php

namespace Modules\Acct\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'acct.invoices';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The attributes that are not mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Attribute casting definitions.
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
     * Company relationship.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Customer relationship.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(\Modules\Acct\Models\Customer::class, 'customer_id');
    }

    /**
     * Creator relationship.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Line items relationship.
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class, 'invoice_id');
    }

    /**
     * Payment allocations relationship.
     */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'invoice_id');
    }

    /**
     * Scope invoices for a company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope invoices by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope outstanding invoices.
     */
    public function scopeOutstanding($query)
    {
        return $query->where('balance_due', '>', 0);
    }

    /**
     * Scope overdue invoices.
     */
    public function scopeOverdue($query)
    {
        return $query->where(function ($inner) {
            $inner->where('payment_status', 'overdue')
                ->orWhere(function ($sub) {
                    $sub->where('payment_status', '!=', 'paid')
                        ->whereIn('status', ['sent', 'posted'])
                        ->whereDate('due_date', '<', now()->toDateString());
                });
        });
    }

    /**
     * Determine if invoice is draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Determine if invoice is sent.
     */
    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Determine if invoice is posted.
     */
    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    /**
     * Determine if invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Determine if invoice is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Determine if invoice is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->payment_status === 'overdue') {
            return true;
        }

        if ($this->isPaid() || $this->isCancelled()) {
            return false;
        }

        return $this->due_date !== null && now()->startOfDay()->gt($this->due_date) && $this->balance_due > 0;
    }

    /**
     * Determine if invoice is partially paid.
     */
    public function isPartiallyPaid(): bool
    {
        return $this->payment_status === 'partially_paid';
    }

    /**
     * Mark invoice as sent.
     */
    public function markAsSent(): self
    {
        if ($this->isCancelled()) {
            throw new \RuntimeException('Cannot mark a cancelled invoice as sent.');
        }

        $this->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'payment_status' => $this->determinePaymentStatus($this->balance_due ?? 0.0, $this->total_amount ?? 0.0),
        ])->save();

        return $this->refresh();
    }

    /**
     * Mark invoice as paid.
     */
    public function markAsPaid(): self
    {
        $this->forceFill([
            'status' => 'paid',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'balance_due' => 0,
        ])->save();

        return $this->refresh();
    }

    /**
     * Mark invoice as overdue.
     */
    public function markAsOverdue(): self
    {
        $this->forceFill([
            'payment_status' => 'overdue',
            'overdue_at' => now(),
        ])->save();

        return $this->refresh();
    }

    /**
     * Recalculate key monetary totals based on current line items and allocations.
     */
    public function calculateTotals(): self
    {
        $lineItems = $this->relationLoaded('lineItems') ? $this->lineItems : $this->lineItems()->get();

        $subtotal = $lineItems->sum(fn (InvoiceLineItem $item) => (float) $item->quantity * (float) $item->unit_price);
        $discounts = $lineItems->sum(fn (InvoiceLineItem $item) => (float) $item->getDiscountAmount());
        $tax = $lineItems->sum(fn (InvoiceLineItem $item) => (float) $item->tax_amount);
        $total = $lineItems->sum(fn (InvoiceLineItem $item) => (float) $item->total);

        $paymentsApplied = $this->totalPaymentsApplied();
        $creditsApplied = $this->totalCreditsApplied();

        $balanceDue = max(0, $total - $paymentsApplied - $creditsApplied);
        $paymentStatus = $this->determinePaymentStatus($balanceDue, $total);

        $attributes = [
            'subtotal' => $subtotal,
            'discount_amount' => $discounts,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'balance_due' => $balanceDue,
            'payment_status' => $paymentStatus,
        ];

        if ($paymentStatus === 'paid') {
            $attributes['paid_at'] = $this->paid_at ?? now();
            $attributes['overdue_at'] = null;
        } elseif ($paymentStatus === 'overdue') {
            $attributes['overdue_at'] = $this->overdue_at ?? now();
        } else {
            $attributes['paid_at'] = $paymentStatus === 'paid' ? ($this->paid_at ?? now()) : null;
            $attributes['overdue_at'] = null;
        }

        $this->forceFill($attributes)->save();

        return $this->refresh();
    }

    /**
     * Total active payment allocations applied to the invoice.
     */
    public function totalPaymentsApplied(): float
    {
        return (float) $this->paymentAllocations()
            ->active()
            ->sum('allocated_amount');
    }

    /**
     * Total credit note applications applied to the invoice.
     */
    public function totalCreditsApplied(): float
    {
        return (float) $this->creditNoteApplications()->sum('amount_applied');
    }

    /**
     * Generate a unique invoice number for a company.
     */
    public static function generateInvoiceNumber(string $companyId, ?string $prefix = null): string
    {
        $year = now()->format('Y');
        $sequencePrefix = ($prefix ?? 'INV-').$year.'-';

        $resolver = function () use ($companyId, $sequencePrefix) {
            $lastNumber = static::query()
                ->where('company_id', $companyId)
                ->where('invoice_number', 'like', $sequencePrefix.'%')
                ->lockForUpdate()
                ->orderByDesc('invoice_number')
                ->value('invoice_number');

            $sequence = 1;

            if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            }

            return $sequencePrefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        };

        if (DB::transactionLevel() > 0) {
            return $resolver();
        }

        return DB::transaction($resolver, 5);
    }

    /**
     * Determine payment status label from balance context.
     */
    protected function determinePaymentStatus(float $balanceDue, float $totalAmount): string
    {
        $epsilon = 0.01;

        if ($balanceDue <= $epsilon) {
            return 'paid';
        }

        if ($totalAmount <= $epsilon) {
            return 'paid';
        }

        if ($balanceDue < $totalAmount - $epsilon) {
            return 'partially_paid';
        }

        if ($this->due_date && now()->startOfDay()->gt($this->due_date) && ! $this->isDraft() && $balanceDue > $epsilon) {
            return 'overdue';
        }

        return 'unpaid';
    }
}
