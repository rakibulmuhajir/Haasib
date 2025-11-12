<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.expenses';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'expense_category_id',
        'expense_number',
        'status',
        'title',
        'description',
        'expense_date',
        'amount',
        'currency',
        'exchange_rate',
        'employee_id',
        'vendor_id',
        'receipt_number',
        'notes',
        'rejection_reason',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'payment_date',
        'payment_reference',
        'created_by',
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'expense_category_id' => 'string',
        'employee_id' => 'string',
        'vendor_id' => 'string',
        'submitted_by' => 'string',
        'approved_by' => 'string',
        'rejected_by' => 'string',
        'created_by' => 'string',
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'expense_date' => 'date',
        'payment_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id', 'id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id', 'id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by', 'id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
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

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeReimbursed($query)
    {
        return $query->where('status', 'reimbursed');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'submitted']);
    }

    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    // Business logic methods
    public function canBeEdited()
    {
        return in_array($this->status, ['draft']);
    }

    public function canBeSubmitted()
    {
        return in_array($this->status, ['draft']);
    }

    public function canBeApproved()
    {
        return in_array($this->status, ['submitted']);
    }

    public function canBeRejected()
    {
        return in_array($this->status, ['submitted']);
    }

    public function canBePaid()
    {
        return in_array($this->status, ['approved']);
    }

    public function canBeReimbursed()
    {
        return in_array($this->status, ['approved']);
    }

    public function canBeDeleted()
    {
        return in_array($this->status, ['draft']);
    }

    public function isEmployeeExpense()
    {
        return ! is_null($this->employee_id);
    }

    public function isVendorExpense()
    {
        return ! is_null($this->vendor_id);
    }

    public function isPaid()
    {
        return in_array($this->status, ['paid', 'reimbursed']);
    }

    public function isPendingApproval()
    {
        return $this->status === 'submitted';
    }

    public function isRejected()
    {
        return $this->status === 'rejected';
    }

    // Workflow methods
    public function submit()
    {
        if (! $this->canBeSubmitted()) {
            return false;
        }

        $this->status = 'submitted';
        $this->submitted_by = auth()->id();
        $this->submitted_at = now();

        return $this->save();
    }

    public function approve($approvedBy = null)
    {
        if (! $this->canBeApproved()) {
            return false;
        }

        $this->status = 'approved';
        $this->approved_by = $approvedBy ?? auth()->id();
        $this->approved_at = now();

        return $this->save();
    }

    public function reject($reason, $rejectedBy = null)
    {
        if (! $this->canBeRejected()) {
            return false;
        }

        $this->status = 'rejected';
        $this->rejection_reason = $reason;
        $this->rejected_by = $rejectedBy ?? auth()->id();
        $this->rejected_at = now();

        return $this->save();
    }

    public function markAsPaid($paymentDate = null, $paymentReference = null)
    {
        if (! $this->canBePaid()) {
            return false;
        }

        $this->status = 'paid';
        $this->payment_date = $paymentDate ?? now();
        $this->payment_reference = $paymentReference;

        return $this->save();
    }

    public function markAsReimbursed($paymentDate = null, $paymentReference = null)
    {
        if (! $this->canBeReimbursed()) {
            return false;
        }

        $this->status = 'reimbursed';
        $this->payment_date = $paymentDate ?? now();
        $this->payment_reference = $paymentReference;

        return $this->save();
    }

    // Computed properties
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2, '.', ',');
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'paid' => 'Paid',
            'reimbursed' => 'Reimbursed',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getPayeeNameAttribute()
    {
        if ($this->employee) {
            return $this->employee->name;
        } elseif ($this->vendor) {
            return $this->vendor->display_name || $this->vendor->legal_name;
        }

        return 'Unknown';
    }

    // Save hook to generate expense number
    protected static function booted()
    {
        static::creating(function ($expense) {
            if (empty($expense->expense_number)) {
                $year = date('Y');
                $sequence = static::whereYear('expense_date', $year)
                    ->where('company_id', $expense->company_id)
                    ->count() + 1;

                $expense->expense_number = 'EXP-'.$year.'-'.str_pad($sequence, 5, '0', STR_PAD_LEFT);
            }
        });
    }
}
