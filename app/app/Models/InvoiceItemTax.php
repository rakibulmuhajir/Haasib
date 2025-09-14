<?php

namespace App\Models;

use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class InvoiceItemTax extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'invoice_item_taxes';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'invoice_item_id',
        'tax_id',
        'tax_name',
        'rate',
        'tax_amount',
        'taxable_amount',
        'is_compound',
        'compound_order',
        'metadata',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'is_compound' => 'boolean',
        'compound_order' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'rate' => 0,
        'tax_amount' => 0,
        'taxable_amount' => 0,
        'is_compound' => false,
        'compound_order' => 0,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = $model->id ?: (string) Str::uuid();
        });

        static::saving(function ($tax) {
            $tax->calculateTaxAmount();
        });
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function scopeForInvoiceItem($query, $invoiceItemId)
    {
        return $query->where('invoice_item_id', $invoiceItemId);
    }

    public function scopeByCompoundOrder($query)
    {
        return $query->orderBy('compound_order');
    }

    public function scopeCompound($query, bool $compound = true)
    {
        return $query->where('is_compound', $compound);
    }

    public function scopeNonCompound($query)
    {
        return $query->where('is_compound', false);
    }

    public function calculateTaxAmount(): void
    {
        if (! $this->invoiceItem) {
            return;
        }

        $invoiceItem = $this->invoiceItem;
        $currency = $invoiceItem->invoice->currency->code;

        if ($this->is_compound) {
            $this->calculateCompoundTax();
        } else {
            $this->calculateStandardTax();
        }
    }

    protected function calculateStandardTax(): void
    {
        $invoiceItem = $this->invoiceItem;
        $currency = $invoiceItem->invoice->currency->code;

        $taxableAmount = $invoiceItem->getSubtotalAfterDiscount();

        if ($invoiceItem->tax_inclusive) {
            $taxAmount = $taxableAmount->multipliedBy($this->rate / (100 + $this->rate));
        } else {
            $taxAmount = $taxableAmount->multipliedBy($this->rate / 100);
        }

        $this->taxable_amount = $taxableAmount->getAmount()->toFloat();
        $this->tax_amount = $taxAmount->getAmount()->toFloat();
    }

    protected function calculateCompoundTax(): void
    {
        $invoiceItem = $this->invoiceItem;
        $currency = $invoiceItem->invoice->currency->code;

        $baseAmount = $invoiceItem->getSubtotalAfterDiscount();

        $precedingTaxes = $invoiceItem->taxes()
            ->where('is_compound', true)
            ->where('compound_order', '<', $this->compound_order)
            ->get();

        foreach ($precedingTaxes as $precedingTax) {
            $precedingTaxAmount = Money::of($precedingTax->tax_amount, $currency);
            $baseAmount = $baseAmount->plus($precedingTaxAmount);
        }

        if ($invoiceItem->tax_inclusive) {
            $taxAmount = $baseAmount->multipliedBy($this->rate / (100 + $this->rate));
        } else {
            $taxAmount = $baseAmount->multipliedBy($this->rate / 100);
        }

        $this->taxable_amount = $baseAmount->getAmount()->toFloat();
        $this->tax_amount = $taxAmount->getAmount()->toFloat();
    }

    public function getEffectiveRate(): float
    {
        if ($this->taxable_amount <= 0) {
            return 0;
        }

        return ($this->tax_amount / $this->taxable_amount) * 100;
    }

    public function isVat(): bool
    {
        return stripos($this->tax_name, 'vat') !== false ||
               stripos($this->tax_name, 'value added') !== false;
    }

    public function isGst(): bool
    {
        return stripos($this->tax_name, 'gst') !== false ||
               stripos($this->tax_name, 'goods and services') !== false;
    }

    public function isSalesTax(): bool
    {
        return stripos($this->tax_name, 'sales tax') !== false ||
               stripos($this->tax_name, 'sales') !== false;
    }

    public function isServiceTax(): bool
    {
        return stripos($this->tax_name, 'service tax') !== false ||
               stripos($this->tax_name, 'service') !== false;
    }

    public function getDisplayRate(): string
    {
        return number_format($this->rate, 2).'%';
    }

    public function getDisplayTaxAmount(): string
    {
        return number_format($this->tax_amount, 2);
    }

    public function getDisplayTaxableAmount(): string
    {
        return number_format($this->taxable_amount, 2);
    }

    public function getTaxType(): string
    {
        if ($this->isVat()) {
            return 'VAT';
        }
        if ($this->isGst()) {
            return 'GST';
        }
        if ($this->isSalesTax()) {
            return 'Sales Tax';
        }
        if ($this->isServiceTax()) {
            return 'Service Tax';
        }

        return 'Tax';
    }

    public function getDescription(): string
    {
        $description = $this->tax_name;

        if ($this->is_compound) {
            $description .= ' (Compound)';
        }

        $description .= ' @ '.$this->getDisplayRate();

        return $description;
    }

    public function setCompoundOrder(int $order): void
    {
        $this->is_compound = true;
        $this->compound_order = $order;
        $this->save();
    }

    public function setAsStandardTax(): void
    {
        $this->is_compound = false;
        $this->compound_order = 0;
        $this->save();
    }

    public function getCalculationMethod(): string
    {
        return $this->is_compound ? 'Compound' : 'Standard';
    }

    public function validateCompoundOrder(): bool
    {
        if (! $this->is_compound) {
            return true;
        }

        $invoiceItem = $this->invoiceItem;
        if (! $invoiceItem) {
            return true;
        }

        $compoundTaxes = $invoiceItem->taxes()
            ->where('is_compound', true)
            ->where('id', '!=', $this->id)
            ->get();

        foreach ($compoundTaxes as $tax) {
            if ($tax->compound_order === $this->compound_order) {
                return false;
            }
        }

        return true;
    }

    public function getMaximumCompoundOrder(): int
    {
        $invoiceItem = $this->invoiceItem;
        if (! $invoiceItem) {
            return 0;
        }

        $maxOrder = $invoiceItem->taxes()
            ->where('is_compound', true)
            ->max('compound_order');

        return $maxOrder ?? 0;
    }

    public function getCompoundTaxesBeforeThis(): array
    {
        if (! $this->is_compound) {
            return [];
        }

        $invoiceItem = $this->invoiceItem;
        if (! $invoiceItem) {
            return [];
        }

        return $invoiceItem->taxes()
            ->where('is_compound', true)
            ->where('compound_order', '<', $this->compound_order)
            ->orderBy('compound_order')
            ->get()
            ->toArray();
    }
}
