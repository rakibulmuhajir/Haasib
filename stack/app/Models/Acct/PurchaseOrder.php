<?php

namespace App\Models\Acct;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditLogging;
use App\Models\User;
use App\Models\Company;
use App\Models\Vendor;
use App\Models\Bill;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PurchaseOrder extends Model
{
    use HasFactory, HasUuids, BelongsToCompany, SoftDeletes, AuditLogging;

    protected $table = 'acct.purchase_orders';

    protected $fillable = [
        'company_id',
        'po_number',
        'vendor_id',
        'status',
        'order_date',
        'expected_delivery_date',
        'currency',
        'exchange_rate',
        'subtotal',
        'tax_amount',
        'total_amount',
        'notes',
        'internal_notes',
        'approved_by',
        'approved_at',
        'sent_to_vendor_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'sent_to_vendor_at' => 'datetime',
        'exchange_rate' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'updated_by',
    ];

    protected $appends = [
        'formatted_subtotal',
        'formatted_tax_amount',
        'formatted_total_amount',
        'status_color',
        'is_editable',
        'is_approvable',
        'is_sendable',
        'is_receivable',
        'is_cancellable',
    ];

    // UUID Configuration
    protected $keyType = 'string';
    public $incrementing = false;

    // === CONSTANTS ===

    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_SENT = 'sent';
    const STATUS_PARTIAL_RECEIVED = 'partial_received';
    const STATUS_RECEIVED = 'received';
    const STATUS_CLOSED = 'closed';
    const STATUS_CANCELLED = 'cancelled';

    // === RELATIONSHIPS ===

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'po_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'po_id');
    }

    // === SCOPES ===

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByVendor(Builder $query, string $vendorId): Builder
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeByDateRange(Builder $query, $startDate, $endDate = null): Builder
    {
        $query->where('order_date', '>=', $startDate);

        if ($endDate) {
            $query->where('order_date', '<=', $endDate);
        }

        return $query;
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_APPROVAL);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_RECEIVED, self::STATUS_PARTIAL_RECEIVED]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_CLOSED, self::STATUS_CANCELLED]);
    }

    // === BUSINESS LOGIC METHODS ===

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function isPartiallyReceived(): bool
    {
        return $this->status === self::STATUS_PARTIAL_RECEIVED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING_APPROVAL]);
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function canBeSent(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canBeReceived(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_PARTIAL_RECEIVED]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT, 
            self::STATUS_PENDING_APPROVAL, 
            self::STATUS_APPROVED, 
            self::STATUS_SENT
        ]);
    }

    public function canBeClosed(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    // === MUTATORS & ACCESSORS ===

    public function getFormattedSubtotalAttribute(): string
    {
        return number_format($this->subtotal, 2, '.', ',');
    }

    public function getFormattedTaxAmountAttribute(): string
    {
        return number_format($this->tax_amount, 2, '.', ',');
    }

    public function getFormattedTotalAmountAttribute(): string
    {
        return number_format($this->total_amount, 2, '.', ',');
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            self::STATUS_DRAFT => 'gray',
            self::STATUS_PENDING_APPROVAL => 'orange',
            self::STATUS_APPROVED => 'blue',
            self::STATUS_SENT => 'purple',
            self::STATUS_PARTIAL_RECEIVED => 'yellow',
            self::STATUS_RECEIVED => 'green',
            self::STATUS_CLOSED => 'teal',
            self::STATUS_CANCELLED => 'red',
        ];

        return $colors[$this->status] ?? 'gray';
    }

    public function getIsEditableAttribute(): bool
    {
        return $this->canBeEdited();
    }

    public function getIsApprovableAttribute(): bool
    {
        return $this->canBeApproved();
    }

    public function getIsSendableAttribute(): bool
    {
        return $this->canBeSent();
    }

    public function getIsReceivableAttribute(): bool
    {
        return $this->canBeReceived();
    }

    public function getIsCancellableAttribute(): bool
    {
        return $this->canBeCancelled();
    }

    public function getPoNumberAttribute(): string
    {
        if (!isset($this->attributes['po_number'])) {
            $this->attributes['po_number'] = $this->generatePoNumber();
            $this->save();
        }

        return $this->attributes['po_number'];
    }

    private function generatePoNumber(): string
    {
        $maxNumber = static::where('company_id', $this->company_id)
            ->whereNotNull('po_number')
            ->max('po_number');

        $nextNumber = (int)str_replace('PO-', '', $maxNumber ?? 'PO-000000') + 1;

        return 'PO-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    // === BUSINESS OPERATIONS ===

    public function recalculateTotals(): bool
    {
        $subtotal = 0;
        $taxAmount = 0;

        foreach ($this->lines as $line) {
            $lineTotal = ($line->quantity * $line->unit_price) * (1 - $line->discount_percentage / 100);
            $subtotal += $lineTotal;
            $taxAmount += $lineTotal * ($line->tax_rate / 100);
        }

        $this->subtotal = $subtotal;
        $this->tax_amount = $taxAmount;
        $this->total_amount = $subtotal + $taxAmount;

        return $this->save();
    }

    public function approve(): bool
    {
        if (!$this->canBeApproved()) {
            throw new \InvalidArgumentException('Purchase order cannot be approved');
        }

        $this->status = self::STATUS_APPROVED;
        $this->approved_at = now();
        
        if (Auth::check()) {
            $this->approved_by = Auth::id();
        }

        return $this->save();
    }

    public function sendToVendor(): bool
    {
        if (!$this->canBeSent()) {
            throw new \InvalidArgumentException('Purchase order cannot be sent');
        }

        $this->status = self::STATUS_SENT;
        $this->sent_to_vendor_at = now();

        return $this->save();
    }

    public function markAsReceived(): bool
    {
        if (!$this->canBeReceived()) {
            throw new \InvalidArgumentException('Purchase order cannot be marked as received');
        }

        $this->status = self::STATUS_RECEIVED;

        return $this->save();
    }

    public function cancel(): bool
    {
        if (!$this->canBeCancelled()) {
            throw new \InvalidArgumentException('Purchase order cannot be cancelled');
        }

        $this->status = self::STATUS_CANCELLED;

        return $this->save();
    }

    public function close(): bool
    {
        if (!$this->canBeClosed()) {
            throw new \InvalidArgumentException('Purchase order cannot be closed');
        }

        $this->status = self::STATUS_CLOSED;

        return $this->save();
    }

    // === EVENTS ===

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $po) {
            if (!$po->po_number) {
                $po->po_number = $po->generatePoNumber();
            }

            if (Auth::check()) {
                $po->created_by = Auth::id();
                $po->updated_by = Auth::id();
            }
        });

        static::updating(function (PurchaseOrder $po) {
            if (Auth::check()) {
                $po->updated_by = Auth::id();
            }

            // Prevent status changes for closed/cancelled POs
            if ($po->isDirty('status') && in_array($po->getOriginal('status'), [self::STATUS_CLOSED, self::STATUS_CANCELLED])) {
                throw new \InvalidArgumentException('Cannot change status of closed or cancelled purchase order');
            }
        });

        static::deleting(function (PurchaseOrder $po) {
            if ($po->bills()->count() > 0) {
                throw new \InvalidArgumentException('Cannot delete purchase order with existing bills');
            }
        });
    }

    // === QUERY SCOPES ===

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('expected_delivery_date', '<', now())
                    ->whereNotIn('status', [self::STATUS_RECEIVED, self::STATUS_CLOSED, self::STATUS_CANCELLED]);
    }
}
