<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.bills';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'vendor_id',
        'bill_number',
        'vendor_invoice_number',
        'bill_date',
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
        'received_at',
        'goods_received_at',
        'approved_at',
        'paid_at',
        'voided_at',
        'recurring_schedule_id',
        'transaction_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'vendor_id' => 'string',
        'recurring_schedule_id' => 'string',
        'bill_date' => 'date',
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
        'received_at' => 'datetime',
        'goods_received_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
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

    public function recurringSchedule()
    {
        return $this->belongsTo(RecurringBillSchedule::class, 'recurring_schedule_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function lineItems()
    {
        return $this->hasMany(BillLineItem::class, 'bill_id');
    }

    public function paymentAllocations()
    {
        return $this->hasMany(BillPaymentAllocation::class, 'bill_id');
    }
}
