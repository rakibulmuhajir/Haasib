<?php

namespace App\Models\Acct;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditLogging;
use App\Models\User;
use App\Models\Company;
use App\Models\Acct\PurchaseOrder;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PurchaseOrderLine extends Model
{
    use HasFactory, HasUuids, BelongsToCompany, SoftDeletes, AuditLogging;

    protected $table = 'acct.purchase_order_lines';

    protected $fillable = [
        'company_id',
        'po_id',
        'line_number',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'discount_percentage',
        'tax_rate',
        'line_total',
        'received_quantity',
        'account_id',
        'expected_delivery_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:6',
        'discount_percentage' => 'decimal:2',
        'tax_rate' => 'decimal:5',
        'line_total' => 'decimal:2',
        'received_quantity' => 'decimal:4',
        'expected_delivery_date' => 'date',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'updated_by',
    ];

    protected $appends = [
        'calculated_line_total',
        'calculated_tax_amount',
        'calculated_total_with_tax',
        'formatted_quantity',
        'formatted_unit_price',
        'formatted_line_total',
        'formatted_received_quantity',
        'remaining_quantity',
        'is_fully_received',
        'reception_status',
        'has_discount',
        'is_overdue',
    ];

    // UUID Configuration
    protected $keyType = 'string';
    public $incrementing = false;

    // === RELATIONSHIPS ===

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function billLines(): HasMany
    {
        return $this->hasMany(BillLine::class, 'purchase_order_line_id');
    }

    public function receptions(): HasMany
    {
        return $this->hasMany(PurchaseOrderLineReception::class, 'po_line_id');
    }

    // === SCOPES ===

    public function scopeByPurchaseOrder(Builder $query, string $poId): Builder
    {
        return $query->where('po_id', $poId);
    }

    public function scopeByProduct(Builder $query, string $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByAccount(Builder $query, string $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('line_number');
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->whereColumn('received_quantity', '>=', 'quantity');
    }

    public function scopePartiallyReceived(Builder $query): Builder
    {
        return $query->whereColumn('received_quantity', '>', 0)
                    ->whereColumn('received_quantity', '<', 'quantity');
    }

    public function scopeNotReceived(Builder $query): Builder
    {
        return $query->whereColumn('received_quantity', '=', 0);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('expected_delivery_date', '<', now())
                    ->whereNotIn('received_quantity', function ($q) {
                        $q->whereColumn('received_quantity', '>=', 'quantity');
                    });
    }

    public function scopeWithDiscount(Builder $query): Builder
    {
        return $query->where('discount_percentage', '>', 0);
    }

    // === BUSINESS LOGIC METHODS ===

    public function hasDiscount(): bool
    {
        return $this->discount_percentage > 0;
    }

    public function isFullyReceived(): bool
    {
        return $this->received_quantity >= $this->quantity;
    }

    public function isPartiallyReceived(): bool
    {
        return $this->received_quantity > 0 && $this->received_quantity < $this->quantity;
    }

    public function isNotReceived(): bool
    {
        return $this->received_quantity == 0;
    }

    public function isOverdue(): bool
    {
        return $this->expected_delivery_date && 
               $this->expected_delivery_date->isPast() && 
               !$this->isFullyReceived();
    }

    public function getRemainingQuantity(): float
    {
        return max(0, $this->quantity - $this->received_quantity);
    }

    public function getReceptionPercentage(): float
    {
        if ($this->quantity == 0) {
            return 0;
        }

        return min(100, ($this->received_quantity / $this->quantity) * 100);
    }

    public function getDiscountAmount(): float
    {
        return ($this->quantity * $this->unit_price) * ($this->discount_percentage / 100);
    }

    public function getNetAmount(): float
    {
        return ($this->quantity * $this->unit_price) - $this->getDiscountAmount();
    }

    public function calculateLineTotal(): float
    {
        return ($this->quantity * $this->unit_price) * (1 - $this->discount_percentage / 100);
    }

    public function calculateTaxAmount(): float
    {
        return $this->calculateLineTotal() * ($this->tax_rate / 100);
    }

    public function calculateTotalWithTax(): float
    {
        return $this->calculateLineTotal() + $this->calculateTaxAmount();
    }

    public function canBeReceived(): bool
    {
        return $this->getRemainingQuantity() > 0;
    }

    public function canBeReceivedInFull(): bool
    {
        return $this->received_quantity == 0;
    }

    // === MUTATORS & ACCESSORS ===

    public function getCalculatedLineTotalAttribute(): float
    {
        return $this->calculateLineTotal();
    }

    public function getCalculatedTaxAmountAttribute(): float
    {
        return $this->calculateTaxAmount();
    }

    public function getCalculatedTotalWithTaxAttribute(): float
    {
        return $this->calculateTotalWithTax();
    }

    public function getFormattedQuantityAttribute(): string
    {
        return number_format($this->quantity, 4, '.', ',');
    }

    public function getFormattedUnitPriceAttribute(): string
    {
        return number_format($this->unit_price, 6, '.', ',');
    }

    public function getFormattedLineTotalAttribute(): string
    {
        return number_format($this->line_total, 2, '.', ',');
    }

    public function getFormattedReceivedQuantityAttribute(): string
    {
        return number_format($this->received_quantity, 4, '.', ',');
    }

    public function getRemainingQuantityAttribute(): string
    {
        return number_format($this->getRemainingQuantity(), 4, '.', ',');
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->isFullyReceived();
    }

    public function getReceptionStatusAttribute(): string
    {
        if ($this->isNotReceived()) {
            return 'not_received';
        } elseif ($this->isFullyReceived()) {
            return 'fully_received';
        } else {
            return 'partially_received';
        }
    }

    public function getHasDiscountAttribute(): bool
    {
        return $this->hasDiscount();
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->isOverdue();
    }

    public function getDescriptionAttribute(): string
    {
        return trim($this->attributes['description'] ?? '');
    }

    public function setDescriptionAttribute(string $value): void
    {
        $this->attributes['description'] = trim($value);
    }

    public function getNotesAttribute(): ?string
    {
        return $this->attributes['notes'] ? trim($this->attributes['notes']) : null;
    }

    public function setNotesAttribute(?string $value): void
    {
        $this->attributes['notes'] = $value ? trim($value) : null;
    }

    // === BUSINESS OPERATIONS ===

    public function receiveQuantity(float $quantity, ?string $notes = null): bool
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Received quantity must be greater than 0');
        }

        if ($quantity > $this->getRemainingQuantity()) {
            throw new \InvalidArgumentException('Cannot receive more than remaining quantity');
        }

        $this->received_quantity += $quantity;
        
        if ($notes) {
            $this->notes = trim($notes);
        }

        return $this->save();
    }

    public function receiveAll(?string $notes = null): bool
    {
        return $this->receiveQuantity($this->getRemainingQuantity(), $notes);
    }

    public function adjustQuantity(float $newQuantity): bool
    {
        if ($newQuantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than 0');
        }

        if ($newQuantity < $this->received_quantity) {
            throw new \InvalidArgumentException('Cannot reduce quantity below received quantity');
        }

        $this->quantity = $newQuantity;
        return $this->recalculateTotals();
    }

    public function recalculateTotals(): bool
    {
        $this->line_total = $this->calculateLineTotal();
        return $this->save();
    }

    public function applyDiscount(float $percentage): bool
    {
        if ($percentage < 0 || $percentage > 100) {
            throw new \InvalidArgumentException('Discount percentage must be between 0 and 100');
        }

        $this->discount_percentage = $percentage;
        return $this->recalculateTotals();
    }

    // === EVENTS ===

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrderLine $line) {
            if (Auth::check()) {
                $line->created_by = Auth::id();
                $line->updated_by = Auth::id();
            }

            // Auto-calculate totals if not provided
            if (!isset($line->line_total)) {
                $line->recalculateTotals();
            }

            // Ensure received_quantity doesn't exceed quantity
            if ($line->received_quantity > $line->quantity) {
                $line->received_quantity = 0;
            }
        });

        static::updating(function (PurchaseOrderLine $line) {
            if (Auth::check()) {
                $line->updated_by = Auth::id();
            }

            // Recalculate totals when relevant fields change
            if ($line->isDirty(['quantity', 'unit_price', 'discount_percentage'])) {
                $line->recalculateTotals();
            }

            // Prevent reducing quantity below received quantity
            if ($line->isDirty('quantity') && $line->quantity < $line->received_quantity) {
                throw new \InvalidArgumentException('Cannot reduce quantity below received quantity');
            }

            // Prevent receiving more than quantity
            if ($line->isDirty('received_quantity') && $line->received_quantity > $line->quantity) {
                throw new \InvalidArgumentException('Received quantity cannot exceed ordered quantity');
            }
        });

        static::saving(function (PurchaseOrderLine $line) {
            // Validate business rules
            if ($line->quantity <= 0) {
                throw new \InvalidArgumentException('Quantity must be greater than 0');
            }

            if ($line->unit_price < 0) {
                throw new \InvalidArgumentException('Unit price cannot be negative');
            }

            if ($line->discount_percentage < 0 || $line->discount_percentage > 100) {
                throw new \InvalidArgumentException('Discount percentage must be between 0 and 100');
            }

            if ($line->tax_rate < 0) {
                throw new \InvalidArgumentException('Tax rate cannot be negative');
            }

            if ($line->received_quantity < 0) {
                throw new \InvalidArgumentException('Received quantity cannot be negative');
            }
        });
    }

    // === QUERY SCOPES ===

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('description', 'like', "%{$term}%")
              ->orWhere('notes', 'like', "%{$term}%");
        });
    }

    // === STATIC METHODS ===

    public static function createFromProduct(Product $product, PurchaseOrder $po, array $data): self
    {
        return static::create([
            'company_id' => $po->company_id,
            'po_id' => $po->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => $data['quantity'] ?? 1,
            'unit_price' => $data['unit_price'] ?? $product->purchase_price,
            'account_id' => $data['account_id'] ?? null,
            'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
