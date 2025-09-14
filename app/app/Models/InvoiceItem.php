<?php

namespace App\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class InvoiceItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'invoice_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'invoice_id',
        'item_id',
        'description',
        'quantity',
        'unit_price',
        'discount_amount',
        'discount_percentage',
        'subtotal',
        'total_tax',
        'total_amount',
        'tax_inclusive',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'tax_inclusive' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'quantity' => 1,
        'unit_price' => 0,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'subtotal' => 0,
        'total_tax' => 0,
        'total_amount' => 0,
        'tax_inclusive' => false,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = $model->id ?: (string) Str::uuid();
        });

        static::saving(function ($item) {
            $item->calculateTotals();
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(InvoiceItemTax::class);
    }

    public function scopeForInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    public function getSubtotalBeforeDiscount(): Money
    {
        return Money::of($this->quantity * $this->unit_price, $this->invoice->currency->code);
    }

    public function getDiscountAmount(): Money
    {
        if ($this->discount_percentage > 0) {
            return $this->getSubtotalBeforeDiscount()->multipliedBy($this->discount_percentage / 100);
        }

        return Money::of($this->discount_amount, $this->invoice->currency->code);
    }

    public function getSubtotalAfterDiscount(): Money
    {
        return $this->getSubtotalBeforeDiscount()->minus($this->getDiscountAmount());
    }

    public function getTaxableAmount(): Money
    {
        if ($this->tax_inclusive) {
            return $this->getSubtotalAfterDiscount();
        }

        return $this->getSubtotalAfterDiscount();
    }

    public function getTotalTax(): Money
    {
        $totalTax = Money::of(0, $this->invoice->currency->code);

        foreach ($this->taxes as $tax) {
            if ($this->tax_inclusive) {
                $taxAmount = $this->getSubtotalAfterDiscount()
                    ->multipliedBy($tax->rate / (100 + $tax->rate));
            } else {
                $taxAmount = $this->getTaxableAmount()->multipliedBy($tax->rate / 100);
            }
            $totalTax = $totalTax->plus($taxAmount);
        }

        return $totalTax;
    }

    public function getTotalAmount(): Money
    {
        if ($this->tax_inclusive) {
            return $this->getSubtotalAfterDiscount();
        }

        return $this->getSubtotalAfterDiscount()->plus($this->getTotalTax());
    }

    public function calculateTotals(): void
    {
        $subtotalBeforeDiscount = $this->getSubtotalBeforeDiscount();
        $discountAmount = $this->getDiscountAmount();
        $subtotalAfterDiscount = $subtotalBeforeDiscount->minus($discountAmount);
        $totalTax = $this->getTotalTax();
        $totalAmount = $this->getTotalAmount();

        $this->subtotal = $subtotalAfterDiscount->getAmount()->toFloat();
        $this->total_tax = $totalTax->getAmount()->toFloat();
        $this->total_amount = $totalAmount->getAmount()->toFloat();
    }

    public function getEffectiveTaxRate(): float
    {
        if ($this->subtotal <= 0) {
            return 0;
        }

        return ($this->total_tax / $this->subtotal) * 100;
    }

    public function isTaxable(): bool
    {
        return $this->taxes()->count() > 0;
    }

    public function getTaxBreakdown(): array
    {
        $breakdown = [];

        foreach ($this->taxes as $tax) {
            if ($this->tax_inclusive) {
                $taxAmount = $this->getSubtotalAfterDiscount()
                    ->multipliedBy($tax->rate / (100 + $tax->rate));
            } else {
                $taxAmount = $this->getTaxableAmount()->multipliedBy($tax->rate / 100);
            }

            $breakdown[] = [
                'tax_id' => $tax->id,
                'tax_name' => $tax->name,
                'rate' => $tax->rate,
                'amount' => $taxAmount->getAmount()->toFloat(),
            ];
        }

        return $breakdown;
    }

    public function applyDiscount(?float $amount = null, ?float $percentage = null): void
    {
        if ($amount !== null) {
            $this->discount_amount = min($amount, $this->getSubtotalBeforeDiscount()->getAmount()->toFloat());
            $this->discount_percentage = 0;
        } elseif ($percentage !== null) {
            $this->discount_percentage = min(max(0, $percentage), 100);
            $this->discount_amount = 0;
        }

        $this->calculateTotals();
    }

    public function addTax(string $taxId, string $name, float $rate): void
    {
        InvoiceItemTax::create([
            'invoice_item_id' => $this->id,
            'tax_id' => $taxId,
            'tax_name' => $name,
            'rate' => $rate,
            'tax_amount' => 0,
        ]);

        $this->calculateTotals();
        $this->save();
    }

    public function removeTax(string $taxId): void
    {
        $this->taxes()->where('tax_id', $taxId)->delete();
        $this->calculateTotals();
        $this->save();
    }

    public function clearTaxes(): void
    {
        $this->taxes()->delete();
        $this->calculateTotals();
        $this->save();
    }

    public function getUnitPriceWithDiscount(): Money
    {
        if ($this->quantity <= 0) {
            return Money::of(0, $this->invoice->currency->code);
        }

        return Money::of($this->subtotal / $this->quantity, $this->invoice->currency->code);
    }

    public function getDescription(): string
    {
        return $this->description ?: ($this->item?->name ?? 'No description');
    }

    public function getDisplayUnitPrice(): string
    {
        return number_format($this->unit_price, 2);
    }

    public function getDisplayQuantity(): string
    {
        return number_format($this->quantity, 4);
    }

    public function getDisplaySubtotal(): string
    {
        return number_format($this->subtotal, 2);
    }

    public function getDisplayTotalTax(): string
    {
        return number_format($this->total_tax, 2);
    }

    public function getDisplayTotalAmount(): string
    {
        return number_format($this->total_amount, 2);
    }
}
