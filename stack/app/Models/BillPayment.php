<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BillPayment extends Model
{
    use HasFactory;

    protected $table = 'acct.bill_payments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'payment_number',
        'payment_type',
        'payment_date',
        'amount',
        'currency',
        'exchange_rate',
        'payment_method',
        'status',
        'description',
        'notes',
        'reference_number',
        'payment_details',
        'payable_id',
        'payable_type',
        'vendor_id',
        'paid_by',
        'created_by',
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'payable_id' => 'string',
        'vendor_id' => 'string',
        'paid_by' => 'string',
        'created_by' => 'string',
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'payment_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo('payable');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'id');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
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

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeBillPayments($query)
    {
        return $query->where('payment_type', 'bill_payment');
    }

    public function scopeExpenseReimbursements($query)
    {
        return $query->where('payment_type', 'expense_reimbursement');
    }

    public function scopeVendorPayments($query)
    {
        return $query->where('payment_type', 'vendor_payment');
    }

    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeByVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    // Business logic methods
    public function isBillPayment()
    {
        return $this->payment_type === 'bill_payment';
    }

    public function isExpenseReimbursement()
    {
        return $this->payment_type === 'expense_reimbursement';
    }

    public function isVendorPayment()
    {
        return $this->payment_type === 'vendor_payment';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    public function isFailed()
    {
        return $this->status === 'failed';
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['pending']);
    }

    public function canBeCompleted()
    {
        return in_array($this->status, ['pending']);
    }

    public function markAsCompleted()
    {
        $this->status = 'completed';

        return $this->save();
    }

    public function markAsCancelled()
    {
        if (! $this->canBeCancelled()) {
            return false;
        }

        $this->status = 'cancelled';

        return $this->save();
    }

    public function markAsFailed()
    {
        $this->status = 'failed';

        return $this->save();
    }

    // Payment processing
    public function applyPayment()
    {
        if (! $this->isCompleted()) {
            return false;
        }

        try {
            \DB::beginTransaction();

            // Apply payment to bill or expense
            if ($this->payable) {
                if ($this->payable instanceof Bill) {
                    $this->payable->applyPayment($this->amount);
                } elseif ($this->payable instanceof Expense) {
                    if ($this->isExpenseReimbursement()) {
                        $this->payable->markAsReimbursed($this->payment_date, $this->reference_number);
                    } else {
                        $this->payable->markAsPaid($this->payment_date, $this->reference_number);
                    }
                }
            }

            \DB::commit();

            return true;
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    public function reversePayment()
    {
        if (! $this->isCompleted()) {
            return false;
        }

        try {
            \DB::beginTransaction();

            // Reverse payment from bill or expense
            if ($this->payable) {
                if ($this->payable instanceof Bill) {
                    $this->payable->amount_paid -= $this->amount;
                    $this->payable->balance_due += $this->amount;
                    $this->payable->recalculateTotals();
                } elseif ($this->payable instanceof Expense) {
                    $this->payable->status = 'approved';
                    $this->payable->payment_date = null;
                    $this->payable->payment_reference = null;
                }
            }

            $this->markAsCancelled();

            \DB::commit();

            return true;
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    // Computed properties
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2, '.', ',');
    }

    public function getPaymentMethodLabelAttribute()
    {
        $methods = [
            'cash' => 'Cash',
            'check' => 'Check',
            'bank_transfer' => 'Bank Transfer',
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
            'other' => 'Other',
        ];

        return $methods[$this->payment_method] ?? $this->payment_method;
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Pending',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'failed' => 'Failed',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getPaymentTypeLabelAttribute()
    {
        $types = [
            'bill_payment' => 'Bill Payment',
            'expense_reimbursement' => 'Expense Reimbursement',
            'vendor_payment' => 'Direct Vendor Payment',
        ];

        return $types[$this->payment_type] ?? $this->payment_type;
    }

    public function getPayeeNameAttribute()
    {
        if ($this->vendor) {
            return $this->vendor->display_name || $this->vendor->legal_name;
        } elseif ($this->payable) {
            if ($this->payable instanceof Bill && $this->payable->vendor) {
                return $this->payable->vendor->display_name || $this->payable->vendor->legal_name;
            } elseif ($this->payable instanceof Expense && $this->payable->employee) {
                return $this->payable->employee->name;
            }
        }

        return 'Unknown';
    }

    // Save hook to generate payment number
    protected static function booted()
    {
        static::creating(function ($payment) {
            if (empty($payment->payment_number)) {
                $year = date('Y');
                $prefix = '';

                switch ($payment->payment_type) {
                    case 'bill_payment':
                        $prefix = 'BP';
                        break;
                    case 'expense_reimbursement':
                        $prefix = 'ER';
                        break;
                    case 'vendor_payment':
                        $prefix = 'VP';
                        break;
                }

                $sequence = static::whereYear('payment_date', $year)
                    ->where('company_id', $payment->company_id)
                    ->where('payment_type', $payment->payment_type)
                    ->count() + 1;

                $payment->payment_number = $prefix.'-'.$year.'-'.str_pad($sequence, 5, '0', STR_PAD_LEFT);
            }
        });

        static::updated(function ($payment) {
            // Auto-apply payment when status changes to completed
            if ($payment->wasChanged('status') && $payment->status === 'completed') {
                $payment->applyPayment();
            }
        });
    }
}
