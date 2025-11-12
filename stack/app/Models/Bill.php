<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.bills';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'vendor_id',
        'bill_number',
        'status',
        'bill_date',
        'due_date',
        'currency',
        'exchange_rate',
        'subtotal',
        'tax_total',
        'total_amount',
        'amount_paid',
        'balance_due',
        'vendor_bill_number',
        'notes',
        'internal_notes',
        'purchase_order_id',
        'created_by',
        'approved_by',
        'approved_at',
        'sent_to_vendor_at',
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'vendor_id' => 'string',
        'purchase_order_id' => 'string',
        'created_by' => 'string',
        'approved_by' => 'string',
        'bill_date' => 'date',
        'due_date' => 'date',
        'exchange_rate' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'approved_at' => 'datetime',
        'sent_to_vendor_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'id');
    }

    public function lines()
    {
        return $this->hasMany(BillLine::class, 'bill_id', 'id')->orderBy('line_number');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    public function payments()
    {
        return $this->morphMany(BillPayment::class, 'payable');
    }

    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeWithStatus($query, array $statuses)
    {
        return $query->whereIn('status', $statuses);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'pending_approval', 'approved', 'partial']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereIn('status', ['approved', 'partial']);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', ['approved', 'partial', 'overdue']);
    }

    // Business logic methods
    public function canBeEdited()
    {
        return in_array($this->status, ['draft', 'pending_approval']);
    }

    public function canBeApproved()
    {
        return in_array($this->status, ['draft', 'pending_approval']);
    }

    public function canBeSent()
    {
        return in_array($this->status, ['approved']);
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['draft', 'pending_approval', 'approved']);
    }

    public function canReceivePayment()
    {
        return in_array($this->status, ['approved', 'partial', 'overdue']);
    }

    public function isOverdue()
    {
        return $this->due_date < now() && in_array($this->status, ['approved', 'partial']);
    }

    public function recalculateTotals()
    {
        $this->load('lines');

        $this->subtotal = $this->lines->sum('line_total');
        $this->tax_total = $this->lines->sum('tax_amount');
        $this->total_amount = $this->subtotal + $this->tax_total;
        $this->balance_due = $this->total_amount - $this->amount_paid;

        // Update status based on payment
        if ($this->amount_paid >= $this->total_amount) {
            $this->status = 'paid';
        } elseif ($this->amount_paid > 0) {
            $this->status = 'partial';
        } elseif ($this->isOverdue()) {
            $this->status = 'overdue';
        }

        $this->save();
    }

    public function applyPayment($amount)
    {
        if ($amount <= 0) {
            return false;
        }

        $newAmountPaid = $this->amount_paid + $amount;

        if ($newAmountPaid > $this->total_amount) {
            return false; // Payment exceeds total amount
        }

        $this->amount_paid = $newAmountPaid;
        $this->balance_due = $this->total_amount - $this->amount_paid;

        // Update status
        if ($this->amount_paid >= $this->total_amount) {
            $this->status = 'paid';
        } elseif ($this->amount_paid > 0) {
            $this->status = 'partial';
        }

        return $this->save();
    }

    // Computed properties
    public function getFormattedSubtotalAttribute()
    {
        return number_format($this->subtotal, 2, '.', ',');
    }

    public function getFormattedTaxTotalAttribute()
    {
        return number_format($this->tax_total, 2, '.', ',');
    }

    public function getFormattedTotalAmountAttribute()
    {
        return number_format($this->total_amount, 2, '.', ',');
    }

    public function getFormattedAmountPaidAttribute()
    {
        return number_format($this->amount_paid, 2, '.', ',');
    }

    public function getFormattedBalanceDueAttribute()
    {
        return number_format($this->balance_due, 2, '.', ',');
    }

    public function getPaymentStatusAttribute()
    {
        if ($this->status === 'paid') {
            return 'fully_paid';
        } elseif ($this->amount_paid > 0) {
            return 'partially_paid';
        } else {
            return 'unpaid';
        }
    }

    // Save hook to generate bill number
    protected static function booted()
    {
        static::creating(function ($bill) {
            if (empty($bill->bill_number)) {
                $year = date('Y');
                $sequence = static::whereYear('bill_date', $year)
                    ->where('company_id', $bill->company_id)
                    ->count() + 1;

                $bill->bill_number = 'BILL-'.$year.'-'.str_pad($sequence, 5, '0', STR_PAD_LEFT);
            }

            // Set initial amounts
            if ($bill->amount_paid === 0 && $bill->balance_due === 0) {
                $bill->balance_due = $bill->total_amount;
            }
        });

        static::saving(function ($bill) {
            // Ensure balance_due is always correct
            $bill->balance_due = $bill->total_amount - $bill->amount_paid;
        });
    }
}
