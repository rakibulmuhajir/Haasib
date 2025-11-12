<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxComponent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.tax_components';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'tax_rate_id',
        'transaction_type',
        'transaction_id',
        'transaction_line_id',
        'taxable_amount',
        'tax_rate_percentage',
        'tax_amount',
        'currency',
        'tax_code',
        'tax_name',
        'is_compound',
        'compound_base_amount',
        'is_reversed',
        'reversed_by',
        'reversal_reason',
        'reversed_at',
        'paid_amount',
        'credited_amount',
        'tax_period_start',
        'tax_period_end',
        'tax_return_id',
        'created_by',
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'tax_rate_id' => 'string',
        'transaction_id' => 'string',
        'transaction_line_id' => 'string',
        'taxable_amount' => 'decimal:2',
        'tax_rate_percentage' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'is_compound' => 'boolean',
        'compound_base_amount' => 'decimal:2',
        'is_reversed' => 'boolean',
        'reversed_by' => 'string',
        'reversed_at' => 'datetime',
        'paid_amount' => 'decimal:2',
        'credited_amount' => 'decimal:2',
        'tax_period_start' => 'date',
        'tax_period_end' => 'date',
        'tax_return_id' => 'string',
        'created_by' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function taxReturn()
    {
        return $this->belongsTo(TaxReturn::class, 'tax_return_id', 'id');
    }

    public function reversalComponent()
    {
        return $this->belongsTo(TaxComponent::class, 'reversed_by', 'id');
    }

    public function reversedByComponents()
    {
        return $this->hasMany(TaxComponent::class, 'reversed_by', 'id');
    }

    // Polymorphic relationship to get the transaction
    public function transaction(): MorphTo
    {
        return $this->morphTo('transaction', 'transaction_type', 'transaction_id');
    }

    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeNotReversed($query)
    {
        return $query->where('is_reversed', false);
    }

    public function scopeReversed($query)
    {
        return $query->where('is_reversed', true);
    }

    public function scopeForTransaction($query, $transactionType, $transactionId)
    {
        return $query->where('transaction_type', $transactionType)
            ->where('transaction_id', $transactionId);
    }

    public function scopeForTaxRate($query, $taxRateId)
    {
        return $query->where('tax_rate_id', $taxRateId);
    }

    public function scopeForTaxPeriod($query, $startDate, $endDate)
    {
        return $query->where('tax_period_start', '>=', $startDate)
            ->where('tax_period_end', '<=', $endDate);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereRaw('tax_amount > paid_amount + credited_amount');
    }

    public function scopePaid($query)
    {
        return $query->whereRaw('tax_amount <= paid_amount + credited_amount');
    }

    // Business logic methods
    public function isReversed()
    {
        return $this->is_reversed;
    }

    public function reverse($reason = null)
    {
        if ($this->is_reversed) {
            return false;
        }

        $this->is_reversed = true;
        $this->reversal_reason = $reason;
        $this->reversed_at = now();

        return $this->save();
    }

    public function getUnpaidAmountAttribute()
    {
        return max(0, $this->tax_amount - $this->paid_amount - $this->credited_amount);
    }

    public function isFullyPaid()
    {
        return $this->tax_amount <= ($this->paid_amount + $this->credited_amount);
    }

    public function markAsPaid($amount)
    {
        $remainingAmount = $this->getUnpaidAmountAttribute();
        $paymentAmount = min($amount, $remainingAmount);

        $this->paid_amount += $paymentAmount;

        return $this->save();
    }

    public function markAsCredited($amount)
    {
        $remainingAmount = $this->getUnpaidAmountAttribute();
        $creditAmount = min($amount, $remainingAmount);

        $this->credited_amount += $creditAmount;

        return $this->save();
    }

    public function getFormattedTaxAmountAttribute()
    {
        return number_format($this->tax_amount, 2);
    }

    public function getFormattedTaxableAmountAttribute()
    {
        return number_format($this->taxable_amount, 2);
    }

    public function getFormattedUnpaidAmountAttribute()
    {
        return number_format($this->getUnpaidAmountAttribute(), 2);
    }

    public function getPaymentStatusAttribute()
    {
        if ($this->is_reversed) {
            return 'Reversed';
        }

        if ($this->isFullyPaid()) {
            return 'Paid';
        }

        if ($this->paid_amount > 0 || $this->credited_amount > 0) {
            return 'Partially Paid';
        }

        return 'Unpaid';
    }

    // Static methods for creating tax components
    public static function createFromTransaction($transaction, $taxRate, $taxableAmount, $currency = 'USD', $transactionLineId = null)
    {
        $taxAmount = $taxRate->calculateTax($taxableAmount);
        $period = self::getTaxPeriod($transaction->date ?? now());

        return static::create([
            'id' => (string) \Str::uuid(),
            'company_id' => $transaction->company_id,
            'tax_rate_id' => $taxRate->id,
            'transaction_type' => get_class($transaction),
            'transaction_id' => $transaction->id,
            'transaction_line_id' => $transactionLineId,
            'taxable_amount' => $taxableAmount,
            'tax_rate_percentage' => $taxRate->rate,
            'tax_amount' => $taxAmount,
            'currency' => $currency,
            'tax_code' => $taxRate->code,
            'tax_name' => $taxRate->name,
            'is_compound' => $taxRate->is_compound,
            'compound_base_amount' => $taxRate->is_compound ? $taxableAmount : 0,
            'tax_period_start' => $period['start'],
            'tax_period_end' => $period['end'],
            'created_by' => auth()->id(),
        ]);
    }

    public static function getTaxPeriod($date, $reportingFrequency = 'quarterly')
    {
        $date = \Carbon\Carbon::parse($date);

        switch ($reportingFrequency) {
            case 'monthly':
                return [
                    'start' => $date->copy()->startOfMonth(),
                    'end' => $date->copy()->endOfMonth(),
                ];

            case 'quarterly':
                $quarter = ceil($date->month / 3);
                $startMonth = (($quarter - 1) * 3) + 1;

                return [
                    'start' => $date->copy()->month($startMonth)->startOfMonth(),
                    'end' => $date->copy()->month($startMonth + 2)->endOfMonth(),
                ];

            case 'annually':
                return [
                    'start' => $date->copy()->startOfYear(),
                    'end' => $date->copy()->endOfYear(),
                ];

            default:
                return [
                    'start' => $date->copy()->startOfMonth(),
                    'end' => $date->copy()->endOfMonth(),
                ];
        }
    }
}
