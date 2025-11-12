<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillLine extends Model
{
    use HasFactory;

    protected $table = 'acct.bill_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
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
    ];

    protected $casts = [
        'id' => 'string',
        'bill_id' => 'string',
        'purchase_order_line_id' => 'string',
        'product_id' => 'string',
        'account_id' => 'string',
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:6',
        'discount_percentage' => 'decimal:2',
        'tax_rate' => 'decimal:3',
        'line_total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_with_tax' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function bill()
    {
        return $this->belongsTo(Bill::class, 'bill_id', 'id');
    }

    public function purchaseOrderLine()
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id', 'id');
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

    public function getFormattedTaxAmountAttribute()
    {
        return number_format($this->tax_amount, 2, '.', ',');
    }

    public function getFormattedTotalWithTaxAttribute()
    {
        return number_format($this->total_with_tax, 2, '.', ',');
    }

    // Save hook to calculate totals
    protected static function booted()
    {
        static::saving(function ($line) {
            $calculatedLineTotal = ($line->quantity * $line->unit_price) * (1 - $line->discount_percentage / 100);
            $calculatedTaxAmount = $calculatedLineTotal * ($line->tax_rate / 100);

            $line->line_total = $calculatedLineTotal;
            $line->tax_amount = $calculatedTaxAmount;
            $line->total_with_tax = $calculatedLineTotal + $calculatedTaxAmount;
        });
    }
}
