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
        'vendor_type',
        'address',
        'tax_id',
        'base_currency',
        'payment_terms',
        'account_number',
        'notes',
        'website',
        'logo_url',
        'is_active',
        'ap_account_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'address' => 'array',
        'payment_terms' => 'integer',
        'is_active' => 'boolean',
        'ap_account_id' => 'string',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public const TYPE_GENERAL = 'general';
    public const TYPE_FUEL_REFINERY = 'fuel_refinery';
    public const TYPE_FUEL_DISTRIBUTOR = 'fuel_distributor';
    public const TYPE_FUEL_STATION = 'fuel_station';
    public const TYPE_LUBRICANT_SUPPLIER = 'lubricant_supplier';
    public const TYPE_CONTRACTOR = 'contractor';
    public const TYPE_UTILITY = 'utility';
    public const TYPE_SERVICE_PROVIDER = 'service_provider';

    public const TYPES = [
        self::TYPE_GENERAL => 'General supplier',
        self::TYPE_FUEL_REFINERY => 'Fuel refinery',
        self::TYPE_FUEL_DISTRIBUTOR => 'Fuel distributor',
        self::TYPE_FUEL_STATION => 'Fuel station',
        self::TYPE_LUBRICANT_SUPPLIER => 'Lubricant supplier',
        self::TYPE_CONTRACTOR => 'Contractor',
        self::TYPE_UTILITY => 'Utility',
        self::TYPE_SERVICE_PROVIDER => 'Service provider',
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

    public function apAccount()
    {
        return $this->belongsTo(Account::class, 'ap_account_id');
    }
}
