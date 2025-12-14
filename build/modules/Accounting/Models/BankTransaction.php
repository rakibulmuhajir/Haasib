<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankTransaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.bank_transactions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'reconciliation_id',
        'transaction_date',
        'value_date',
        'description',
        'reference_number',
        'transaction_type',
        'amount',
        'balance_after',
        'payee_name',
        'category',
        'is_reconciled',
        'reconciled_date',
        'reconciled_by_user_id',
        'matched_payment_id',
        'matched_bill_payment_id',
        'gl_transaction_id',
        'source',
        'external_id',
        'raw_data',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'bank_account_id' => 'string',
        'reconciliation_id' => 'string',
        'transaction_date' => 'date',
        'value_date' => 'date',
        'amount' => 'decimal:6',
        'balance_after' => 'decimal:2',
        'is_reconciled' => 'boolean',
        'reconciled_date' => 'date',
        'reconciled_by_user_id' => 'string',
        'matched_payment_id' => 'string',
        'matched_bill_payment_id' => 'string',
        'gl_transaction_id' => 'string',
        'raw_data' => 'array',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }

    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }

    public function matchedBillPayment(): BelongsTo
    {
        return $this->belongsTo(BillPayment::class, 'matched_bill_payment_id');
    }

    public function glTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'gl_transaction_id');
    }

    public function reconciledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Check if transaction is an inflow (money in).
     */
    public function isInflow(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Check if transaction is an outflow (money out).
     */
    public function isOutflow(): bool
    {
        return $this->amount < 0;
    }

    /**
     * Scope for unreconciled transactions.
     */
    public function scopeUnreconciled($query)
    {
        return $query->where('is_reconciled', false);
    }

    /**
     * Scope for reconciled transactions.
     */
    public function scopeReconciled($query)
    {
        return $query->where('is_reconciled', true);
    }

    /**
     * Scope for "Parked" transactions (clarification queue).
     */
    public function scopeParked($query)
    {
        return $query->where('is_reconciled', false)
                     ->whereNotNull('notes');
    }

    /**
     * Scope for transactions within a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }
}
