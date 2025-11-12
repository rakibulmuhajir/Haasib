<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $table = 'acct.purchase_order_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
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
    ];

    protected $casts = [
        'id' => 'string',
        'po_id' => 'string',
        'product_id' => 'string',
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:6',
        'discount_percentage' => 'decimal:2',
        'tax_rate' => 'decimal:5',
        'line_total' => 'decimal:2',
        'received_quantity' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    // Computed properties
    public function getCalculatedLineTotalAttribute()
    {
        return ($this->quantity * $this->unit_price) * (1 - $this->discount_percentage / 100);
    }

    public function getCalculatedTaxAmountAttribute()
    {
        $lineTotal = $this->getCalculatedLineTotalAttribute();

        return $lineTotal * ($this->tax_rate / 100);
    }

    public function getCalculatedTotalWithTaxAttribute()
    {
        return $this->getCalculatedLineTotalAttribute() + $this->getCalculatedTaxAmountAttribute();
    }

    public function getFormattedQuantityAttribute()
    {
        return number_format($this->quantity, 4, '.', ',');
    }

    public function getFormattedUnitPriceAttribute()
    {
        return number_format($this->unit_price, 6, '.', ',');
    }

    public function getFormattedLineTotalAttribute()
    {
        return number_format($this->line_total, 2, '.', ',');
    }

    public function getFormattedReceivedQuantityAttribute()
    {
        return number_format($this->received_quantity, 4, '.', ',');
    }

    public function isFullyReceived()
    {
        return $this->received_quantity >= $this->quantity;
    }

    public function getRemainingQuantityAttribute()
    {
        return $this->quantity - $this->received_quantity;
    }

    public function getReceptionStatusAttribute()
    {
        if ($this->received_quantity == 0) {
            return 'not_received';
        } elseif ($this->isFullyReceived()) {
            return 'fully_received';
        } else {
            return 'partially_received';
        }
    }

    // Save hook to calculate line total
    protected static function booted()
    {
        static::saving(function ($line) {
            $line->line_total = ($line->quantity * $line->unit_price) * (1 - $line->discount_percentage / 100);
        });
    }
}
