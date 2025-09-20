<?php

namespace App\Models;

use Brick\Money\Money;
use Brick\Math\RoundingMode;
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

    protected $primaryKey = 'invoice_item_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'invoice_id',
        'item_id',
        'description',
        'quantity',
        'unit_price',
        'discount_percentage',
        'discount_amount',
        'line_total',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'line_total' => 'decimal:2',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'quantity' => 1,
        'unit_price' => 0,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'line_total' => 0,
        'sort_order' => 1,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($item) {
            if (empty($item->invoice_item_id)) {
                $item->invoice_item_id = (string) Str::uuid();
            }
        });

        static::saving(function ($item) {
            // Only calculate totals if we have the necessary data
            if ($item->invoice_id && ($item->isDirty('quantity') || $item->isDirty('unit_price') || $item->isDirty('discount_percentage') || $item->isDirty('discount_amount'))) {
                // Load the invoice relationship if not already loaded
                if (! $item->relationLoaded('invoice')) {
                    $item->load('invoice');
                }

                if ($item->invoice && $item->invoice->currency) {
                    $item->calculateTotals();
                }
            }
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(InvoiceItemTax::class, 'invoice_item_id', 'invoice_item_id');
    }

    public function scopeForInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    public function getSubtotalBeforeDiscount(): Money
    {
        if (! $this->invoice || ! $this->invoice->currency) {
            throw new \LogicException('Cannot calculate totals without an associated invoice and currency.');
        }

        return Money::of($this->quantity * $this->unit_price, $this->invoice->currency->code);
    }

    public function getDiscountAmount(): Money
    {
        if ($this->discount_percentage > 0) {
            return $this->getSubtotalBeforeDiscount()->multipliedBy($this->discount_percentage / 100);
        }

        if (! $this->invoice || ! $this->invoice->currency) {
            throw new \LogicException('Cannot calculate totals without an associated invoice and currency.');
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
        if (! $this->invoice || ! $this->invoice->currency) {
            throw new \LogicException('Cannot calculate totals without an associated invoice and currency.');
        }
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

        // Round to 2 decimals for storage
        $this->line_total = $subtotalAfterDiscount->getAmount()->toScale(2, RoundingMode::HALF_UP)->toFloat();
    }

    public function getEffectiveTaxRate(): float
    {
        // For seeder purposes, simplify tax calculation
        return 0;
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
            if (! $this->invoice || ! $this->invoice->currency) {
                throw new \LogicException('Cannot calculate totals without an associated invoice and currency.');
            }

            return Money::of(0, $this->invoice->currency->code);
        }

        if (! $this->invoice || ! $this->invoice->currency) {
            throw new \LogicException('Cannot calculate totals without an associated invoice and currency.');
        }

        return Money::of($this->line_total / $this->quantity, $this->invoice->currency->code);
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
        try {
            return number_format($this->getSubtotalAfterDiscount()->getAmount()->toFloat(), 2);
        } catch (\Throwable $e) {
            return number_format(0, 2);
        }
    }

    public function getDisplayTotalTax(): string
    {
        try {
            return number_format($this->getTotalTax()->getAmount()->toFloat(), 2);
        } catch (\Throwable $e) {
            return number_format(0, 2);
        }
    }

    public function getDisplayTotalAmount(): string
    {
        try {
            return number_format($this->getTotalAmount()->getAmount()->toFloat(), 2);
        } catch (\Throwable $e) {
            return number_format(0, 2);
        }
    }
}
