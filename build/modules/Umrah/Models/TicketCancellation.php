<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\VendorCredit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A cancellation undoes at most one ticket. Its cost is derived, never
 * stored, the same way Ticket::commissionBase() is -- see costBase().
 */
class TicketCancellation extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'umrah.ticket_cancellations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'ticket_id',
        'cancellation_date',
        'supplier_returns_currency',
        'supplier_returns_exchange_rate',
        'supplier_returns_amount',
        'supplier_returns_base',
        'buyer_returns_currency',
        'buyer_returns_exchange_rate',
        'buyer_returns_amount',
        'buyer_returns_base',
        'base_currency',
        'buyer_credit_note_id',
        'supplier_vendor_credit_id',
        'buyer_refund_id',
        'supplier_refund_receipt_id',
        'idempotency_key',
        'reason',
    ];

    protected $casts = [
        'company_id' => 'string',
        'ticket_id' => 'string',
        'buyer_credit_note_id' => 'string',
        'supplier_vendor_credit_id' => 'string',
        'buyer_refund_id' => 'string',
        'supplier_refund_receipt_id' => 'string',
        'cancellation_date' => 'date',
        'supplier_returns_exchange_rate' => 'decimal:8',
        'supplier_returns_amount' => 'decimal:6',
        'supplier_returns_base' => 'decimal:2',
        'buyer_returns_exchange_rate' => 'decimal:8',
        'buyer_returns_amount' => 'decimal:6',
        'buyer_returns_base' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function buyerCreditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class, 'buyer_credit_note_id');
    }

    public function supplierVendorCredit(): BelongsTo
    {
        return $this->belongsTo(VendorCredit::class, 'supplier_vendor_credit_id');
    }

    /**
     * Positive is a cost. The buyer leg debits 4160 and the supplier leg
     * credits it, so this figure is the net debit the ledger is left
     * holding -- computed the same way in both places on purpose.
     */
    public function costBase(): float
    {
        return (float) $this->buyer_returns_base - (float) $this->supplier_returns_base;
    }
}
