<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.tax_returns';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'tax_agency_id',
        'return_number',
        'return_type',
        'filing_frequency',
        'filing_period_start',
        'filing_period_end',
        'due_date',
        'filing_date',
        'status',
        'filing_method',
        'confirmation_number',
        'total_sales',
        'total_purchases',
        'output_tax',
        'input_tax',
        'tax_due',
        'penalty',
        'interest',
        'total_amount_due',
        'amount_paid',
        'payment_date',
        'payment_reference',
        'payment_status',
        'notes',
        'attachments',
        'filing_form_path',
        'prepared_by',
        'filed_by',
        'created_by',
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'tax_agency_id' => 'string',
        'prepared_by' => 'string',
        'filed_by' => 'string',
        'created_by' => 'string',
        'total_sales' => 'decimal:2',
        'total_purchases' => 'decimal:2',
        'output_tax' => 'decimal:2',
        'input_tax' => 'decimal:2',
        'tax_due' => 'decimal:2',
        'penalty' => 'decimal:2',
        'interest' => 'decimal:2',
        'total_amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'attachments' => 'array',
        'filing_period_start' => 'date',
        'filing_period_end' => 'date',
        'due_date' => 'date',
        'filing_date' => 'date',
        'payment_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function taxAgency()
    {
        return $this->belongsTo(TaxAgency::class, 'tax_agency_id', 'id');
    }

    public function taxComponents()
    {
        return $this->hasMany(TaxComponent::class, 'tax_return_id', 'id');
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by', 'id');
    }

    public function filedBy()
    {
        return $this->belongsTo(User::class, 'filed_by', 'id');
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

    public function scopeForTaxAgency($query, $taxAgencyId)
    {
        return $query->where('tax_agency_id', $taxAgencyId);
    }

    public function scopeByReturnType($query, $returnType)
    {
        return $query->where('return_type', $returnType);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', ['filed', 'paid', 'cancelled']);
    }

    public function scopePendingPayment($query)
    {
        return $query->whereIn('status', ['filed', 'prepared'])
            ->whereRaw('total_amount_due > amount_paid');
    }

    public function scopeByPeriod($query, $startDate, $endDate)
    {
        return $query->where('filing_period_start', '>=', $startDate)
            ->where('filing_period_end', '<=', $endDate);
    }

    // Business logic methods
    public function isDraft()
    {
        return $this->status === 'draft';
    }

    public function isPrepared()
    {
        return $this->status === 'prepared';
    }

    public function isFiled()
    {
        return $this->status === 'filed';
    }

    public function isPaid()
    {
        return $this->status === 'paid';
    }

    public function isOverdue()
    {
        return $this->due_date < now() && ! $this->isFiled() && ! $this->isPaid();
    }

    public function canBeEdited()
    {
        return in_array($this->status, ['draft', 'prepared']);
    }

    public function canBeFiled()
    {
        return in_array($this->status, ['prepared']);
    }

    public function canBePaid()
    {
        return in_array($this->status, ['filed']) && $this->total_amount_due > $this->amount_paid;
    }

    public function getRemainingAmountDue()
    {
        return max(0, $this->total_amount_due - $this->amount_paid);
    }

    public function isFullyPaid()
    {
        return $this->amount_paid >= $this->total_amount_due;
    }

    public function getReturnTypeLabelAttribute()
    {
        $labels = [
            'sales_tax' => 'Sales Tax',
            'purchase_tax' => 'Purchase Tax',
            'vat' => 'VAT',
            'income_tax' => 'Income Tax',
        ];

        return $labels[$this->return_type] ?? $this->return_type;
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'draft' => 'Draft',
            'prepared' => 'Prepared',
            'filed' => 'Filed',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'cancelled' => 'Cancelled',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getFilingMethodLabelAttribute()
    {
        $labels = [
            'paper' => 'Paper',
            'electronic' => 'Electronic',
            'auto' => 'Automatic',
        ];

        return $labels[$this->filing_method] ?? $this->filing_method;
    }

    public function getPaymentStatusLabelAttribute()
    {
        $labels = [
            'unpaid' => 'Unpaid',
            'partial' => 'Partially Paid',
            'paid' => 'Paid',
            'refunded' => 'Refunded',
        ];

        return $labels[$this->payment_status] ?? $this->payment_status;
    }

    // Workflow methods
    public function prepare($preparedBy = null)
    {
        if (! $this->canBeEdited()) {
            return false;
        }

        $this->status = 'prepared';
        $this->prepared_by = $preparedBy ?? auth()->id();

        return $this->save();
    }

    public function file($filingMethod = null, $confirmationNumber = null, $filedBy = null)
    {
        if (! $this->canBeFiled()) {
            return false;
        }

        $this->status = 'filed';
        $this->filing_date = now();
        $this->filing_method = $filingMethod ?? $this->filing_method;
        $this->confirmation_number = $confirmationNumber;
        $this->filed_by = $filedBy ?? auth()->id();

        // Update payment status
        if ($this->total_amount_due == 0) {
            $this->payment_status = 'paid';
            $this->status = 'paid';
        }

        return $this->save();
    }

    public function markAsPaid($amount = null, $paymentReference = null)
    {
        if (! $this->canBePaid()) {
            return false;
        }

        $paymentAmount = $amount ?? $this->getRemainingAmountDue();
        $this->amount_paid += $paymentAmount;
        $this->payment_date = now();
        $this->payment_reference = $paymentReference;

        if ($this->isFullyPaid()) {
            $this->status = 'paid';
            $this->payment_status = 'paid';
        } else {
            $this->payment_status = 'partial';
        }

        return $this->save();
    }

    public function calculateTotals()
    {
        $taxComponents = $this->taxComponents()
            ->where('tax_return_id', $this->id)
            ->get();

        $this->total_sales = 0;
        $this->total_purchases = 0;
        $this->output_tax = 0;
        $this->input_tax = 0;

        foreach ($taxComponents as $component) {
            // Determine if this is output tax (sales) or input tax (purchases)
            $transactionClass = $component->transaction_type;

            if (strpos($transactionClass, 'Invoice') !== false || strpos($transactionClass, 'Sale') !== false) {
                $this->total_sales += $component->taxable_amount;
                $this->output_tax += $component->tax_amount;
            } elseif (strpos($transactionClass, 'Bill') !== false || strpos($transactionClass, 'Purchase') !== false) {
                $this->total_purchases += $component->taxable_amount;
                $this->input_tax += $component->tax_amount;
            }
        }

        $this->tax_due = $this->output_tax - $this->input_tax;
        $this->total_amount_due = $this->tax_due + $this->penalty + $this->interest;

        return $this->save();
    }

    // Save hook to generate return number
    protected static function booted()
    {
        static::creating(function ($taxReturn) {
            if (empty($taxReturn->return_number)) {
                $year = date('Y');
                $prefix = 'TR'; // Tax Return

                switch ($taxReturn->return_type) {
                    case 'sales_tax':
                        $prefix = 'STR';
                        break;
                    case 'purchase_tax':
                        $prefix = 'PTR';
                        break;
                    case 'vat':
                        $prefix = 'VAT';
                        break;
                    case 'income_tax':
                        $prefix = 'ITR';
                        break;
                }

                $sequence = static::whereYear('created_at', $year)
                    ->where('company_id', $taxReturn->company_id)
                    ->where('return_type', $taxReturn->return_type)
                    ->count() + 1;

                $taxReturn->return_number = $prefix.'-'.$year.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
            }
        });

        static::saving(function ($taxReturn) {
            // Auto-update payment status
            if ($taxReturn->isDirty('amount_paid') || $taxReturn->isDirty('total_amount_due')) {
                if ($taxReturn->amount_paid >= $taxReturn->total_amount_due) {
                    $taxReturn->payment_status = 'paid';
                } elseif ($taxReturn->amount_paid > 0) {
                    $taxReturn->payment_status = 'partial';
                } else {
                    $taxReturn->payment_status = 'unpaid';
                }
            }
        });
    }
}
