<?php

namespace App\Models;

use App\StateMachines\InvoiceStateMachine;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'invoices';

    protected $primaryKey = 'invoice_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_number',
        'reference_number',
        'invoice_date',
        'due_date',
        'currency_id',
        'exchange_rate',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'shipping_amount',
        'total_amount',
        'paid_amount',
        'balance_due',
        'status',
        'payment_status',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'notes',
        'terms',
        'created_by',
        'updated_by',
        'idempotency_key',
    ];

    protected $casts = [
        'company_id' => 'string',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'exchange_rate' => 'decimal:10',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'sent_at' => 'datetime',
        'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => 'draft',
        'payment_status' => 'unpaid',
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'shipping_amount' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
        'balance_due' => 0,
        'exchange_rate' => 1.0,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_id)) {
                $invoice->invoice_id = (string) \Illuminate\Support\Str::uuid();
            }
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

    public function getRouteKeyName(): string
    {
        return 'invoice_id';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id', 'invoice_id');
    }

    public function payments()
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations', 'invoice_id', 'payment_id')
            ->withPivot('allocated_amount', 'created_at', 'updated_at');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'invoice_id', 'invoice_id');
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

        // Allow zero-total invoices to be sent (e.g., comped invoices).
        // Only disallow negative totals.
        if ($this->total_amount < 0) {
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
        return $this->stateMachine()->transitions[$this->status] ?? [];
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return $this->stateMachine()->canTransitionTo($newStatus);
    }

    public function transitionTo(string $newStatus, ?string $reason = null): void
    {
        $this->stateMachine()->transitionTo($newStatus, ['reason' => $reason]);
    }

    public function markAsDraft(): void
    {
        $this->transitionTo('draft');
    }

    public function getStatusWorkflowSummary(): array
    {
        return [
            'current_status' => $this->status,
            'display_status' => $this->getDisplayStatus(),
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

    public function generateInvoiceNumber(): string
    {
        $company = $this->company ?? \App\Models\Company::find($this->company_id);
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

    public function validateInvoiceNumber(string $invoiceNumber, ?Company $company): bool
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

        $companyId = $company?->id ?? $this->company_id;
        $existingInvoice = static::where('company_id', $companyId)
            ->where('invoice_number', $invoiceNumber)
            ->where('invoice_id', '!=', $this->invoice_id ?? null)
            ->first();

        return $existingInvoice === null;
    }

    private function getNextInvoiceSequence(?Company $company, int $year, string $month, string $day): int
    {
        $today = now()->toDateString();

        $companyId = $company?->id ?? $this->company_id;
        $latestInvoice = static::where('company_id', $companyId)
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
            $itemTax = $item->getTotalTax();

            $subtotal = $subtotal->plus($itemSubtotal->minus($discountAmount));
            $totalTax = $totalTax->plus($itemTax);
        }

        $totalAmount = $subtotal->plus($totalTax);
        $amountPaid = Money::of($this->getTotalPaidAmount(), $this->currency->code);
        $balanceDue = $totalAmount->minus($amountPaid);

        $this->subtotal = $subtotal->getAmount()->toScale(2, RoundingMode::HALF_UP)->toFloat();
        $this->tax_amount = $totalTax->getAmount()->toScale(2, RoundingMode::HALF_UP)->toFloat();
        $this->total_amount = $totalAmount->getAmount()->toScale(2, RoundingMode::HALF_UP)->toFloat();
        $this->paid_amount = $amountPaid->getAmount()->toScale(2, RoundingMode::HALF_UP)->toFloat();
        $this->balance_due = max(0, $balanceDue->getAmount()->toScale(2, RoundingMode::HALF_UP)->toFloat());
    }

    public function getTotalPaidAmount(): float
    {
        return $this->paymentAllocations()
            ->where('status', 'active')
            ->sum('allocated_amount');
    }

    public function getBalanceDue(): Money
    {
        return Money::of($this->balance_due, $this->currency->code);
    }

    public function getAmountPaid(): Money
    {
        return Money::of($this->paid_amount, $this->currency->code);
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

        return min(100, ($this->paid_amount / $this->total_amount) * 100);
    }

    public function isFullyPaid(): bool
    {
        return $this->balance_due <= 0;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->paid_amount > 0 && $this->balance_due > 0;
    }

    public function isUnpaid(): bool
    {
        return $this->paid_amount <= 0;
    }

    public function getPaymentStatusSummary(): array
    {
        return [
            'total_amount' => $this->total_amount,
            'amount_paid' => $this->paid_amount,
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
        $this->stateMachine()->updatePaymentStatus();
    }

    public function recalculateAndSave(): void
    {
        $oldBalanceDue = $this->balance_due;
        $oldAmountPaid = $this->paid_amount;
        $oldStatus = $this->status;

        $this->calculateTotals();
        $this->save();

        if ($oldBalanceDue != $this->balance_due || $oldAmountPaid != $this->paid_amount || $oldStatus != $this->status) {
            Log::info('Invoice recalculated', [
                'invoice_id' => $this->invoice_id,
                'invoice_number' => $this->invoice_number,
                'old_balance_due' => $oldBalanceDue,
                'new_balance_due' => $this->balance_due,
                'old_amount_paid' => $oldAmountPaid,
                'new_amount_paid' => $this->paid_amount,
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
                'amount' => $allocation->allocated_amount,
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
        return Money::of($this->tax_amount, $this->currency->code);
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
        $this->transitionTo('sent');
    }

    public function markAsPosted(): void
    {
        $this->transitionTo('posted');
    }

    public function markAsCancelled(?string $reason = null): void
    {
        $this->transitionTo('cancelled', $reason);
    }

    /**
     * Get an instance of the state machine for this invoice.
     */
    public function stateMachine(): InvoiceStateMachine
    {
        return new InvoiceStateMachine($this);
    }

    /**
     * Journal entries created for this invoice (via source_type/source_id linkage).
     */
    public function journalEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\JournalEntry::class, 'source_id', 'invoice_id')
            ->where('source_type', 'invoice');
    }

    /**
     * Latest journal entry associated to this invoice (if any).
     */
    public function journalEntry(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        // Use posted_at ordering to fetch the latest related journal entry without UUID aggregates
        return $this->hasOne(\App\Models\JournalEntry::class, 'source_id', 'invoice_id')
            ->where('source_type', 'invoice')
            ->orderByDesc('posted_at');
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
