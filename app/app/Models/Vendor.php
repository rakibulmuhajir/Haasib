<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Vendor extends Model
{
    use HasFactory;

    protected $table = 'vendors';

    protected $primaryKey = 'vendor_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'vendor_id',
        'company_id',
        'name',
        'email',
        'phone',
        'tax_number',
        'address',
        'currency_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'company_id' => 'string',
        'currency_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->vendor_id = $model->vendor_id ?: (string) Str::uuid();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getDisplayName(): string
    {
        return $this->name ?: $this->vendor_number;
    }
}
