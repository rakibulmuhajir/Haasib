<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Passenger extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'umrah.passengers';
    protected $keyType = 'string';
    public $incrementing = false;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_EMBASSY = 'embassy';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DELIVERED = 'delivered';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_RECEIVED => 'Received',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_EMBASSY => 'Embassy',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_DELIVERED => 'Delivered',
    ];

    protected $fillable = [
        'company_id',
        'visa_group_id',
        'full_name',
        'passport_number',
        'nationality',
        'date_of_birth',
        'visa_status',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'company_id' => 'string',
        'visa_group_id' => 'string',
        'date_of_birth' => 'date',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(VisaGroup::class, 'visa_group_id');
    }

    public function voucherPassengers(): HasMany
    {
        return $this->hasMany(VoucherPassenger::class);
    }
}
