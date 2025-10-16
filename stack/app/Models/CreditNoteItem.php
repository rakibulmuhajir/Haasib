<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'acct.credit_note_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'credit_note_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'discount_amount',
        'total_amount',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'credit_note_id' => 'string',
        ];
    }

    /**
     * Get the credit note that owns the item.
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * Calculate the line item subtotal before tax and discount.
     */
    public function getSubtotalAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * Calculate the tax amount for the line item.
     */
    public function getTaxAmountAttribute(): float
    {
        $subtotal = $this->subtotal;
        $discountedSubtotal = $subtotal - $this->discount_amount;

        return $discountedSubtotal * ($this->tax_rate / 100);
    }

    /**
     * Calculate the final total after tax and discount.
     */
    public function getFinalTotalAttribute(): float
    {
        return $this->subtotal - $this->discount_amount + $this->tax_amount;
    }
}
