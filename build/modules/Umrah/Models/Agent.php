<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const COUNTRIES = [
        'Pakistan' => 'Pakistan',
        'Bangladesh' => 'Bangladesh',
        'India' => 'India',
        'Turkiye' => 'Turkiye',
        'United Kingdom' => 'United Kingdom',
        'United States' => 'United States',
    ];

    protected $connection = 'pgsql';
    protected $table = 'umrah.agents';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'user_id',
        'agent_number',
        'name',
        'phone',
        'email',
        'city',
        'country',
        'notes',
        'total_receivable',
        'total_paid',
        'balance',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'user_id' => 'string',
        'total_receivable' => 'decimal:2',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(VisaGroup::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(GroupPayment::class);
    }
}
