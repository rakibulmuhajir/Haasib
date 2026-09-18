<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One slice of a supplier (AP) `BillPayment` applied to a `Bill` (reducing that bill's
 * balance). Unlike its AR mirror below, `bill_id` here is not nullable — there is no
 * on-account/unapplied-remainder concept on the payables side as of this writing.
 *
 * This class shares its name with two unrelated models — same word, different
 * mechanisms, do not conflate them:
 *  - {@see \App\Modules\Accounting\Models\PaymentAllocation} (`acct.payment_allocations`)
 *    is the buyer/AR mirror of this table: a `Payment` allocated to an `Invoice`, with a
 *    nullable `invoice_id` meaning an on-account credit.
 *  - {@see \App\Modules\Umrah\Models\PaymentAllocation} (`umrah.payment_allocations`)
 *    allocates a tour-group `GroupPayment` to a `VisaGroup` and has nothing to do with
 *    invoices or bills.
 */
class BillPaymentAllocation extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.bill_payment_allocations';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'bill_payment_id',
        'bill_id',
        'amount_allocated',
        'base_amount_allocated',
        'applied_at',
    ];

    protected $casts = [
        'company_id' => 'string',
        'bill_payment_id' => 'string',
        'bill_id' => 'string',
        'amount_allocated' => 'decimal:6',
        'base_amount_allocated' => 'decimal:2',
        'applied_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function billPayment()
    {
        return $this->belongsTo(BillPayment::class, 'bill_payment_id');
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }
}
