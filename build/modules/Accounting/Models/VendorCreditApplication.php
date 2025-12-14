<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorCreditApplication extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.vendor_credit_applications';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'vendor_credit_id',
        'bill_id',
        'amount_applied',
        'applied_at',
        'user_id',
        'notes',
        'bill_balance_before',
        'bill_balance_after',
    ];

    protected $casts = [
        'company_id' => 'string',
        'vendor_credit_id' => 'string',
        'bill_id' => 'string',
        'amount_applied' => 'decimal:6',
        'bill_balance_before' => 'decimal:2',
        'bill_balance_after' => 'decimal:2',
        'applied_at' => 'datetime',
        'user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function vendorCredit()
    {
        return $this->belongsTo(VendorCredit::class, 'vendor_credit_id');
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }
}
