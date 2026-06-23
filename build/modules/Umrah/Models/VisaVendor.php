<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisaVendor extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'umrah.visa_vendors';
    protected $keyType = 'string';
    public $incrementing = false;

    public const TYPE_GOVERNMENT = 'government';
    public const TYPE_VISA_PROVIDER = 'visa_provider';
    public const TYPE_TRANSPORT_PROVIDER = 'transport_provider';
    public const TYPE_HOTEL = 'hotel';
    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_GOVERNMENT => 'Government',
        self::TYPE_VISA_PROVIDER => 'Visa provider',
        self::TYPE_TRANSPORT_PROVIDER => 'Transport provider',
        self::TYPE_HOTEL => 'Hotel',
        self::TYPE_OTHER => 'Other',
    ];

    protected $fillable = [
        'company_id',
        'vendor_number',
        'name',
        'vendor_type',
        'phone',
        'email',
        'city',
        'notes',
        'total_cost',
        'total_paid',
        'balance',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'total_cost' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(VisaGroup::class, 'vendor_id');
    }
}
