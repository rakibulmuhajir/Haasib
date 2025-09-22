<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'auth.companies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'country',
        'country_id',
        'base_currency',
        'currency_id',
        'exchange_rate_id',
        'language',
        'locale',
        'settings',
        'created_by_user_id',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'created_by_user_id' => 'string',
        'country_id' => 'string',
        'currency_id' => 'string',
        'exchange_rate_id' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     *  Setup model event hooks
     */
    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (! $company->slug) {
                $base = Str::slug((string) $company->name) ?: Str::slug(Str::uuid());
                $slug = $base;
                $i = 1;
                while (self::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $company->slug = $slug;
            }
        });
    }

    /**
     * The users that belong to the company.
     */
    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'auth.company_user')
            ->withPivot('role', 'invited_by_user_id')
            ->withTimestamps();
    }

    /**
     * Get the user who created this company.
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    /**
     * Get the companies created by this user.
     */
    public function createdCompanies()
    {
        return $this->hasMany(\App\Models\Company::class, 'created_by_user_id');
    }

    /**
     * Get the customers that belong to the company.
     */
    public function customers()
    {
        return $this->hasMany(Customer::class, 'company_id', 'id');
    }

    /**
     * Get the owner of the company (user with 'owner' role).
     */
    public function owner()
    {
        return $this->users()->where('auth.company_user.role', 'owner')->first();
    }

    /**
     * Get the primary currency for the company.
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get the exchange rate for the company's primary currency.
     */
    public function exchangeRate()
    {
        return $this->belongsTo(ExchangeRate::class, 'exchange_rate_id', 'exchange_rate_id');
    }

    /**
     * Get the secondary currencies for the company.
     */
    public function secondaryCurrencies()
    {
        return $this->hasMany(CompanySecondaryCurrency::class);
    }

    /**
     * Get all currencies available to this company (primary + active secondary).
     */
    public function availableCurrencies()
    {
        $primaryCurrency = $this->currency ? collect([$this->currency]) : collect();
        $secondaryCurrencies = $this->secondaryCurrencies()->active()->with('currency')->get()->pluck('currency');

        return $primaryCurrency->merge($secondaryCurrencies)->unique('id');
    }

    /**
     * Get the current exchange rate for a specific target currency.
     */
    public function getExchangeRateFor($targetCurrencyId)
    {
        if (! $this->currency_id) {
            return null;
        }

        return ExchangeRate::where('base_currency_id', $this->currency_id)
            ->where('target_currency_id', $targetCurrencyId)
            ->active()
            ->forDate(now())
            ->first();
    }

    /**
     * Add a secondary currency to the company.
     */
    public function addSecondaryCurrency($currencyId, $settings = [])
    {
        return $this->secondaryCurrencies()->create([
            'currency_id' => $currencyId,
            'exchange_rate_id' => $this->getExchangeRateFor($currencyId)?->exchange_rate_id,
            'settings' => $settings,
            'is_active' => true,
        ]);
    }

    /**
     * Remove a secondary currency from the company.
     */
    public function removeSecondaryCurrency($currencyId)
    {
        return $this->secondaryCurrencies()->where('currency_id', $currencyId)->delete();
    }

    /**
     * Scope a query to only include active companies.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive companies.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Determine if the company is active.
     */
    public function isActive()
    {
        return $this->is_active;
    }

    /**
     * Activate the company.
     */
    public function activate()
    {
        $this->is_active = true;
        $this->save();
    }

    /**
     * Deactivate the company.
     */
    public function deactivate()
    {
        $this->is_active = false;
        $this->save();
    }
}
