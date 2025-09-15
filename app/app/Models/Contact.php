<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Contact extends Model
{
    use HasFactory;

    protected $table = 'contacts';

    protected $primaryKey = 'contact_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'contact_id',
        'company_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'customer_id',
        'vendor_id',
        'position',
        'notes',
        'is_primary',
    ];

    protected $casts = [
        'company_id' => 'string',
        'customer_id' => 'string',
        'vendor_id' => 'string',
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (! isset($model->contact_id)) {
                $model->contact_id = (string) Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function getDisplayName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
