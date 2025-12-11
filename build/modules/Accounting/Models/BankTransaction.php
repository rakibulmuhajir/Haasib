<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
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
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function matchedPayment()
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }

    public function matchedBillPayment()
    {
        return $this->belongsTo(BillPayment::class, 'matched_bill_payment_id');
    }

    public function glTransaction()
    {
        return $this->belongsTo(Transaction::class, 'gl_transaction_id');
    }

    /**
     * Scope for "Parked" transactions (clarification queue).
     * Since we don't have a status, we assume parked = unreconciled AND has notes.
     */
    public function scopeParked($query)
    {
        return $query->where('is_reconciled', false)
                     ->whereNotNull('notes');
    }
}
