<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisaService extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'umrah.visa_services';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'vendor_id',
        'name',
        'retail_amount',
        'cost_amount',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'vendor_id' => 'string',
        'retail_amount' => 'decimal:2',
        'cost_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VisaVendor::class, 'vendor_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(VisaGroup::class);
    }
}
