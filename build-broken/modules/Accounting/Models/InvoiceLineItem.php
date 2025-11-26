<?php

namespace Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'acct.invoice_line_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'discount_type',
        'discount_value',
        'tax_rate',
        'tax_amount',
        'total',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'invoice_id' => 'string',
            'product_id' => 'string',
        ];
    }

    /**
     * Get the invoice that owns the line item.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the product for the line item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate the line item total.
     */
    public function calculateTotal(): void
    {
        $subtotal = $this->quantity * $this->unit_price;

        // Calculate discount
        $discountAmount = 0;
        if ($this->discount_type === 'percentage') {
            $discountAmount = $subtotal * ($this->discount_value / 100);
        } elseif ($this->discount_type === 'fixed') {
            $discountAmount = $this->discount_value;
        }

        $afterDiscount = $subtotal - $discountAmount;

        // Calculate tax
        $taxAmount = $afterDiscount * ($this->tax_rate / 100);

        $this->total = $afterDiscount + $taxAmount;
        $this->tax_amount = $taxAmount;

        $this->save();
    }

    /**
     * Get the discount amount.
     */
    public function getDiscountAmount(): float
    {
        $subtotal = $this->quantity * $this->unit_price;

        if ($this->discount_type === 'percentage') {
            return $subtotal * ($this->discount_value / 100);
        } elseif ($this->discount_type === 'fixed') {
            return $this->discount_value;
        }

        return 0;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\InvoiceLineItemFactory::new();
    }
}
