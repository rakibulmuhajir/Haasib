<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One slice of a payment: either applied to an invoice (invoice_id set, reducing that
 * invoice's balance) or sitting on the buyer's account unapplied (invoice_id null - an
 * advance, or the remainder once a payment's amount exceeds what was allocated to
 * invoices). A payment's allocations always sum to its own amount, so the null-invoice
 * row is what keeps that sum whole rather than leaving a gap; see
 * database/migrations/2026_09_18_000001_make_payment_allocations_invoice_nullable.php.
 */
class PaymentAllocation extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.payment_allocations';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'payment_id',
        'invoice_id',
        'amount_allocated',
        'base_amount_allocated',
        'applied_at',
    ];

    protected $casts = [
        'company_id' => 'string',
        'payment_id' => 'string',
        'invoice_id' => 'string',
        'amount_allocated' => 'decimal:6',
        'base_amount_allocated' => 'decimal:2',
        'applied_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
