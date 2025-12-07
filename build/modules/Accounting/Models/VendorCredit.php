<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorCredit extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.vendor_credits';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'vendor_id',
        'bill_id',
        'credit_number',
        'vendor_credit_number',
        'credit_date',
        'amount',
        'currency',
        'base_currency',
        'exchange_rate',
        'base_amount',
        'reason',
        'status',
        'notes',
        'received_at',
        'voided_at',
        'cancellation_reason',
        'journal_entry_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'vendor_id' => 'string',
        'bill_id' => 'string',
        'credit_date' => 'date',
        'amount' => 'decimal:6',
        'exchange_rate' => 'decimal:8',
        'base_amount' => 'decimal:2',
        'received_at' => 'datetime',
        'voided_at' => 'datetime',
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

    public function bill()
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function items()
    {
        return $this->hasMany(VendorCreditItem::class, 'vendor_credit_id');
    }

    public function applications()
    {
        return $this->hasMany(VendorCreditApplication::class, 'vendor_credit_id');
    }
}
