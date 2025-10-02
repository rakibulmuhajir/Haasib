<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Interaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hrm.interactions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'customer_id',
        'vendor_id',
        'interaction_type',
        'interaction_date',
        'subject',
        'description',
        'outcome',
        'follow_up_required',
        'follow_up_date',
        'assigned_to_user_id',
        'metadata',
    ];

    protected $casts = [
        'interaction_date' => 'date',
        'follow_up_date' => 'date',
        'follow_up_required' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'follow_up_required' => false,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = $model->id ?: (string) Str::uuid();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeWithType($query, $type)
    {
        return $query->where('interaction_type', $type);
    }

    public function scopeRequiresFollowUp($query)
    {
        return $query->where('follow_up_required', true);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('interaction_date', [$startDate, $endDate]);
    }
}
