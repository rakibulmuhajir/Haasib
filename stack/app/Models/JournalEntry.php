<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'accounting.journal_entries';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'payment_id',
        'invoice_id',
        'allocation_id',
        'company_id',
        'entry_type',
        'account_code',
        'debit_amount',
        'credit_amount',
        'description',
        'reference',
        'date',
        'balance',
        'status',
        'metadata',
        'posted_at',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'date' => 'date',
        'posted_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'metadata',
    ];

    /**
     * Get the payment that owns the journal entry.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    /**
     * Get the invoice that owns the journal entry.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Get the allocation that owns the journal entry.
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocation::class, 'allocation_id');
    }

    /**
     * Get the company that owns the journal entry.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Get the chart of account for this entry.
     */
    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_code', 'account_code');
    }

    /**
     * Scope to get entries for a specific payment.
     */
    public function scopeForPayment($query, string $paymentId)
    {
        return $query->where('payment_id', $paymentId);
    }

    /**
     * Scope to get entries for a specific invoice.
     */
    public function scopeForInvoice($query, string $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    /**
     * Scope to get entries for a specific company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to get entries by type.
     */
    public function scopeByType($query, string $entryType)
    {
        return $query->where('entry_type', $entryType);
    }

    /**
     * Scope to get entries by account code.
     */
    public function scopeByAccount($query, string $accountCode)
    {
        return $query->where('account_code', $accountCode);
    }

    /**
     * Scope to get posted entries.
     */
    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    /**
     * Scope to get entries in a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Check if entry is a debit entry.
     */
    public function isDebit(): bool
    {
        return $this->debit_amount > 0;
    }

    /**
     * Check if entry is a credit entry.
     */
    public function isCredit(): bool
    {
        return $this->credit_amount > 0;
    }

    /**
     * Get the amount (debit or credit).
     */
    public function getAmount(): float
    {
        return max($this->debit_amount, $this->credit_amount);
    }

    /**
     * Get the entry type label.
     */
    public function getEntryTypeLabelAttribute(): string
    {
        $labels = [
            'payment' => 'Payment',
            'reversal' => 'Payment Reversal',
            'allocation' => 'Allocation',
            'allocation_reversal' => 'Allocation Reversal',
        ];

        return $labels[$this->entry_type] ?? $this->entry_type;
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'draft' => 'Draft',
            'posted' => 'Posted',
            'void' => 'Void',
        ];

        return $labels[$this->status] ?? $this->status;
    }
}