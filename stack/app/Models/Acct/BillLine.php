<?php

namespace App\Models\Acct;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditLogging;
use App\Models\User;
use App\Models\Company;
use App\Models\Bill;
use App\Models\PurchaseOrderLine;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class BillLine extends Model
{
    use HasFactory, HasUuids, BelongsToCompany, SoftDeletes, AuditLogging;

    protected $table = 'acct.bill_lines';

    protected $fillable = [
        'company_id',
        'bill_id',
        'line_number',
        'purchase_order_line_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'discount_percentage',
        'tax_rate',
        'line_total',
        'tax_amount',
        'total_with_tax',
        'account_id',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:6',
        'discount_percentage' => 'decimal:2',
        'tax_rate' => 'decimal:3',
        'line_total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_with_tax' => 'decimal:2',
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
        'formatted_tax_amount',
        'formatted_total_with_tax',
        'has_discount',
        'is_taxable',
    ];

    // UUID Configuration
    protected $keyType = 'string';
    public $incrementing = false;

    // === RELATIONSHIPS ===

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
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

    // === SCOPES ===

    public function scopeByBill(Builder $query, string $billId): Builder
    {
        return $query->where('bill_id', $billId);
    }

    public function scopeByProduct(Builder $query, string $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByAccount(Builder $query, string $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeByPurchaseOrderLine(Builder $query, string $poLineId): Builder
    {
        return $query->where('purchase_order_line_id', $poLineId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('line_number');
    }

    public function scopeWithDiscount(Builder $query): Builder
    {
        return $query->where('discount_percentage', '>', 0);
    }

    public function scopeTaxable(Builder $query): Builder
    {
        return $query->where('tax_rate', '>', 0);
    }

    // === BUSINESS LOGIC METHODS ===

    public function hasDiscount(): bool
    {
        return $this->discount_percentage > 0;
    }

    public function isTaxable(): bool
    {
        return $this->tax_rate > 0;
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

    public function getFormattedTaxAmountAttribute(): string
    {
        return number_format($this->tax_amount, 2, '.', ',');
    }

    public function getFormattedTotalWithTaxAttribute(): string
    {
        return number_format($this->total_with_tax, 2, '.', ',');
    }

    public function getHasDiscountAttribute(): bool
    {
        return $this->hasDiscount();
    }

    public function getIsTaxableAttribute(): bool
    {
        return $this->isTaxable();
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

    public function recalculateTotals(): bool
    {
        $this->line_total = $this->calculateLineTotal();
        $this->tax_amount = $this->calculateTaxAmount();
        $this->total_with_tax = $this->calculateTotalWithTax();

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

    public function setTaxRate(float $rate): bool
    {
        if ($rate < 0) {
            throw new \InvalidArgumentException('Tax rate cannot be negative');
        }

        $this->tax_rate = $rate;
        return $this->recalculateTotals();
    }

    // === EVENTS ===

    protected static function booted(): void
    {
        static::creating(function (BillLine $line) {
            if (Auth::check()) {
                $line->created_by = Auth::id();
                $line->updated_by = Auth::id();
            }

            // Auto-calculate totals if not provided
            if (!isset($line->line_total) || !isset($line->tax_amount) || !isset($line->total_with_tax)) {
                $line->recalculateTotals();
            }
        });

        static::updating(function (BillLine $line) {
            if (Auth::check()) {
                $line->updated_by = Auth::id();
            }

            // Recalculate totals when relevant fields change
            if ($line->isDirty(['quantity', 'unit_price', 'discount_percentage', 'tax_rate'])) {
                $line->recalculateTotals();
            }
        });

        static::saving(function (BillLine $line) {
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

    public static function createFromPurchaseOrderLine(PurchaseOrderLine $poLine, Bill $bill): self
    {
        return static::create([
            'company_id' => $bill->company_id,
            'bill_id' => $bill->id,
            'purchase_order_line_id' => $poLine->id,
            'product_id' => $poLine->product_id,
            'description' => $poLine->description,
            'quantity' => $poLine->quantity,
            'unit_price' => $poLine->unit_price,
            'discount_percentage' => $poLine->discount_percentage,
            'tax_rate' => $poLine->tax_rate,
            'account_id' => $poLine->account_id,
            'notes' => 'Created from PO: ' . $poLine->purchase_order_id,
        ]);
    }
}
