<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One slice of a supplier (AP) `BillPayment` applied to a `Bill` (reducing that bill's
 * balance). `bill_id` is never null here -- unlike its AR mirror below, an AP advance is
 * not represented by a dedicated allocation row of its own. Instead it is the gap between
 * a `BillPayment`'s amount and what its own allocation rows already total (see
 * {@see BillPayment::unappliedAmount()}), applied to a bill later by
 * {@see \App\Modules\Accounting\Services\VendorAdvanceService} either automatically (the
 * moment a bill for that vendor becomes payable) or by hand from the bill's page.
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
