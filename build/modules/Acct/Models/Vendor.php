<?php

namespace Modules\Acct\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.vendors';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'vendor_code',
        'legal_name',
        'display_name',
        'tax_id',
        'vendor_type',
        'status',
        'website',
        'notes',
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function contacts()
    {
        return $this->hasMany(VendorContact::class, 'vendor_id', 'id');
    }

    public function primaryContact()
    {
        return $this->hasOne(VendorContact::class, 'vendor_id', 'id')->where('is_primary', true);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'vendor_id', 'id');
    }

    public function bills()
    {
        return $this->hasMany(Bill::class, 'vendor_id', 'id');
    }

    public function billPayments()
    {
        return $this->hasMany(BillPayment::class, 'vendor_id', 'id');
    }
}
