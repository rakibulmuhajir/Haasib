<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.vendors';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'vendor_number',
        'name',
        'email',
        'phone',
        'address',
        'tax_id',
        'base_currency',
        'payment_terms',
        'account_number',
        'notes',
        'website',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'address' => 'array',
        'payment_terms' => 'integer',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class, 'vendor_id');
    }

    public function billPayments()
    {
        return $this->hasMany(BillPayment::class, 'vendor_id');
    }

    public function vendorCredits()
    {
        return $this->hasMany(VendorCredit::class, 'vendor_id');
    }
}
