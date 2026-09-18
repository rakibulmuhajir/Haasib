<?php

namespace App\Modules\Umrah\Models;

use App\Modules\Accounting\Models\Transaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One slice of a tour-group `GroupPayment` applied to a `VisaGroup` (`visa_group_id`
 * set), or a `Refund`'s debit drawn against a group's advances (`refund_id` set,
 * `visa_group_id` null — see {@see refund()}). This table has nothing to do with
 * invoices or bills: there is no `invoice_id`/`bill_id` column here.
 *
 * This class shares its name with two unrelated models in the Accounting module — same
 * word, different mechanisms, do not conflate them:
 *  - {@see \App\Modules\Accounting\Models\PaymentAllocation} (`acct.payment_allocations`)
 *    allocates a buyer `Payment` to an `Invoice` (with a null-invoice on-account variant).
 *  - {@see \App\Modules\Accounting\Models\BillPaymentAllocation} (`acct.bill_payment_allocations`)
 *    allocates a supplier `BillPayment` to a `Bill`.
 */
class PaymentAllocation extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'umrah.payment_allocations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id', 'group_payment_id', 'visa_group_id', 'refund_id', 'base_amount', 'transaction_id',
        'reversed_at', 'reversed_by_user_id', 'reversal_reason', 'reversal_transaction_id',
    ];

    protected $casts = [
        'company_id' => 'string', 'group_payment_id' => 'string', 'visa_group_id' => 'string',
        'refund_id' => 'string', 'base_amount' => 'decimal:2', 'transaction_id' => 'string', 'reversed_at' => 'datetime',
        'reversed_by_user_id' => 'string', 'reversal_transaction_id' => 'string',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(GroupPayment::class, 'group_payment_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(VisaGroup::class, 'visa_group_id');
    }

    /**
     * Set only on a row created by UmrahCoreService::debitAgentAdvances() --
     * a refund's draw against this payment, not an allocation to a group.
     * Every existing reader of `allocations`/`allAllocations` sums this
     * table without filtering on visa_group_id, so a debit row is already
     * counted as spent everywhere that matters without those readers
     * needing to know it exists.
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function reversalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'reversal_transaction_id');
    }
}
