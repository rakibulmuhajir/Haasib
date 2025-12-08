<?php

namespace App\Modules\Banking\Models;

use App\Models\BillPayment;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use Modules\Accounting\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BankTransaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'bank.bank_transactions';
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class);
    }

    public function reconciliation()
    {
        return $this->belongsTo(BankReconciliation::class);
    }

    public function reconciledByUser()
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }

    public function matchedPayment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function matchedBillPayment()
    {
        return $this->belongsTo(BillPayment::class);
    }

    public function glTransaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    // Scopes for common queries
    public function scopeReconciled($query)
    {
        return $query->where('is_reconciled', true);
    }

    public function scopeUnreconciled($query)
    {
        return $query->where('is_reconciled', false);
    }

    public function scopeDeposits($query)
    {
        return $query->where('transaction_type', 'deposit');
    }

    public function scopeWithdrawals($query)
    {
        return $query->where('transaction_type', 'withdrawal');
    }

    public function scopeTransfersIn($query)
    {
        return $query->where('transaction_type', 'transfer_in');
    }

    public function scopeTransfersOut($query)
    {
        return $query->where('transaction_type', 'transfer_out');
    }

    public function scopeFees($query)
    {
        return $query->where('transaction_type', 'fee');
    }

    public function scopeInterest($query)
    {
        return $query->where('transaction_type', 'interest');
    }

    public function scopeAdjustments($query)
    {
        return $query->where('transaction_type', 'adjustment');
    }

    public function scopeOpeningBalance($query)
    {
        return $query->where('transaction_type', 'opening');
    }

    public function scopeManual($query)
    {
        return $query->where('source', 'manual');
    }

    public function scopeImported($query)
    {
        return $query->where('source', 'import');
    }

    public function scopeFromFeed($query)
    {
        return $query->where('source', 'feed');
    }

    public function scopeSystem($query)
    {
        return $query->where('source', 'system');
    }
}