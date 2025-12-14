<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bank extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.banks';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'swift_code',
        'country_code',
        'logo_url',
        'website',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'bank_id');
    }

    /**
     * Scope for active banks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
