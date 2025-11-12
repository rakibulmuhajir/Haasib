<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $table = 'acct.purchase_orders';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
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
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'vendor_id' => 'string',
        'approved_by' => 'string',
        'created_by' => 'string',
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'sent_to_vendor_at' => 'datetime',
        'exchange_rate' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function lines()
    {
        return $this->hasMany(PurchaseOrderLine::class, 'po_id', 'id');
    }

    public function bills()
    {
        return $this->hasMany(Bill::class, 'po_id', 'id');
    }

    // Computed properties
    public function getFormattedSubtotalAttribute()
    {
        return number_format($this->subtotal, 2, '.', ',');
    }

    public function getFormattedTaxAmountAttribute()
    {
        return number_format($this->tax_amount, 2, '.', ',');
    }

    public function getFormattedTotalAmountAttribute()
    {
        return number_format($this->total_amount, 2, '.', ',');
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'draft' => 'gray',
            'pending_approval' => 'orange',
            'approved' => 'blue',
            'sent' => 'purple',
            'partial_received' => 'yellow',
            'received' => 'green',
            'closed' => 'teal',
            'cancelled' => 'red',
        ];

        return $colors[$this->status] ?? 'gray';
    }

    public function canBeEdited()
    {
        return in_array($this->status, ['draft', 'pending_approval']);
    }

    public function canBeApproved()
    {
        return $this->status === 'pending_approval';
    }

    public function canBeSent()
    {
        return $this->status === 'approved';
    }

    public function canBeReceived()
    {
        return in_array($this->status, ['sent', 'partial_received']);
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['draft', 'pending_approval', 'approved', 'sent']);
    }

    // Calculate totals from lines
    public function recalculateTotals()
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
}
