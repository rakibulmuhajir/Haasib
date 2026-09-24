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

    /**
     * The banks to offer a company: those of its own country, plus the bank an existing account
     * already uses, so editing never drops a choice that was made.
     *
     * The list is shared across every country, so a Pakistani station was offered Saudi banks. A
     * company whose country has no banks listed, or which has no country, still sees them all.
     */
    public function scopeForCountry($query, ?string $country, ?string $alsoBankId = null)
    {
        $country = strtoupper(trim((string) $country));

        if ($country === '' || ! static::query()->where('country_code', $country)->where('is_active', true)->exists()) {
            return $query;
        }

        return $query->where(function ($q) use ($country, $alsoBankId) {
            $q->where('country_code', $country);
            if ($alsoBankId) {
                $q->orWhere('id', $alsoBankId);
            }
        });
    }
}
