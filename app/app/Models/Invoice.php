<?php

namespace App\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'invoices';

    protected $primaryKey = 'invoice_id';

    public $incrementing = true;

    protected $fillable = [
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
        'company_id' => 'string',
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

        static::creating(function ($invoice) {
            if (! $invoice->invoice_number) {
                $invoice->invoice_number = $invoice->generateInvoiceNumber();
            }

            if (! $invoice->validateInvoiceNumber($invoice->invoice_number, $invoice->company)) {
                throw new \InvalidArgumentException('Invalid or duplicate invoice number');
            }
        });

        static::updating(function ($invoice) {
            if ($invoice->isDirty('invoice_number') && ! $invoice->isDraft()) {
                throw new \InvalidArgumentException('Invoice number can only be changed while in draft status');
            }

            if ($invoice->isDirty('invoice_number') && ! $invoice->validateInvoiceNumber($invoice->invoice_number, $invoice->company)) {
                throw new \InvalidArgumentException('Invalid or duplicate invoice number');
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
        return $this->due_date < now() && $this->balance_due > 0 && ! $this->isPaid();
    }

    public function getDaysOverdue(): int
    {
        if (! $this->isOverdue()) {
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
        if ($this->status !== 'draft') {
            return false;
        }

        if ($this->items()->count() === 0) {
            return false;
        }

        if ($this->total_amount <= 0) {
            return false;
        }

        if (! $this->isInvoiceNumberValid()) {
            return false;
        }

        return true;
    }

    public function canBePosted(): bool
    {
        if ($this->status !== 'sent') {
            return false;
        }

        if ($this->items()->count() === 0) {
            return false;
        }

        if ($this->total_amount <= 0) {
            return false;
        }

        if (isset($this->sent_at) && $this->sent_at->isFuture()) {
            return false;
        }

        return true;
    }

    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, ['paid', 'cancelled']);
    }

    public function canBeReopened(): bool
    {
        return in_array($this->status, ['cancelled']) && $this->items()->count() > 0;
    }

    public function canBeDuplicated(): bool
    {
        return in_array($this->status, ['draft', 'sent', 'posted', 'partial', 'paid']) && $this->items()->count() > 0;
    }

    public function canGeneratePDF(): bool
    {
        return in_array($this->status, ['sent', 'posted', 'partial', 'paid']) && $this->items()->count() > 0;
    }

    public function canBeEmailed(): bool
    {
        return $this->status === 'sent' && $this->items()->count() > 0 && $this->customer && $this->customer->email;
    }

    public function getValidStatusTransitions(): array
    {
        $transitions = [
            'draft' => ['sent', 'cancelled'],
            'sent' => ['draft', 'posted', 'cancelled'],
            'posted' => ['sent', 'cancelled'],
            'partial' => ['partial', 'paid'],
            'paid' => ['paid'],
            'cancelled' => ['draft'],
        ];

        return $transitions[$this->status] ?? [];
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, $this->getValidStatusTransitions());
    }

    public function validateStatusTransition(string $newStatus, ?string $reason = null): void
    {
        if ($this->status === $newStatus) {
            return;
        }

        if (! $this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException("Cannot transition invoice from {$this->status} to {$newStatus}");
        }

        if ($newStatus === 'cancelled' && empty(trim($reason ?? ''))) {
            throw new \InvalidArgumentException('Cancellation reason is required');
        }

        if ($newStatus === 'posted' && $this->balance_due <= 0) {
            throw new \InvalidArgumentException('Cannot post invoice with zero balance due');
        }

        if ($newStatus === 'draft' && $this->status === 'cancelled') {
            if ($this->paymentAllocations()->where('status', 'active')->exists()) {
                throw new \InvalidArgumentException('Cannot reopen cancelled invoice with existing payment allocations');
            }
        }
    }

    public function transitionTo(string $newStatus, ?string $reason = null): void
    {
        $this->validateStatusTransition($newStatus, $reason);
        $oldStatus = $this->status;

        switch ($newStatus) {
            case 'sent':
                $this->markAsSent();
                break;
            case 'posted':
                $this->markAsPosted();
                break;
            case 'cancelled':
                $this->markAsCancelled($reason);
                break;
            case 'draft':
                $this->markAsDraft();
                break;
            default:
                $this->status = $newStatus;
                $this->save();
        }

        $this->logStatusTransition($oldStatus, $newStatus, $reason);
    }

    public function markAsDraft(): void
    {
        if (! $this->canBeReopened()) {
            throw new \InvalidArgumentException('Invoice cannot be reopened to draft');
        }

        $this->status = 'draft';
        $this->metadata = array_merge($this->metadata ?? [], [
            'reopened_at' => now()->toISOString(),
            'reopened_by_user_id' => auth()->id(),
        ]);
        $this->save();
    }

    public function getStatusWorkflowSummary(): array
    {
        return [
            'current_status' => $this->status,
            'display_status' => $this->getDisplayStatus(),
            'can_be_edited' => $this->canBeEdited(),
            'can_be_sent' => $this->canBeSent(),
            'can_be_posted' => $this->canBePosted(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'can_be_reopened' => $this->canBeReopened(),
            'can_be_duplicated' => $this->canBeDuplicated(),
            'can_generate_pdf' => $this->canGeneratePDF(),
            'can_be_emailed' => $this->canBeEmailed(),
            'valid_transitions' => $this->getValidStatusTransitions(),
            'workflow_restrictions' => $this->getWorkflowRestrictions(),
        ];
    }

    public function getWorkflowRestrictions(): array
    {
        $restrictions = [];

        if ($this->isPaid()) {
            $restrictions[] = 'Invoice is fully paid';
        }

        if ($this->isCancelled()) {
            $restrictions[] = 'Invoice is cancelled';
        }

        if ($this->paymentAllocations()->where('status', 'active')->exists()) {
            $restrictions[] = 'Invoice has payment allocations';
        }

        if (isset($this->posted_at)) {
            $restrictions[] = 'Invoice has been posted to ledger';
        }

        if ($this->items()->count() === 0) {
            $restrictions[] = 'Invoice has no items';
        }

        if ($this->total_amount <= 0) {
            $restrictions[] = 'Invoice has zero total amount';
        }

        return $restrictions;
    }

    private function logStatusTransition(string $oldStatus, string $newStatus, ?string $reason = null): void
    {
        Log::info('Invoice status transition', [
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice_number,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function generateInvoiceNumber(): string
    {
        $company = $this->company;
        $year = now()->year;
        $month = now()->format('m');
        $day = now()->format('d');

        $prefix = $company->settings['invoice_prefix'] ?? 'INV';
        $pattern = $company->settings['invoice_number_pattern'] ?? '{prefix}-{year}{month}{day}-{sequence:4}';

        $sequence = $this->getNextInvoiceSequence($company, $year, $month, $day);

        return str_replace(
            ['{prefix}', '{year}', '{month}', '{day}', '{sequence:4}', '{sequence:5}', '{sequence:6}'],
            [$prefix, $year, $month, $day, str_pad($sequence, 4, '0', STR_PAD_LEFT), str_pad($sequence, 5, '0', STR_PAD_LEFT), str_pad($sequence, 6, '0', STR_PAD_LEFT)],
            $pattern
        );
    }

    public function validateInvoiceNumber(string $invoiceNumber, Company $company): bool
    {
        if (empty(trim($invoiceNumber))) {
            return false;
        }

        if (strlen($invoiceNumber) > 50) {
            return false;
        }

        if (! preg_match('/^[A-Za-z0-9\-_\/\s]+$/', $invoiceNumber)) {
            return false;
        }

        $existingInvoice = static::where('company_id', $company->id)
            ->where('invoice_number', $invoiceNumber)
            ->where('invoice_id', '!=', $this->invoice_id ?? null)
            ->first();

        return $existingInvoice === null;
    }

    private function getNextInvoiceSequence(Company $company, int $year, string $month, string $day): int
    {
        $today = now()->toDateString();

        $latestInvoice = static::where('company_id', $company->id)
            ->whereDate('created_at', $today)
            ->orderBy('invoice_number', 'desc')
            ->first();

        if (! $latestInvoice) {
            return 1;
        }

        $pattern = '/.*?(\d+)$/';
        if (preg_match($pattern, $latestInvoice->invoice_number, $matches)) {
            return (int) $matches[1] + 1;
        }

        return 1;
    }

    public function getInvoiceNumberPrefix(): string
    {
        return $this->company->settings['invoice_prefix'] ?? 'INV';
    }

    public function getInvoiceNumberPattern(): string
    {
        return $this->company->settings['invoice_number_pattern'] ?? '{prefix}-{year}{month}{day}-{sequence:4}';
    }

    public function isInvoiceNumberValid(): bool
    {
        return $this->validateInvoiceNumber($this->invoice_number, $this->company);
    }

    public function resetInvoiceNumber(): void
    {
        if ($this->isDraft()) {
            $this->invoice_number = $this->generateInvoiceNumber();
            $this->save();
        }
    }

    public function calculateTotals(): void
    {
        $items = $this->items;
        $subtotal = Money::of(0, $this->currency->code);
        $totalTax = Money::of(0, $this->currency->code);

        foreach ($items as $item) {
            $itemSubtotal = Money::of($item->quantity * $item->unit_price, $this->currency->code);
            $discountAmount = Money::of($item->discount_amount ?? 0, $this->currency->code);
            $itemTax = Money::of($item->total_tax, $this->currency->code);

            $subtotal = $subtotal->plus($itemSubtotal->minus($discountAmount));
            $totalTax = $totalTax->plus($itemTax);
        }

        $totalAmount = $subtotal->plus($totalTax);
        $amountPaid = Money::of($this->getTotalPaidAmount(), $this->currency->code);
        $balanceDue = $totalAmount->minus($amountPaid);

        $this->subtotal = $subtotal->getAmount()->toFloat();
        $this->total_tax = $totalTax->getAmount()->toFloat();
        $this->total_amount = $totalAmount->getAmount()->toFloat();
        $this->amount_paid = $amountPaid->getAmount()->toFloat();
        $this->balance_due = max(0, $balanceDue->getAmount()->toFloat());
    }

    public function getTotalPaidAmount(): float
    {
        return $this->paymentAllocations()
            ->where('status', 'active')
            ->sum('amount');
    }

    public function getBalanceDue(): Money
    {
        return Money::of($this->balance_due, $this->currency->code);
    }

    public function getAmountPaid(): Money
    {
        return Money::of($this->amount_paid, $this->currency->code);
    }

    public function getRemainingBalance(): Money
    {
        return $this->getBalanceDue();
    }

    public function getPaymentPercentage(): float
    {
        if ($this->total_amount <= 0) {
            return 0;
        }

        return min(100, ($this->amount_paid / $this->total_amount) * 100);
    }

    public function isFullyPaid(): bool
    {
        return $this->balance_due <= 0;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->amount_paid > 0 && $this->balance_due > 0;
    }

    public function isUnpaid(): bool
    {
        return $this->amount_paid <= 0;
    }

    public function getPaymentStatusSummary(): array
    {
        return [
            'total_amount' => $this->total_amount,
            'amount_paid' => $this->amount_paid,
            'balance_due' => $this->balance_due,
            'payment_percentage' => $this->getPaymentPercentage(),
            'is_fully_paid' => $this->isFullyPaid(),
            'is_partially_paid' => $this->isPartiallyPaid(),
            'is_unpaid' => $this->isUnpaid(),
            'status' => $this->status,
        ];
    }

    public function updatePaymentStatus(): void
    {
        $oldStatus = $this->status;

        if ($this->balance_due <= 0) {
            $this->status = 'paid';
        } elseif ($this->amount_paid > 0) {
            $this->status = 'partial';
        } else {
            $this->status = match ($oldStatus) {
                'draft' => 'draft',
                'sent' => 'sent',
                'posted' => 'posted',
                default => 'sent',
            };
        }

        if ($oldStatus !== $this->status) {
            $this->metadata = array_merge($this->metadata ?? [], [
                'status_changed_at' => now()->toISOString(),
                'previous_status' => $oldStatus,
                'status_change_reason' => 'payment_update',
            ]);
        }
    }

    public function recalculateAndSave(): void
    {
        $oldBalanceDue = $this->balance_due;
        $oldAmountPaid = $this->amount_paid;
        $oldStatus = $this->status;

        $this->calculateTotals();
        $this->updatePaymentStatus();
        $this->save();

        if ($oldBalanceDue != $this->balance_due || $oldAmountPaid != $this->amount_paid || $oldStatus != $this->status) {
            Log::info('Invoice recalculated', [
                'invoice_id' => $this->invoice_id,
                'invoice_number' => $this->invoice_number,
                'old_balance_due' => $oldBalanceDue,
                'new_balance_due' => $this->balance_due,
                'old_amount_paid' => $oldAmountPaid,
                'new_amount_paid' => $this->amount_paid,
                'old_status' => $oldStatus,
                'new_status' => $this->status,
            ]);
        }
    }

    public function applyPayment(Money $amount, ?Payment $payment = null): void
    {
        if ($amount->isLessThanOrEqualTo(Money::of(0, $this->currency->code))) {
            throw new \InvalidArgumentException('Payment amount must be positive');
        }

        if ($amount->isGreaterThan($this->getBalanceDue())) {
            throw new \InvalidArgumentException('Payment amount exceeds balance due');
        }

        $this->recalculateAndSave();
    }

    public function getPaymentAllocations(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->paymentAllocations()
            ->with(['payment', 'payment.currency'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPaymentHistory(): array
    {
        $allocations = $this->getPaymentAllocations();

        return $allocations->map(function ($allocation) {
            return [
                'allocation_id' => $allocation->id,
                'payment_id' => $allocation->payment_id,
                'payment_reference' => $allocation->payment->payment_reference,
                'payment_date' => $allocation->payment->payment_date,
                'amount' => $allocation->amount,
                'currency' => $allocation->payment->currency->code,
                'allocation_date' => $allocation->allocation_date,
                'status' => $allocation->status,
                'notes' => $allocation->notes,
                'created_at' => $allocation->created_at,
            ];
        })->toArray();
    }

    public function getAgingInformation(): array
    {
        if ($this->isFullyPaid()) {
            return [
                'is_overdue' => false,
                'days_overdue' => 0,
                'aging_category' => 'paid',
                'aging_days' => 0,
            ];
        }

        $daysOverdue = max(0, now()->diffInDays($this->due_date));

        $agingCategory = match (true) {
            $daysOverdue <= 0 => 'current',
            $daysOverdue <= 30 => '1_30_days',
            $daysOverdue <= 60 => '31_60_days',
            $daysOverdue <= 90 => '61_90_days',
            default => 'over_90_days',
        };

        return [
            'is_overdue' => $daysOverdue > 0,
            'days_overdue' => $daysOverdue,
            'aging_category' => $agingCategory,
            'aging_days' => $daysOverdue,
            'due_date' => $this->due_date,
            'current_date' => now()->toDateString(),
        ];
    }

    public function getAmountInBaseCurrency(): Money
    {
        if ($this->currency->code === $this->company->base_currency) {
            return $this->getTotalAmount();
        }

        $currencyService = app(\App\Services\CurrencyService::class);

        return $currencyService->convertCurrency(
            $this->getTotalAmount(),
            $this->currency->code,
            $this->company->base_currency,
            $this->invoice_date
        );
    }

    public function getBalanceDueInBaseCurrency(): Money
    {
        if ($this->currency->code === $this->company->base_currency) {
            return $this->getBalanceDue();
        }

        $currencyService = app(\App\Services\CurrencyService::class);

        return $currencyService->convertCurrency(
            $this->getBalanceDue(),
            $this->currency->code,
            $this->company->base_currency,
            now()->toDateString()
        );
    }

    public function getAmountPaidInBaseCurrency(): Money
    {
        if ($this->currency->code === $this->company->base_currency) {
            return $this->getAmountPaid();
        }

        $currencyService = app(\App\Services\CurrencyService::class);

        return $currencyService->convertCurrency(
            $this->getAmountPaid(),
            $this->currency->code,
            $this->company->base_currency,
            now()->toDateString()
        );
    }

    public function getTotalAmount(): Money
    {
        return Money::of($this->total_amount, $this->currency->code);
    }

    public function getSubtotalAmount(): Money
    {
        return Money::of($this->subtotal, $this->currency->code);
    }

    public function getTaxAmount(): Money
    {
        return Money::of($this->total_tax, $this->currency->code);
    }

    public function getFormattedTotalAmount(?string $locale = null): string
    {
        $currencyService = app(\App\Services\CurrencyService::class);

        return $currencyService->formatMoney($this->getTotalAmount(), $locale);
    }

    public function getFormattedBalanceDue(?string $locale = null): string
    {
        $currencyService = app(\App\Services\CurrencyService::class);

        return $currencyService->formatMoney($this->getBalanceDue(), $locale);
    }

    public function getFormattedAmountPaid(?string $locale = null): string
    {
        $currencyService = app(\App\Services\CurrencyService::class);

        return $currencyService->formatMoney($this->getAmountPaid(), $locale);
    }

    public function getCurrencySummary(): array
    {
        $currencyService = app(\App\Services\CurrencyService::class);

        return [
            'invoice_currency' => [
                'code' => $this->currency->code,
                'name' => $this->currency->name,
                'symbol' => $this->currency->symbol,
                'total_amount' => $this->getFormattedTotalAmount(),
                'balance_due' => $this->getFormattedBalanceDue(),
                'amount_paid' => $this->getFormattedAmountPaid(),
            ],
            'base_currency' => [
                'code' => $this->company->base_currency,
                'total_amount' => $currencyService->formatMoney($this->getAmountInBaseCurrency()),
                'balance_due' => $currencyService->formatMoney($this->getBalanceDueInBaseCurrency()),
                'amount_paid' => $currencyService->formatMoney($this->getAmountPaidInBaseCurrency()),
            ],
            'exchange_rate_info' => $this->currency->code === $this->company->base_currency ? null : [
                'from_currency' => $this->currency->code,
                'to_currency' => $this->company->base_currency,
                'rate' => $currencyService->getExchangeRate($this->currency->code, $this->company->base_currency, $this->invoice_date),
            ],
        ];
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

    public function getDisplayStatus(): string
    {
        return match ($this->status) {
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
