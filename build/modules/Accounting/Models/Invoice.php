<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, HasUuids, SoftDeletes;
    use \App\Modules\Accounting\Models\Concerns\ProtectsOpeningDocument;

    protected static function booted(): void
    {
        static::updating(function (self $invoice) {
            // discount_amount/total_amount joined the settlement-style whitelist for a
            // post-close discount (DailyClosePostCloseDiscountService::apply()): a legitimate
            // amendment to a posted close's credit invoice, gated by that service's own
            // checks (the close must be unlocked, the invoice must belong to it, the
            // discount cannot exceed the balance) rather than by this guard. The DB-level
            // fuel.protect_close_credit_invoice() trigger enforces the same invariants as a
            // backstop -- see the 2026_09_26_010000 migration.
            $settlement = ['paid_amount', 'balance', 'paid_at', 'updated_at', 'updated_by_user_id', 'status', 'discount_amount', 'total_amount'];
            // total/discount only count as settlement when they move together as a discount:
            // the discount grows and the total falls by exactly that much. Any other change to
            // them is a change of principal and goes through assertMutable like everything else.
            $oldDiscount = (float) $invoice->getOriginal('discount_amount');
            $oldTotal = (float) $invoice->getOriginal('total_amount');
            $principalMoved = ($invoice->isDirty('total_amount') || $invoice->isDirty('discount_amount'))
                && ! ((float) $invoice->discount_amount >= $oldDiscount
                    && abs(($oldTotal - (float) $invoice->total_amount) - ((float) $invoice->discount_amount - $oldDiscount)) < 0.000001);
            if ($principalMoved || array_diff(array_keys($invoice->getDirty()), $settlement)
                || ($invoice->isDirty('status') && in_array($invoice->status, ['draft', 'void', 'cancelled', 'reversed'], true))) {
                app(\App\Modules\FuelStation\Services\DailyCloseCreditSaleService::class)->assertMutable($invoice);
            }
        });
        static::deleting(fn (self $invoice) => app(\App\Modules\FuelStation\Services\DailyCloseCreditSaleService::class)->assertMutable($invoice));
    }

    protected $connection = 'pgsql';
    protected $table = 'acct.invoices';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'status',
        'currency',
        'base_currency',
        'exchange_rate',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance',
        'base_amount',
        'payment_terms',
        'notes',
        'internal_notes',
        'sent_at',
        'viewed_at',
        'paid_at',
        'voided_at',
        'recurring_schedule_id',
        'transaction_id',
        'is_direct_delivery',
        'included_in_close_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'customer_id' => 'string',
        'recurring_schedule_id' => 'string',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:6',
        'tax_amount' => 'decimal:6',
        'discount_amount' => 'decimal:6',
        'total_amount' => 'decimal:6',
        'paid_amount' => 'decimal:6',
        'balance' => 'decimal:6',
        'base_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'payment_terms' => 'integer',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
        'transaction_id' => 'string',
        'is_direct_delivery' => 'boolean',
        'included_in_close_id' => 'string',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function lineItems()
    {
        return $this->hasMany(InvoiceLineItem::class, 'invoice_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /** 1:1 fuel-station metadata, when this invoice came from a fuel sale. */
    public function saleMetadata()
    {
        return $this->hasOne(\App\Modules\FuelStation\Models\SaleMetadata::class, 'invoice_id');
    }

    /**
     * Generate an invoice number scoped per company (simple incremental suffix).
     * Uses company's invoice_prefix and invoice_start_number settings.
     */
    public static function generateInvoiceNumber(string $companyId): string
    {
        return app(\App\Modules\Accounting\Services\InvoiceNumberService::class)->next($companyId);
    }
}
