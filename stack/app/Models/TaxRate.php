<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'acct.tax_rates';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'tax_agency_id',
        'name',
        'code',
        'description',
        'rate',
        'calculation_method',
        'fixed_amount',
        'tax_type',
        'is_compound',
        'is_reverse_charge',
        'is_inclusive',
        'country_code',
        'state_province',
        'city',
        'postal_code_pattern',
        'effective_from',
        'effective_to',
        'is_active',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'id' => 'string',
        'company_id' => 'string',
        'tax_agency_id' => 'string',
        'rate' => 'decimal:4',
        'fixed_amount' => 'decimal:2',
        'is_compound' => 'boolean',
        'is_reverse_charge' => 'boolean',
        'is_inclusive' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'created_by' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function taxAgency()
    {
        return $this->belongsTo(TaxAgency::class, 'tax_agency_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function taxComponents()
    {
        return $this->hasMany(TaxComponent::class, 'tax_rate_id', 'id');
    }

    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            })
            ->where('effective_from', '<=', now());
    }

    public function scopeEffectiveOn($query, $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }

    public function scopeByTaxType($query, $taxType)
    {
        return $query->where('tax_type', $taxType);
    }

    public function scopeByCountry($query, $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Business logic methods
    public function isActive()
    {
        return $this->is_active &&
               $this->effective_from <= now() &&
               (! $this->effective_to || $this->effective_to >= now());
    }

    public function isEffectiveOn($date)
    {
        return $this->effective_from <= $date &&
               (! $this->effective_to || $this->effective_to >= $date);
    }

    public function activate()
    {
        $this->is_active = true;

        return $this->save();
    }

    public function deactivate()
    {
        $this->is_active = false;

        return $this->save();
    }

    public function makeDefault()
    {
        // Remove default from all other tax rates for this company
        static::where('company_id', $this->company_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->is_default = true;

        return $this->save();
    }

    public function calculateTax($amount, $compoundAmount = 0)
    {
        if ($this->calculation_method === 'fixed_amount') {
            return $this->fixed_amount;
        }

        $baseAmount = $this->is_compound ? $compoundAmount : $amount;

        return ($baseAmount * $this->rate) / 100;
    }

    public function getFormattedRateAttribute()
    {
        return number_format($this->rate, 4).'%';
    }

    public function getCalculationMethodLabelAttribute()
    {
        $labels = [
            'percentage' => 'Percentage',
            'fixed_amount' => 'Fixed Amount',
        ];

        return $labels[$this->calculation_method] ?? $this->calculation_method;
    }

    public function getTaxTypeLabelAttribute()
    {
        $labels = [
            'sales' => 'Sales Tax',
            'purchase' => 'Purchase Tax',
            'both' => 'Sales & Purchase Tax',
        ];

        return $labels[$this->tax_type] ?? $this->tax_type;
    }

    public function getJurisdictionAttribute()
    {
        $parts = array_filter([
            $this->city,
            $this->state_province,
            $this->country_code,
        ]);

        return implode(', ', $parts);
    }

    // Business rules
    public function canBeDeleted()
    {
        // Check if tax rate is used in any invoices, bills, or tax components
        return ! $this->taxComponents()->exists();
    }

    public function appliesToLocation($countryCode, $stateProvince = null, $city = null, $postalCode = null)
    {
        // Check country match
        if ($this->country_code && $this->country_code !== $countryCode) {
            return false;
        }

        // Check state match
        if ($this->state_province && $this->state_province !== $stateProvince) {
            return false;
        }

        // Check city match
        if ($this->city && $this->city !== $city) {
            return false;
        }

        // Check postal code pattern
        if ($this->postal_code_pattern && $postalCode) {
            if (! preg_match('/'.$this->postal_code_pattern.'/', $postalCode)) {
                return false;
            }
        }

        return true;
    }

    // Save hook to handle default tax rate
    protected static function booted()
    {
        static::saving(function ($taxRate) {
            if ($taxRate->is_default) {
                // Remove default from all other tax rates for this company
                static::where('company_id', $taxRate->company_id)
                    ->where('id', '!=', $taxRate->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
