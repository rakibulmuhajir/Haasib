<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillPayment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.bill_payments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'vendor_id',
        'payment_group_id',
        'payment_group_number',
        'payment_number',
        'payment_date',
        'amount',
        'currency',
        'exchange_rate',
        'base_currency',
        'base_amount',
        'transaction_charge',
        'base_transaction_charge',
        'payment_method',
        'payment_account_id',
        'transaction_id',
        'reference_number',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'vendor_id' => 'string',
        'payment_group_id' => 'string',
        'payment_date' => 'date',
        'amount' => 'decimal:6',
        'exchange_rate' => 'decimal:8',
        'base_amount' => 'decimal:2',
        'transaction_charge' => 'decimal:6',
        'base_transaction_charge' => 'decimal:2',
        'payment_account_id' => 'string',
        'transaction_id' => 'string',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function allocations()
    {
        return $this->hasMany(BillPaymentAllocation::class, 'bill_payment_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(Account::class, 'payment_account_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * What is left of this payment that is not matched to any bill -- an advance/on-account
     * balance with the vendor. There is no dedicated column or allocation row for it (unlike
     * the AR side's null-invoice_id row on `acct.payment_allocations`): it is simply this
     * payment's amount minus the sum of its own `bill_payment_allocations` rows, which stays
     * a valid invariant because `PostingService::postBillPayment` always posts the FULL
     * payment amount Dr AP / Cr cash regardless of how much of it is matched to a bill yet.
     * See {@see \App\Modules\Accounting\Services\VendorAdvanceService}, which applies this
     * balance to bills automatically and on demand.
     */
    public function unappliedAmount(): float
    {
        return max(0.0, round((float) $this->amount - (float) $this->allocations->sum('amount_allocated'), 6));
    }
}
